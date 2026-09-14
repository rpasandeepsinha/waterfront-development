<?php

declare(strict_types=1);

namespace Tests\Domain\Sitebuilder;

use Exception;
use Illuminate\Contracts\Events\Dispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SandwaveIo\BaseKit\Api\Interfaces\SslApiInterface;
use SandwaveIo\BaseKit\BaseKit;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SslDeploymentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\Services\DnsZoneService;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\ProviderRepository;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Sitebuilder\Results\SitebuilderResult;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Sitebuilder\Exceptions\DomainNotFoundException;
use Waterfront\Domain\Sitebuilder\Exceptions\SitebuilderException;
use Waterfront\Domain\Sitebuilder\Services\BasekitFactoryInterface;
use Waterfront\Domain\Sitebuilder\Services\BaseKitService;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Ssl\Services\CertificateManager;
use Waterfront\Domain\Ssl\Services\CsrManager;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\GatewayHelper;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;

#[CoversClass(BaseKitService::class)]
class SitebuilderSslTest extends IntegrationTestCase
{
    #[Test]
    public function installSsl(): void
    {
        $domain = 'openprovider.nl';
        $rootCertificate = require __DIR__ . '/data/root_certificate.php';
        $mainCertificate = require __DIR__ . '/data/main_certificate.php';
        $intermediateCertificate = require __DIR__ . '/data/intermediate_certificate.php';

        [$sslDeployment, $server] = $this->createSslProduct($domain);

        $baseKit = new BaseKit('watbullshit', 'watbullshit', 'watbullshitdatmaaktallemaatnietuit-JesseKramer');

        $sslApi = self::createMock(SslApiInterface::class);
        $sslApi->expects(self::once())->method('addSsl');

        $baseKit->sslApi = $sslApi;

        $certificateManager = self::resolve(CertificateManager::class);
        $csrManagerMock = self::createStub(CsrManager::class);
        $csrManagerMock->method('getPrivateKey')->willReturn('A Key');

        $certificateManager->saveRootCertificate($domain, $rootCertificate);
        $certificateManager->saveMainCertificate($domain, $mainCertificate);
        $certificateManager->saveIntermediateCertificate($domain, $intermediateCertificate);

        $factoryMock = self::createStub(BasekitFactoryInterface::class);
        $factoryMock->method('make')->willReturn($baseKit);
        $this->app->bind(BasekitFactoryInterface::class, fn (): BasekitFactoryInterface => $factoryMock);
        $this->app->bind(CsrManager::class, fn (): CsrManager => $csrManagerMock);

        $sitebuilderService = self::resolve(BaseKitService::class);

        $result = $sitebuilderService->setupSsl($sslDeployment, $server);

        self::assertSame(Result::STATUS_OK, $result->getStatus());
    }

    #[Test]
    public function installSslUsingGateway(): void
    {
        $domain = 'openprovider.nl';
        $rootCertificate = require __DIR__ . '/data/root_certificate.php';
        $mainCertificate = require __DIR__ . '/data/main_certificate.php';
        $intermediateCertificate = require __DIR__ . '/data/intermediate_certificate.php';

        [$sslDeployment, $server] = $this->createSslProduct($domain);

        $certificateManager = self::resolve(CertificateManager::class);
        $csrManagerMock = self::createStub(CsrManager::class);
        $csrManagerMock->method('getPrivateKey')->willReturn('A Key');

        $certificateManager->saveRootCertificate($domain, $rootCertificate);
        $certificateManager->saveMainCertificate($domain, $mainCertificate);
        $certificateManager->saveIntermediateCertificate($domain, $intermediateCertificate);

        $mockGatewayHelper = self::createMock(GatewayHelper::class);
        $mockProvisionGateway = self::createMock(ProvisionGateway::class);
        $mockProvisionResult = new SitebuilderResult(
            provisionData: self::createStub(ProvisionRequestInterface::class),
            provisionStatus: ProvisionStatus::SUCCESS,
        );

        $mockGatewayHelper->expects(self::once())->method('hasSitebuilderDeploymentUsingGateway')->willReturn(true);

        $mockProvisionGateway->expects(self::once())->method('request')->willReturn($mockProvisionResult);

        $sitebuilderService = new BaseKitService(
            certificateManager: $certificateManager,
            csrManager: $csrManagerMock,
            dnsZoneService: self::resolve(DnsZoneService::class),
            basekitFactory: self::resolve(BasekitFactoryInterface::class),
            mailer: self::resolve(MailerInterface::class),
            eventDispatcher: self::resolve(Dispatcher::class),
            providerRepository: self::resolve(ProviderRepository::class),
            provisionGateway: $mockProvisionGateway,
            logger: self::resolve(LoggerInterface::class),
            gatewayHelper: $mockGatewayHelper,
            subscriptionRepository: self::resolve(SubscriptionRepository::class),
        );

        $result = $sitebuilderService->setupSsl($sslDeployment, $server);

        $sslSubscription = $sslDeployment->subscription->fresh();

        self::assertNotNull($sslSubscription);
        self::assertEquals(TechnicalStatus::OK->value, $sslSubscription->technical_status);
        self::assertSame(Result::STATUS_OK, $result->getStatus());
    }

