<?php

declare(strict_types=1);

namespace Tests\Domain\Ssl\Services;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\SslDeploymentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Console\Commands\Sanity\CheckSsl;
use Waterfront\Domain\Hosting\Factories\HostingServiceFactory;
use Waterfront\Domain\Hosting\Interfaces\Drivers\HostingServiceInterface;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result as HostingResult;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Domain\Sitebuilder\Factories\SitebuilderServiceFactory;
use Waterfront\Domain\Sitebuilder\SitebuilderService;
use Waterfront\Domain\Ssl\Repositories\DeploymentRepository;
use Waterfront\Domain\Ssl\Services\CertificateInstaller;
use Waterfront\Domain\Ssl\Services\CertificateManager;
use Waterfront\Domain\Ssl\Services\CsrManager;
use Waterfront\Infra\RtrClient\Exceptions\Ssl\InstallCertificateException;
use Waterfront\Infra\RtrClient\Services\Ssl\CertificateDownloader;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(CertificateInstaller::class)]
class CertificateInstallerTest extends IntegrationTestCase
{
    use RefreshDatabase;

    /**
     * @throws InstallCertificateException
     * @throws JsonException
     */
    #[Test]
    public function installForSslDeploymentShouldDownloadBeforeInstalling(): void
    {
        $this->freezeTime();

        $domain = 'ssl-install.nl';
        $mockCsr = '-----BEGIN CERTIFICATE REQUEST-----mockCsr-----END CERTIFICATE REQUEST-----';
        $mockMainCertificate = '-----BEGIN CERTIFICATE-----mockMainCertificate-----END CERTIFICATE-----';
        $mockCa = '-----BEGIN CERTIFICATE-----mockCa-----END CERTIFICATE-----';
        $mockPrivateKey = '-----BEGIN PRIVATE KEY-----mockPrivateKey-----END PRIVATE KEY-----';
        $certificateName = sprintf('%s-certificate-%s', $domain, CarbonImmutable::now()->toIso8601String());

        $sslSubscription = SubscriptionFactory::new()
            ->forDomain($domain)
            ->for(CustomerFactory::new())
            ->for(
                ProductFactory::new()
                ->sslSingleDomain()
                ->has(ProductSpecFactory::new()->state(
                    ['name' => 'ssl.product_id', 'value' => true]
                ))
            )
            ->has(
                SslDeploymentFactory::new()
                ->rtrProvider()
            )
        ->createOne();

        $hostingSubscription = SubscriptionFactory::new()
            ->forDomain($domain)
            ->for(CustomerFactory::new())
            ->for(ProductFactory::new()->hostingBrons())
            ->has(HostingDeploymentFactory::new()->withPleskProvider())
            ->createOne();

        $hostingDeployment = $hostingSubscription->hostingDeployment;
        $sslDeployment = $sslSubscription->sslDeployment;
        self::assertNotNull($hostingDeployment);
        self::assertNotNull($sslDeployment);

        $logMock = self::mock(LoggerInterface::class);
        $mockDownloader = self::createMock(CertificateDownloader::class);
        $mockCsrManager = self::createMock(CsrManager::class);
        $mockCertificateManager = self::createMock(CertificateManager::class);
        $mockRepository = self::createMock(DeploymentRepository::class);
        $mockHostingServiceFactory = self::createMock(HostingServiceFactory::class);
        $mockHostingService = self::createMock(HostingServiceInterface::class);

        $installer = new CertificateInstaller(
            csrManager: $mockCsrManager,
            certificateManager: $mockCertificateManager,
            sslDeploymentRepository: $mockRepository,
            logger: $logMock,
            hostingServiceFactory: $mockHostingServiceFactory,
            hostingService: self::resolve(HostingService::class),
            sitebuilderServiceFactory: self::createStub(SitebuilderServiceFactory::class),
            certificateDownloader: $mockDownloader,
            sitebuilderService: self::resolve(SitebuilderService::class),
        );

        $logMock->shouldReceive('debug')
            ->once()
            ->with(sprintf(
                'Installing certificate for SSL deployment #%d',
                $sslDeployment->id
            ));

        $mockDownloader->expects(self::once())
            ->method('downloadForSslDeployment')
            ->with($sslDeployment);

        $mockCsrManager->expects(self::once())
            ->method('getRawCsr')
            ->with($domain)
            ->willReturn($mockCsr);

        $mockCertificateManager->expects(self::once())
            ->method('getMainCertificate')
            ->with($domain)
            ->willReturn($mockMainCertificate);

        $mockCertificateManager->expects(self::once())
            ->method('getIntermediateCertificate')
            ->with($domain)
            ->willReturn($mockCa);

        $mockCsrManager->expects(self::once())
            ->method('getPrivateKey')
            ->with($domain)
            ->willReturn($mockPrivateKey);

        $mockRepository->expects(self::once())
            ->method('getRelatedHostingSubscriptionForSslDeployment')
            ->with($sslDeployment)
            ->willReturn($hostingDeployment);

        $logMock->shouldReceive('debug')
            ->once()
            ->with(
                sprintf(
                    'Certificate will be installed on hosting deployment #%d for SSL deployment #%d',
                    $hostingDeployment->id,
                    $sslDeployment->id
                )
            );

        $logMock->shouldReceive('debug')
            ->once()
            ->with(
                'Trying to install a SSL certificate on a default hosting deployment',
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $hostingDeployment->subscription_uuid,
                ]
            );

        $mockHostingServiceFactory->expects(self::once())
            ->method('driver')
            ->willReturn($mockHostingService);

        $mockHostingService->expects(self::once())
            ->method('installCertificate')
            ->willReturn(HostingResult::STATUS_OK);

        $logMock->shouldReceive('notice')
            ->once()
            ->with(sprintf(
                'Certificate for SSL deployment #%d installed on hosting: %s',
                $sslDeployment->id,
                $certificateName
            ));

        Artisan::shouldReceive('call')
            ->with(CheckSsl::class, [
                'domain' => $domain,
            ]);

        $mockCsrManager->expects(self::once())
            ->method('removeLocalDirectory')
            ->with($domain);

        $installer->installForSslDeployment($sslDeployment);
    }
}
