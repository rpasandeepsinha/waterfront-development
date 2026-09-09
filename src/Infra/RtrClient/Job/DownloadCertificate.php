<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\Job;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Bus\Dispatcher;
use JsonException;
use Spatie\SslCertificate\SslCertificate;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Ssl\Jobs\InstallCertificate;
use Waterfront\Domain\Ssl\Mailers\SslRenewalSucces;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Infra\RtrClient\Exceptions\Ssl\DownloadCertificateException;
use Waterfront\Infra\RtrClient\Services\Ssl\CertificateDownloader;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;
use Webmozart\Assert\Assert;

class DownloadCertificate extends AbstractQueueableJob
{
    public int $tries = 5;

    /** @var array<int> */
    public array $backoff = [60, 5 * 60, 30 * 60, 60 * 60, 2 * 60 * 60];

    public function __construct(
        private readonly SslDeployment $sslDeployment,
        private readonly bool $autoDispatchInstallCertificate = true,
    ) {
        parent::__construct();
    }

    /**
     * @throws DownloadCertificateException
     * @throws JsonException
     */
    public function handle(
        CertificateDownloader $certificateDownloader,
        Dispatcher $eventDispatcher,
        MailerInterface $mailer
    ): void {
        try {
            $certificateDownloader->downloadForSslDeployment($this->sslDeployment);

            $message = [
                'certificate_status' => 'Downloaded and ready for installation.',
                'message' => 'SSL certificate downloaded. Ready for installation.',
            ];
            $this->updateSslDeploymentStatus(TechnicalStatus::OK->value, $message);
        } catch (DownloadCertificateException $exception) {
            $lastResult = [
                'certificate_status' => 'Download error.',
                'message' => 'Certificate download failed: ' . $exception->getMessage(),
            ];
            $this->updateSslDeploymentStatus(TechnicalStatus::ERROR->value, $lastResult);

            throw $exception;
        }

        if ($this->autoDispatchInstallCertificate) {
            $eventDispatcher->dispatch(
                new InstallCertificate($this->sslDeployment)
            );
        } else {
            $subscription = $this->sslDeployment->subscription;
            Assert::notNull($subscription->domain);
            $expiryDate = $this->getOldCertificateExpiryDate($subscription->domain);
            $mailer->send(
                [$subscription->customer],
                new SslRenewalSucces(
                    domain: $subscription->domain,
                    expirydate: $expiryDate->format('d-m-Y')
                )
            );
        }
    }

    public function getSslDeployment(): SslDeployment
    {
        return $this->sslDeployment;
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::PARTNER_SSL;
    }

    /**
     * @param array<mixed> $lastResult
     *
     * @throws JsonException
     */
    private function updateSslDeploymentStatus(string $technicalStatus, array $lastResult): void
    {
        $sslSubscription = $this->sslDeployment->subscription;
        $sslSubscription->technical_status = $technicalStatus;
        $sslSubscription->save();

        $this->sslDeployment->last_result = json_encode($lastResult, JSON_THROW_ON_ERROR);
        $this->sslDeployment->last_result_received = CarbonImmutable::now();
        $this->sslDeployment->save();
    }

    private function getOldCertificateExpiryDate(string $domain): CarbonImmutable
    {
        $sslCert = SslCertificate::createForHostName($domain);
        Assert::isInstanceOf($sslCert, SslCertificate::class);

        $expiresAt = $sslCert->expirationDate();
        return $expiresAt->toImmutable();
    }
}