    #[Test]
    public function installSslUsingGatewayFailed(): void
    {
        $domain = 'openprovider.nl';
        $rootCertificate = require __DIR__ . '/data/root_certificate.php';
        $mainCertificate = require __DIR__ . '/data/main_certificate.php';
        $intermediateCertificate = require __DIR__ . '/data/intermediate_certificate.php';

        [$sslDeployment, $server] = $this->createSslProduct($domain);

        $certificateManager = self::resolve(CertificateManager::class);
        $csrManagerMock = self::createStub(CsrManager::class);
        $csrManagerMock->method('getPrivateKey')->willReturn('A Key');

        $certificateManager->saveRootCertificate($domain, $rootCertificate);
        $certificateManager->saveMainCertificate($domain, $mainCertificate);
        $certificateManager->saveIntermediateCertificate($domain, $intermediateCertificate);

        $mockGatewayHelper = self::createMock(GatewayHelper::class);
        $mockProvisionGateway = self::createMock(ProvisionGateway::class);
        $exceptionMessage = 'Oh noes!';
        $mockException = new Exception($exceptionMessage);
        $mockProvisionResult = new SitebuilderResult(
            provisionData: self::createStub(ProvisionRequestInterface::class),
            provisionStatus: ProvisionStatus::FAILED,
            exception: $mockException,
        );

        $mockGatewayHelper->expects(self::once())->method('hasSitebuilderDeploymentUsingGateway')->willReturn(true);

        $mockProvisionGateway->expects(self::once())->method('request')->willReturn($mockProvisionResult);

        $sitebuilderService = new BaseKitService(
            certificateManager: $certificateManager,
            csrManager: $csrManagerMock,
            dnsZoneService: self::resolve(DnsZoneService::class),
            basekitFactory: self::resolve(BasekitFactoryInterface::class),
            mailer: self::resolve(MailerInterface::class),
            eventDispatcher: self::resolve(Dispatcher::class),
            providerRepository: self::resolve(ProviderRepository::class),
            provisionGateway: $mockProvisionGateway,
            logger: self::resolve(LoggerInterface::class),
            gatewayHelper: $mockGatewayHelper,
            subscriptionRepository: self::resolve(SubscriptionRepository::class),
        );

        $result = $sitebuilderService->setupSsl($sslDeployment, $server);

        $sslSubscription = $sslDeployment->subscription->fresh();

        self::assertNotNull($sslSubscription);

        $expectedErrorMessage = sprintf(
            'Failed to setup SSL for {%s}. Api returned {%s}',
            $sslSubscription->domain,
            $exceptionMessage,
        );

        self::assertEquals(TechnicalStatus::FAILED->value, $sslSubscription->technical_status);
        self::assertSame(Result::STATUS_ERROR, $result->getStatus());
        self::assertSame($expectedErrorMessage, $result->getErrorMessage());
    }

