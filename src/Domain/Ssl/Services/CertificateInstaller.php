<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Services;

use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Artisan;
use JsonException;
use Psr\Log\LoggerInterface;
use Waterfront\Apps\Console\Commands\Sanity\CheckSsl;
use Waterfront\Domain\Hosting\Factories\HostingServiceFactory;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result as HostingResult;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Domain\Products\Models\ProductSpec;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Sitebuilder\Factories\SitebuilderServiceFactory;
use Waterfront\Domain\Sitebuilder\SitebuilderService;
use Waterfront\Domain\Ssl\Interfaces\Models\CertificateInstall\Parameters as InstallParameters;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Ssl\Repositories\DeploymentRepository;
use Waterfront\Domain\Ssl\ValueObjects\SslProduct;
use Waterfront\Infra\RtrClient\Exceptions\Ssl\DownloadCertificateException;
use Waterfront\Infra\RtrClient\Exceptions\Ssl\InstallCertificateException;
use Waterfront\Infra\RtrClient\Services\Ssl\CertificateDownloader;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class CertificateInstaller
{
    public function __construct(
        private readonly CsrManager $csrManager,
        private readonly CertificateManager $certificateManager,
        private readonly DeploymentRepository $sslDeploymentRepository,
        private readonly LoggerInterface $logger,
        private readonly HostingServiceFactory $hostingServiceFactory,
        private readonly HostingService $hostingService,
        private readonly SitebuilderServiceFactory $sitebuilderServiceFactory,
        private readonly CertificateDownloader $certificateDownloader,
        private readonly SitebuilderService $sitebuilderService,
    ) {
    }

    /**
     * All data should be available and valid. Installation will simply fail if something appears to be invalid.
     *
     * @throws InstallCertificateException
     * @throws JsonException
     */
    public function installForSslDeployment(SslDeployment $sslDeployment): void
    {
        $this->logger->debug(
            sprintf(
                'Installing certificate for SSL deployment #%d',
                $sslDeployment->id
            )
        );

        // Ensure we have the latest certificate in the cloud before fetching the parameters
        try {
            $this->certificateDownloader->downloadForSslDeployment($sslDeployment);
        } catch (DownloadCertificateException $exception) {
            $this->logger->warning(
                'Download certificate error before install for SSL deployment [{provisioning.id}]. Certificate install might be outdated.',
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::SSL,
                    LoggingContextKeys::PROVISIONING_ID => $sslDeployment->id,
                ]
            );
        }

        $parameters = $this->getInstallParameters($sslDeployment);
        $hostingDeployment = $this->getHostingSubscription($sslDeployment);

        if ($hostingDeployment !== null) {
            $this->installCertificateOnHosting(
                $hostingDeployment,
                $parameters,
                $sslDeployment,
            );

            $this->logger->notice(
                sprintf(
                    'Certificate for SSL deployment #%d installed on hosting: %s',
                    $sslDeployment->id,
                    $parameters->getName()
                )
            );
        }

        Artisan::call(CheckSsl::class, [
            'domain' => $parameters->getDomain(),
        ]);

        // Cleanup the temporary local certificate files, they are (now) also stored in the cloud.
        $this->csrManager->removeLocalDirectory($parameters->getDomain());
    }

    /**
     * @throws InstallCertificateException
     */
    private function getInstallParameters(SslDeployment $sslDeployment): InstallParameters
    {
        try {
            $domain = $sslDeployment->subscription->domain;
            Assert::notNull($domain, 'Provided subscription has no domain');

            /** @var ProductSpec $productSpec */
            $productSpec = $sslDeployment->subscription->product->productSpecs()
                ->where('name', 'ssl.product_id')
                ->firstOrFail();

            $sslProduct = SslProduct::fromNative($productSpec->value);
            $sslDomain = $sslProduct->isWildcardSsl() ? '*.' . $domain : $domain;

            $csr = $this->getCsr($sslDomain, $domain);
            $certificate = $this->getMainCertificate($sslDomain, $domain);
            $ca = $this->getIntermediateCertificate($sslDomain, $domain);

            return InstallParameters::create(
                [
                    'name' => $domain . '-certificate-' . CarbonImmutable::now()->toIso8601String(),
                    'domain' => $domain,
                    'csr' => $csr,
                    'pvt' => $this->csrManager->getPrivateKey($sslDomain),
                    'cert' => $certificate,
                    'ca' => $ca,
                ]
            );
        } catch (Exception $exception) {
            throw InstallCertificateException::couldNotPrepareInstallParameters(
                $sslDeployment->id,
                $exception
            );
        }
    }

    private function getIntermediateCertificate(string $sslDomain, string $domain): ?string
    {
        $certificate = $this->certificateManager->getIntermediateCertificate($sslDomain);

        $certificate ??= $this->certificateManager->getIntermediateCertificate($domain);

        return $certificate;
    }

    private function getMainCertificate(string $sslDomain, string $domain): ?string
    {
        $certificate = $this->certificateManager->getMainCertificate($sslDomain);

        $certificate ??= $this->certificateManager->getMainCertificate($domain);

        return $certificate;
    }

    /**
     * @throws FileNotFoundException
     */
    private function getCsr(string $sslDomain, string $domain): string
    {
        try {
            return $this->csrManager->getRawCsr($sslDomain);
        } catch (FileNotFoundException $exception) {
            if ($sslDomain === $domain) {
                throw $exception;
            }
        }

        return $this->csrManager->getRawCsr($domain);
    }

    /**
     * @throws InstallCertificateException
     */
    private function getHostingSubscription(SslDeployment $sslDeployment): ?HostingDeployment
    {
        try {
            $hostingDeployment = $this->sslDeploymentRepository->getRelatedHostingSubscriptionForSslDeployment($sslDeployment);

            if ($hostingDeployment === null) {
                $this->logger->debug(
                    sprintf(
                        'Certificate will not be installed, no related hosting deployment found for SSL deployment #%d',
                        $sslDeployment->id
                    )
                );
                return null;
            }

            $this->logger->debug(
                sprintf(
                    'Certificate will be installed on hosting deployment #%d for SSL deployment #%d',
                    $hostingDeployment->id,
                    $sslDeployment->id
                )
            );

            return $hostingDeployment;
        } catch (ModelNotFoundException $exception) {
            throw InstallCertificateException::couldNotFindHostingSubscriptionForCertificate(
                $sslDeployment->id,
                $exception
            );
        }
    }

    /**
     * @throws InstallCertificateException
     * @throws JsonException
     */
    private function installCertificateOnHosting(
        HostingDeployment $hostingDeployment,
        InstallParameters $installParameters,
        SslDeployment $sslDeployment
    ): void {
        if ($hostingDeployment->isDefaultHostingSubscription()) {
            $this->installCertificateOnDefaultHosting($sslDeployment, $hostingDeployment, $installParameters);
            return;
        }

        if ($hostingDeployment->subscription->product->isSitebuilderProduct()) {
            $this->installCertificateOnSitebuilderHosting($sslDeployment, $hostingDeployment);
            return;
        }

        /**
         * We currently simply accept if there is no SSL installation gateway
         * available for the related hosting type. But we will log a warning
         * in case this happens.
         */
        $this->logger->warning(
            sprintf(
                'Unknown hosting type to install the certificate on for SSL deployment #%s.',
                $sslDeployment->id
            )
        );
    }

    /**
     * @throws InstallCertificateException
     * @throws JsonException
     */
    private function installCertificateOnDefaultHosting(
        SslDeployment $sslDeployment,
        HostingDeployment $hostingDeployment,
        InstallParameters $installParameters,
    ): void {
        $this->logger->debug(
            'Trying to install a SSL certificate on a default hosting deployment',
            [
                LoggingContextKeys::SUBSCRIPTION_UUID => $hostingDeployment->subscription_uuid,
            ]
        );

        $providerSlug = $this->hostingService->getProviderSlug($hostingDeployment->subscription);
        $providerSlug = ProviderSlug::from($providerSlug ?? '');

        $result = $this->hostingServiceFactory
            ->driver($providerSlug)
            ->installCertificate(
                subscriptionUuid: $hostingDeployment->subscription_uuid,
                data: $installParameters->toArray()
            );

        if (strtolower($result) !== HostingResult::STATUS_OK) {
            throw InstallCertificateException::couldNotInstallCertificateOnHosting(
                $sslDeployment->id,
                json_encode($result, JSON_THROW_ON_ERROR)
            );
        }
    }

    /**
     * @throws InstallCertificateException
     */
    private function installCertificateOnSitebuilderHosting(
        SslDeployment $sslDeployment,
        HostingDeployment $hostingDeployment,
    ): void {
        /** @var Server $basekitServer */
        $basekitServer = $hostingDeployment->basekitServer()->firstOrFail();

        $this->logger->debug(
            'Trying to install on a sitebuilder hosting deployment',
            [
                LoggingContextKeys::SUBSCRIPTION_UUID => $sslDeployment->subscription_uuid,
                LoggingContextKeys::META => [
                    'sslDeployment' => $sslDeployment->id,
                    'basekitServer' => $basekitServer->id,
                ],
            ]
        );

        $result = $this->sitebuilderServiceFactory
            ->driver($this->sitebuilderService->getProviderSlug($hostingDeployment->subscription))
            ->setupSsl(
                sslDeployment: $sslDeployment,
                server: $basekitServer
            );

        if ($result->getStatus() !== HostingResult::STATUS_OK) {
            throw InstallCertificateException::couldNotInstallCertificateOnHosting(
                $sslDeployment->id,
                (string) $result->getErrorMessage()
            );
        }
    }
}
