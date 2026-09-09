<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Services;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use RealtimeRegister\Domain\Certificate;
use RealtimeRegister\Exceptions\RealtimeRegisterClientException;
use RealtimeRegister\RealtimeRegister;
use RuntimeException;
use Spatie\SslCertificate\Downloader;
use Spatie\SslCertificate\SslCertificate;
use Throwable;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Ssl\Interfaces\Models\CertificateInstall\Parameters;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Ssl\Repositories\DeploymentRepository;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class CertificateService
{
    public function __construct(
        private readonly CsrManager $csrManager,
        private readonly CertificateManager $certificateManager,
        private readonly RealtimeRegister $realtimeRegister,
        private readonly Downloader $certificateDownloader,
        private readonly LoggerInterface $logger,
        private readonly DeploymentRepository $sslDeploymentRepository,
    ) {
    }

    /**
     * Check if the domain has a valid certificate.
     */
    public function check(string $domain, int $period = 12): bool
    {
        $certificate = SslCertificate::createForHostName($domain);
        assert($certificate instanceof SslCertificate);

        return $certificate->isValidUntil(Carbon::now()->addMonths($period - 1));
    }

    /**
     * Collect the required parameters for installing a certificate.
     */
    public function prepareCertificateInstallParameters(string $domain): Parameters
    {
        try {
            $csr = $this->csrManager->getRawCsr($domain);
        } catch (FileNotFoundException $e) {
            Log::info("Unable to fetch CSR from filesystem attempting to gather from Provider from domain: $domain");

            $subscription = Subscription::query()->whereProductGroupType(ProductGroupType::SSL)
                ->where('domain', $domain)
                ->firstOrFail();

            $sslDeployment = $subscription->sslDeployment;
            if ($sslDeployment === null) {
                throw new RuntimeException("prepareCertificateInstallParameters:: Unable to find ssl deployment for domain: $domain", 0, $e);
            }

            if (! $csr = $sslDeployment->custom_csr) {
                throw new RuntimeException("prepareCertificateInstallParameters:: Unable to find csr for domain: $domain", 0, $e);
            }
        }

        return Parameters::create(
            [
                'name' => $domain . '-certificate-' . CarbonImmutable::now()->toIso8601String(),
                'domain' => $domain,
                'csr' => $csr,
                'pvt' => $this->csrManager->getPrivateKey($domain),
                'cert' => $this->certificateManager->getMainCertificate($domain),
                'ca' => $this->certificateManager->getIntermediateCertificate($domain),
            ]
        );
    }

    /**
     * @throws RuntimeException
     */
    public function updateSslExpireDate(SslDeployment $sslDeployment): void
    {
        if ($sslDeployment->provider->slug !== ProviderSlug::REALTIME_REGISTER) {
            $date = $this->fetchExpireDateFromHost($sslDeployment);
            $sslDeployment->expire_date = $date;
            $sslDeployment->save();
            return;
        }

        if ($sslDeployment->certificate_id === null) {
            $certificate = $this->backfillRtrCertificateId($sslDeployment);

            $date = $certificate instanceof Certificate
                ? CarbonImmutable::createFromMutable($certificate->expiryDate)
                : $this->fetchExpireDateFromHost($sslDeployment);
        } else {
            $date = $this->fetchExpireDateFromRtr($sslDeployment);
        }

        $sslDeployment->expire_date = $date;
        $sslDeployment->save();
    }

    /**
     * @throws RuntimeException
     */
    private function fetchExpireDateFromRtr(SslDeployment $sslDeployment): CarbonImmutable
    {
        Assert::notNull($sslDeployment->certificate_id, 'RTR certificate_id must be set before fetching expire date from RTR.');

        try {
            $certificate = $this->realtimeRegister->certificates->getCertificate($sslDeployment->certificate_id);

            return CarbonImmutable::createFromMutable($certificate->expiryDate);
        } catch (RealtimeRegisterClientException) {
            $this->logger->warning(
                sprintf(
                    'Failed to fetch ssl expire date from RTR for domain: %s. Trying to fetch from host.',
                    $sslDeployment->subscription->domain,
                ),
                [
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::SSL,
                    LoggingContextKeys::PROVISIONING_PROVIDER => $sslDeployment->provider->slug,
                    LoggingContextKeys::PROVISIONING_ID => $sslDeployment->id,
                    LoggingContextKeys::DOMAIN_NAME => $sslDeployment->subscription->domain,
                ]
            );

            return $this->fetchExpireDateFromHost($sslDeployment);
        }
    }

    /**
     * @throws RuntimeException
     */
    private function fetchExpireDateFromHost(SslDeployment $sslDeployment): CarbonImmutable
    {
        if ($sslDeployment->subscription->domain === null) {
            $this->logger->warning(
                'Failed to fetch ssl expire date because domain is null.',
                [
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::SSL,
                    LoggingContextKeys::PROVISIONING_PROVIDER => $sslDeployment->provider->slug,
                    LoggingContextKeys::PROVISIONING_ID => $sslDeployment->id,
                ]
            );

            throw new RuntimeException('Failed to fetch ssl expire date because domain is null.');
        }

        try {
            $certificate = $this->certificateDownloader->downloadCertificateFromUrl($sslDeployment->subscription->domain);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                sprintf(
                    'Failed to download ssl certificate from host for domain: %s.',
                    $sslDeployment->subscription->domain,
                ),
                $exception->getCode(),
                $exception
            );
        }

        if (! $certificate instanceof SslCertificate) {
            $this->logger->warning(
                sprintf(
                    'Failed to fetch ssl expire date from host for domain: %s.',
                    $sslDeployment->subscription->domain,
                ),
                [
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::SSL,
                    LoggingContextKeys::PROVISIONING_PROVIDER => $sslDeployment->provider->slug,
                    LoggingContextKeys::PROVISIONING_ID => $sslDeployment->id,
                    LoggingContextKeys::PRODUCT_SLUG => $sslDeployment->subscription->product->slug,
                    LoggingContextKeys::DOMAIN_NAME => $sslDeployment->subscription->domain,
                ]
            );

            throw new RuntimeException(
                sprintf(
                    'Failed to fetch ssl expire date from host for domain: %s.',
                    $sslDeployment->subscription->domain,
                )
            );
        }

        return CarbonImmutable::createFromMutable($certificate->expirationDate());
    }

    private function backfillRtrCertificateId(SslDeployment $sslDeployment): ?Certificate
    {
        $domain = $sslDeployment->subscription->domain;
        $processId = $sslDeployment->request_id;

        $context = [
            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::SSL,
            LoggingContextKeys::PROVISIONING_PROVIDER => $sslDeployment->provider->slug,
            LoggingContextKeys::PROVISIONING_ID => $sslDeployment->id,
            LoggingContextKeys::DOMAIN_NAME => $domain,
            LoggingContextKeys::META => [
                'request_id' => $processId,
            ],
        ];

        if ($domain === null || $processId === null) {
            $this->logger->warning('RTR certificate_id backfill skipped: missing domain or request_id', $context);
            return null;
        }

        try {
            $certificate = $this->realtimeRegister->certificates->listCertificates(
                limit: 1,
                offset: 0,
                parameters: [
                    'process' => $processId,
                    'status' => 'ACTIVE',
                    'order' => '-startDate',
                ],
            )[0];

            if ($certificate === null) {
                $this->logger->warning('RTR certificate_id backfill: no ACTIVE certificate found for request', $context);
                return null;
            }

            if ($certificate->domainName !== $domain) {
                $meta = [
                    ...$context[LoggingContextKeys::META],
                    'certificate_id' => $certificate->id,
                    'rtr_domain' => $certificate->domainName,
                ];

                $this->logger->warning(
                    'RTR certificate_id backfill: domain mismatch',
                    array_merge($context, [LoggingContextKeys::META => $meta])
                );

                return null;
            }

            $updated = $this->sslDeploymentRepository->backfillCertificateId(
                sslDeploymentId: $sslDeployment->id,
                certificateId: $certificate->id
            );

            if ($updated) {
                $meta = [
                    ...$context[LoggingContextKeys::META],
                    'certificate_id' => $certificate->id,
                ];

                $this->logger->info(
                    'RTR certificate_id backfilled',
                    array_merge($context, [LoggingContextKeys::META => $meta])
                );
            }

            return $certificate;
        } catch (RealtimeRegisterClientException $exception) {
            $this->logger->warning(
                'RTR certificate_id backfill failed: RTR client exception',
                $context + [LoggingContextKeys::EXCEPTION => $exception]
            );

            return null;
        }
    }
}
