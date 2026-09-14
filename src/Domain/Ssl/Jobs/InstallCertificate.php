<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Jobs;

use Carbon\CarbonImmutable;
use JsonException;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Ssl\Services\CertificateInstaller;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Infra\RtrClient\Exceptions\Ssl\InstallCertificateException;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class InstallCertificate extends AbstractQueueableJob
{
    public function __construct(
        private readonly SslDeployment $sslDeployment,
    ) {
        parent::__construct();
    }

    /**
     * @throws InstallCertificateException
     * @throws JsonException
     */
    public function handle(CertificateInstaller $certificateInstaller): void
    {
        try {
            $certificateInstaller->installForSslDeployment($this->sslDeployment);

            $lastResult = [
                'certificate_status' => 'Ready and installed.',
                'message' => 'Certificate installed',
            ];
            $this->updateSslDeploymentStatus(TechnicalStatus::OK->value, $lastResult);
        } catch (InstallCertificateException $exception) {
            $lastResult = [
                'certificate_status' => 'Installation error.',
                'message' => 'Certificate installation failed: ' . $exception->getMessage(),
            ];
            $this->updateSslDeploymentStatus(TechnicalStatus::ERROR->value, $lastResult);

            throw $exception;
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
        $subscription = $this->sslDeployment->subscription;
        $subscription->technical_status = $technicalStatus;
        $subscription->save();

        $this->sslDeployment->last_result = json_encode($lastResult, JSON_THROW_ON_ERROR);
        $this->sslDeployment->last_result_received = CarbonImmutable::now();
        $this->sslDeployment->save();
    }
}