    #[Test]
    public function installSslApiFail(): void
    {
        $domain = 'openprovider.nl';
        $rootCertificate = require __DIR__ . '/data/root_certificate.php';
        $mainCertificate = require __DIR__ . '/data/main_certificate.php';
        $intermediateCertificate = require __DIR__ . '/data/intermediate_certificate.php';

        new ServerFactory()->make([
            'type' => ServerType::SITEBUILDER,
            'username' => 'test',
            'password' => 'test',
            'hostname' => 'example.com',
        ]);

        [$sslDeployment, $server] = $this->createSslProduct($domain);

        $baseKit = new BaseKit('watbullshit', 'watbullshit', 'watbullshitdatmaaktallemaatnietuit-JesseKramer');

        $sslApi = self::createMock(SslApiInterface::class);
        $sslApi->expects(self::once())->method('addSsl')->willThrowException(new SitebuilderException());

        $csrManagerMock = self::createStub(CsrManager::class);
        $csrManagerMock->method('getPrivateKey')->willReturn('A Key');

        $baseKit->sslApi = $sslApi;

        $certificateManager = self::resolve(CertificateManager::class);

        $certificateManager->saveRootCertificate($domain, $rootCertificate);
        $certificateManager->saveMainCertificate($domain, $mainCertificate);
        $certificateManager->saveIntermediateCertificate($domain, $intermediateCertificate);

        $factoryMock = self::createStub(BasekitFactoryInterface::class);
        $factoryMock->method('make')->willReturn($baseKit);

        $this->app->bind(BasekitFactoryInterface::class, fn (): BasekitFactoryInterface => $factoryMock);
        $this->app->bind(CsrManager::class, fn (): CsrManager => $csrManagerMock);

        $baseKitService = self::resolve(BaseKitService::class);

        $result = $baseKitService->setupSsl($sslDeployment, $server);

        self::assertSame(Result::STATUS_ERROR, $result->getStatus());
    }

    #[Test]
    public function installSSlFailedNoMainCertificate(): void
    {
        $domain = 'nomaincertificate.nl';
        $certificate = require __DIR__ . '/data/root_certificate.php';

        [$sslDeployment, $server] = $this->createSslProduct($domain);

        $this->expectException(RuntimeException::class);

        $baseKitService = self::resolve(BaseKitService::class);
        $certificateManager = self::resolve(CertificateManager::class);

        $certificateManager->saveRootCertificate($domain, $certificate);
        $baseKitService->setupSsl($sslDeployment, $server);
    }

    #[Test]
    public function installSSlFailedNoRootCertificate(): void
    {
        $domain = 'norootcertificate.nl';

        [$sslDeployment, $server] = $this->createSslProduct($domain);

        $this->expectException(RuntimeException::class);

        $baseKitService = self::resolve(BaseKitService::class);

        $baseKitService->setupSsl($sslDeployment, $server);
    }

    #[Test]
    public function installSSlFailedNoDomainOnSslDeployment(): void
    {
        [$sslDeployment, $server] = $this->createSslProduct();

        $this->expectException(DomainNotFoundException::class);

        $baseKitService = self::resolve(BaseKitService::class);

        $baseKitService->setupSsl($sslDeployment, $server);
    }

    /**
     * @return array{0: SslDeployment, 1: Server}
     */
    private function createSslProduct(?string $domain = null): array
    {
        $customer = new CustomerFactory()->createOne();

        $sslProvider = ProviderFactory::new()->createOne([
            'type' => ProviderType::SSL,
            'slug' => ProviderSlug::OPEN_PROVIDER,
            'enabled' => true,
            'default' => true,
        ]);

        $sslProductGroup = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::SSL,
            'name' => ProductGroupType::SSL,
        ]);
        $sslProduct = new ProductFactory()->createOne([
            'product_group_id' => $sslProductGroup->id,
            'name' => 'single-domain',
            'slug' => 'ssl_single-domain',
        ]);
        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->createOne([
                'product_uuid' => $sslProduct->uuid,
                'customer_id' => $customer->id,
                'domain' => $domain,
            ]);

        $sslDeployment = new SslDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'provider_id' => $sslProvider->id,
        ]);

        $productHosting = new ProductFactory()
            ->siteBuilder()
            ->for(new ProductGroupFactory()->hosting())
            ->createOne();
        new SubscriptionFactory()
            ->for($customer)
            ->for($productHosting)
            ->createOne(['domain' => $domain]);

        $customer->load('address');

        $server = new ServerFactory()->makeOne([
            'type' => ServerType::SITEBUILDER,
            'username' => 'test',
            'password' => 'test',
            'hostname' => 'example.com',
        ]);

        return [
            $sslDeployment,
            $server,
        ];
    }
}
