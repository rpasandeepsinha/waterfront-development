<?php

declare(strict_types=1);

namespace Tests\Domain\Ssl\Job;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SslDeploymentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result as SiteBuilderResult;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Placeholder\Services\HostingPlaceholderService;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Sitebuilder\Services\BaseKitService;
use Waterfront\Domain\Ssl\Jobs\InstallCertificate;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Ssl\Services\CertificateInstaller;
use Waterfront\Domain\Ssl\Services\CertificateManager;
use Waterfront\Domain\Ssl\Services\CsrManager;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\RtrClient\Services\Ssl\CertificateDownloader;

#[CoversClass(InstallCertificate::class)]
class InstallCertificateTest extends IntegrationTestCase
{
    private const string TEST_CSR = <<<CSR
    -----BEGIN CERTIFICATE REQUEST-----
    not a real csr
    -----END CERTIFICATE REQUEST-----
    CSR;

    private const string TEST_PVT = <<<PVT
    -----BEGIN PRIVATE KEY-----
    not a real private key
    -----END PRIVATE KEY-----
    PVT;

    private const string TEST_CERT = <<<CERT_WRAP
    -----BEGIN CERTIFICATE-----
    not a real main certificate
    -----END CERTIFICATE-----
    CERT_WRAP;

    private const string TEST_INTERMEDIATE = <<<CERT_WRAP
    -----BEGIN CERTIFICATE-----
    not a real intermediate certificate
    -----END CERTIFICATE-----
    CERT_WRAP;

    private const string TEST_ROOT = <<<CERT_WRAP
    -----BEGIN CERTIFICATE-----
    not a real root certificate
    -----END CERTIFICATE-----
    CERT_WRAP;

    private Product $hostingProduct;

    private SslDeployment $sslDeployment;

    private Subscription $sslSubscription;

    private HostingDeployment $hostingDeployment;

    public function setUp(): void
    {
        parent::setUp();

        $customer = new CustomerFactory()->createOne();

        $productGroup = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::HOSTING,
            'name' => 'hosting',
        ]);
        $this->hostingProduct = new ProductFactory()->createOne([
            'product_group_id' => $productGroup->getKey(),
            'name' => $productGroup->slug,
        ]);
        $hostingSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->createOne([
                'customer_id' => $customer->getKey(),
                'domain' => 'sandwave.io',
                'product_uuid' => $this->hostingProduct->uuid,
                'technical_status' => TechnicalStatus::OK->value,
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
            ]);

        $defaultHostingProvider = new ProviderFactory()->createOne([
            'type' => ProviderType::HOSTING,
            'slug' => ProviderSlug::PLESK,
            'enabled' => true,
            'default' => true,
        ]);

        $this->hostingDeployment = new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $hostingSubscription->uuid,
            'provider_id' => $defaultHostingProvider->getKey(),
            'sitebuilder_provider_id' => null,
            'basekit_server_id' => null,
        ]);

        $this->sslSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->createOne([
                'customer_id' => $customer->getKey(),
                'product_uuid' => $this->hostingProduct->uuid,
                'domain' => 'sandwave.io',
            ]);

        $rtrProvider = ProviderFactory::new()->createOne([
            'type' => ProviderType::SSL,
            'slug' => ProviderSlug::REALTIME_REGISTER,
            'enabled' => true,
            'default' => true,
        ]);

        $this->sslDeployment = new SslDeploymentFactory()->createOne([
            'subscription_uuid' => $this->sslSubscription->uuid,
            'provider_id' => $rtrProvider->getKey(),
            'request_id' => '1234567890',
            'certificate_id' => 77_665_544,
        ]);

        $sslProduct = $this->sslDeployment->subscription->product;
        new ProductSpecFactory()->for($sslProduct)->createOne([
            'name' => 'ssl.product_id',
            'value' => 99_999_999_999,
        ]);
    }

    /**
     * @return iterable<string, array<string, string>>
     */
    public static function certificateProvider(): iterable
    {
        yield 'Normal certificate domain' => [
            'domain' => 'sandwave.io',
        ];

        yield 'Certificate with subdomain' => [
            'domain' => 'subdomain.sandwave.io',
        ];
    }

    #[DataProvider('certificateProvider')]
    #[Test]
    public function installingCertificateWillResultInCallToTheGatewayForDefaultHosting(string $domain): void
    {
        $this->sslSubscription->domain = $domain;
        $this->sslSubscription->save();

        $this->sslDeployment->refresh();

        $certificateManager = self::resolve(CertificateManager::class);
        $certificateManager->saveMainCertificate($domain, self::TEST_CERT);
        $certificateManager->saveIntermediateCertificate($domain, self::TEST_INTERMEDIATE);
        $certificateManager->saveRootCertificate($domain, self::TEST_ROOT);

        $csrManager = self::resolve(CsrManager::class);
        $csrManager->saveCsr($domain, self::TEST_CSR);
        $csrManager->updatePrivateKey($domain, self::TEST_PVT);

        // Setup subscription as default hosting
        $hostingProvider = new ProviderFactory()->createOne([
            'type' => ProviderType::HOSTING,
            'slug' => ProviderSlug::PLACEHOLDER,
            'enabled' => true,
            'default' => true,
        ]);
        $this->hostingDeployment->provider_id = $hostingProvider->id;
        $this->hostingDeployment->sitebuilder_provider_id = null;
        $this->hostingDeployment->save();

        // Mock the gateway that will install the certificate to the correct hosting platform
        $placeholderServiceMock = self::createMock(HostingPlaceholderService::class);
        $placeholderServiceMock
            ->expects(self::once())
            ->method('installCertificate')
            ->with($this->hostingDeployment->subscription_uuid, self::callback(
                function (array $data) use ($domain) {
                    self::assertSame($domain, $data['domain']);
                    self::assertSame(self::TEST_CSR, $data['csr']);
                    self::assertSame(self::TEST_PVT, $data['pvt']);
                    self::assertSame(self::TEST_CERT, $data['cert']);
                    self::assertSame(self::TEST_INTERMEDIATE, $data['ca']);

                    return true;
                },
            ))
            ->willReturn('ok');

        $this->instance(HostingPlaceholderService::class, $placeholderServiceMock);

        $this->app->bind(CertificateDownloader::class, fn () => self::createStub(CertificateDownloader::class));

        $installCertificateJob = new InstallCertificate($this->sslDeployment);
        $installCertificateJob->handle(
            self::resolve(CertificateInstaller::class),
        );
    }

    #[Test]
    public function installingCertificateWillResultInCallToTheGatewayForSiteBuilder(): void
    {
        $certificateManager = self::resolve(CertificateManager::class);
        $certificateManager->saveMainCertificate('sandwave.io', self::TEST_CERT);
        $certificateManager->saveIntermediateCertificate('sandwave.io', self::TEST_INTERMEDIATE);
        $certificateManager->saveRootCertificate('sandwave.io', self::TEST_ROOT);

        $csrManager = self::resolve(CsrManager::class);
        $csrManager->saveCsr('sandwave.io', self::TEST_CSR);
        $csrManager->updatePrivateKey('sandwave.io', self::TEST_PVT);

        $sitebuilderProvider = ProviderFactory::new()->createOne([
            'type' => ProviderType::SITEBUILDER,
            'slug' => ProviderSlug::BASEKIT,
            'enabled' => true,
            'default' => true,
        ]);
        $server = new ServerFactory()->createOne();
        $this->hostingDeployment->provider_id = null;
        $this->hostingDeployment->sitebuilder_provider_id = $sitebuilderProvider->id;
        $this->hostingDeployment->basekit_server_id = $server->id;
        $this->hostingDeployment->save();

        $this->hostingProduct->name = 'sitebuilder';
        $this->hostingProduct->slug = 'sitebuilder';
        $this->hostingProduct->save();

        // Mock the gateway that will install the certificate to the correct hosting platform
        $baseKitServiceMock = self::createMock(BaseKitService::class);
        $baseKitServiceMock
            ->expects(self::once())
            ->method('setupSsl')
            ->with(self::callback(
                fn (SslDeployment $sslDeployment): bool => $sslDeployment->getKey() === $this->sslDeployment->getKey(),
            ), self::callback(
                fn (Server $server): bool => $server->getKey() === $this->hostingDeployment->basekitServer?->getKey(),
            ))
            ->willReturnCallback(function () {
                $result = new SiteBuilderResult();
                $result->setStatus(SiteBuilderResult::STATUS_OK);

                return $result;
            });

        $this->instance(BaseKitService::class, $baseKitServiceMock);

        $this->app->bind(CertificateDownloader::class, fn () => self::createStub(CertificateDownloader::class));

        $installCertificateJob = new InstallCertificate($this->sslDeployment);
        $installCertificateJob->handle(
            self::resolve(CertificateInstaller::class),
        );
    }
}
