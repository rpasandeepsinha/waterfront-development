<?php

declare(strict_types=1);

namespace Tests\Domain\Hosting\Services;

use Illuminate\Bus\Dispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Hosting\DTO\DnsRecord;
use Waterfront\Domain\Hosting\DTO\UserStatistics;
use Waterfront\Domain\Hosting\Factories\HostingServiceFactory;
use Waterfront\Domain\Hosting\Interfaces\Drivers\HostingServiceInterface;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Parameters as HostingParameters;
use Waterfront\Domain\Hosting\Repositories\HostingDeploymentRepository;
use Waterfront\Domain\Hosting\Services\HostingDeploymentService;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Domain\MailManagement\Services\MailManagementService;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Repositories\ProductSpecRepository;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(HostingService::class)]
class HostingServiceTest extends IntegrationTestCase
{
    public const string DOMAIN = 'sandwave.io';

    #[Test]
    public function createHosting(): void
    {
        $testDomain = 'test-hosting-create.nl';

        $mockHostingServiceFactory = self::createMock(HostingServiceFactory::class);
        $mockHostingService = self::createMock(HostingServiceInterface::class);
        $mockLogger = self::createMock(LoggerInterface::class);
        $mockBusDispatcher = self::createMock(Dispatcher::class);
        $mockProductSpecRepository = self::createStub(ProductSpecRepository::class);
        $mockSubscriptionRepository = self::createMock(SubscriptionRepository::class);

        $server = ServerFactory::new()->plesk()->createOne();
        $customer = new CustomerFactory()->createOne();
        $hostingProduct = new ProductFactory()->hostingBrons()->createOne();

        $hostingSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($hostingProduct)
            ->technicalStatus(TechnicalStatus::PENDING->value)
            ->createOne(['domain' => $testDomain]);

        $mockHostingServiceFactory->expects(self::exactly(2))->method('defaultDriver')->willReturn($mockHostingService);

        $mockHostingService->expects(self::once())->method('findServer')->with()->willReturn($server);

        $mockLogger
            ->expects(self::once())
            ->method('info')
            ->with('Create hosting', [
                LoggingContextKeys::DOMAIN_NAME => $testDomain,
                LoggingContextKeys::SUBSCRIPTION_UUID => $hostingSubscription->uuid,
                LoggingContextKeys::SERVER_ID => null,
                LoggingContextKeys::META => [
                    'contact email' => $customer->email,
                    'contact person' => $customer->name,
                    'email' => $customer->email,
                ],
            ]);

        $mockSubscriptionRepository->expects(self::once())->method('getByUuid')->willReturn($hostingSubscription);

        $mockHostingService
            ->expects(self::once())
            ->method('create')
            ->with(
                $customer->name,
                $customer->email,
                $customer->email,
                $customer->uuid,
                $hostingSubscription->uuid,
                $hostingProduct->productSpecs->toArray(),
                $server,
                null,
                $testDomain,
            )
            ->willReturn([
                'result' => TechnicalStatus::OK->value,
                'domain' => $testDomain,
            ]);

        $mockBusDispatcher->expects(self::never())->method('dispatch');

        $hostingService = new HostingService(
            $mockHostingServiceFactory,
            $mockLogger,
            $mockBusDispatcher,
            $mockProductSpecRepository,
            $mockSubscriptionRepository,
            self::resolve(HostingDeploymentRepository::class),
            self::resolve(MailManagementService::class),
            self::resolve(HostingDeploymentService::class),
        );

        $hostingService->create(
            subscriptionUuid: $hostingSubscription->uuid,
            contactPersonName: $customer->name,
            contactEmail: $customer->email,
            product: $hostingProduct,
            customer: $customer,
            serverId: null,
            domain: $testDomain,
        );

        self::assertSame(TechnicalStatus::OK->value, $hostingSubscription->refresh()->technical_status);
        self::assertSame($testDomain, $hostingSubscription->domain);
    }

    #[Test]
    public function createHostingWithWpToolkit(): void
    {
        $testDomain = 'test-hosting-create.nl';

        $mockHostingServiceFactory = self::createMock(HostingServiceFactory::class);
        $mockHostingService = self::createMock(HostingServiceInterface::class);
        $mockLogger = self::createMock(LoggerInterface::class);
        $mockBusDispatcher = self::createMock(Dispatcher::class);
        $mockProductSpecRepository = self::resolve(ProductSpecRepository::class);
        $mockSubscriptionRepository = self::createMock(SubscriptionRepository::class);

        $server = ServerFactory::new()->plesk()->createOne();

        $customer = new CustomerFactory()->createOne();

        $hostingProduct = new ProductFactory()
            ->hostingBrons()
            ->has(
                new ProductSpecFactory()->state([
                    'name' => ProductSpecName::WAIT_FOR_WP_TOOLKIT->value,
                    'value' => 1,
                ]),
            )
            ->createOne();

        $hostingSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($hostingProduct)
            ->technicalStatus(TechnicalStatus::PENDING->value)
            ->createOne(['domain' => $testDomain]);

        $mockHostingServiceFactory->expects(self::exactly(2))->method('defaultDriver')->willReturn($mockHostingService);

        $mockHostingService->expects(self::once())->method('findServer')->with()->willReturn($server);

        $mockLogger
            ->expects(self::once())
            ->method('info')
            ->with('Create hosting', [
                LoggingContextKeys::DOMAIN_NAME => $testDomain,
                LoggingContextKeys::SUBSCRIPTION_UUID => $hostingSubscription->uuid,
                LoggingContextKeys::SERVER_ID => null,
                LoggingContextKeys::META => [
                    'contact email' => $customer->email,
                    'contact person' => $customer->name,
                    'email' => $customer->email,
                ],
            ]);

        $mockSubscriptionRepository->expects(self::once())->method('getByUuid')->willReturn($hostingSubscription);

        $mockHostingService
            ->expects(self::once())
            ->method('create')
            ->with(
                $customer->name,
                $customer->email,
                $customer->email,
                $customer->uuid,
                $hostingSubscription->uuid,
                $hostingProduct->productSpecs->toArray(),
                $server,
                null,
                $testDomain,
            )
            ->willReturn([
                'result' => TechnicalStatus::OK->value,
                'domain' => $testDomain,
            ]);

        $mockBusDispatcher->expects(self::once())->method('dispatch');

        $hostingService = new HostingService(
            $mockHostingServiceFactory,
            $mockLogger,
            $mockBusDispatcher,
            $mockProductSpecRepository,
            $mockSubscriptionRepository,
            self::resolve(HostingDeploymentRepository::class),
            self::resolve(MailManagementService::class),
            self::resolve(HostingDeploymentService::class),
        );

        $hostingService->create(
            subscriptionUuid: $hostingSubscription->uuid,
            contactPersonName: $customer->name,
            contactEmail: $customer->email,
            product: $hostingProduct,
            customer: $customer,
            serverId: null,
            domain: $testDomain,
        );

        self::assertSame(TechnicalStatus::PENDING->value, $hostingSubscription->refresh()->technical_status);
        self::assertSame($testDomain, $hostingSubscription->domain);
    }

    #[Test]
    public function getCustomerDomains(): void
    {
        $hostingDeployment = new HostingDeploymentFactory()->createOne();
        $driver = ProviderSlug::PLESK;
        $hostingService = new HostingService(
            $hostingServiceFactory = self::createMock(HostingServiceFactory::class),
            self::resolve(LoggerInterface::class),
            self::createStub(Dispatcher::class),
            self::createStub(ProductSpecRepository::class),
            self::createStub(SubscriptionRepository::class),
            self::resolve(HostingDeploymentRepository::class),
            self::resolve(MailManagementService::class),
            self::resolve(HostingDeploymentService::class),
        );

        $hostingServiceFactory
            ->expects($this->once())
            ->method('driver')
            ->with($driver)
            ->willReturn($hostingServiceMock = self::createMock(HostingServiceInterface::class));

        $hostingServiceMock->expects(self::once())->method('getCustomerDomainsForDkim')->with($hostingDeployment);

        $hostingService->getCustomerDomains($driver->value, $hostingDeployment);
    }

    #[Test]
    public function isDkimEnabled(): void
    {
        $hostingDeployment = new HostingDeploymentFactory()->createOne();
        $driver = ProviderSlug::PLESK;
        $hostingService = new HostingService(
            $hostingServiceFactory = self::createMock(HostingServiceFactory::class),
            self::resolve(LoggerInterface::class),
            self::createStub(Dispatcher::class),
            self::createStub(ProductSpecRepository::class),
            self::createStub(SubscriptionRepository::class),
            self::resolve(HostingDeploymentRepository::class),
            self::resolve(MailManagementService::class),
            self::resolve(HostingDeploymentService::class),
        );

        $hostingServiceFactory
            ->expects($this->once())
            ->method('driver')
            ->with($driver)
            ->willReturn($hostingServiceMock = self::createMock(HostingServiceInterface::class));

        $hostingServiceMock
            ->expects(self::once())
            ->method('isDkimEnabled')
            ->with($hostingDeployment, self::DOMAIN)
            ->willReturn(true);

        $isEnabled = $hostingService->isDkimEnabled($driver->value, $hostingDeployment, self::DOMAIN);
        self::assertTrue($isEnabled);
    }

    #[Test]
    public function isDkimDisabled(): void
    {
        $hostingDeployment = new HostingDeploymentFactory()->createOne();
        $driver = ProviderSlug::PLESK;
        $hostingService = new HostingService(
            $hostingServiceFactory = self::createMock(HostingServiceFactory::class),
            self::resolve(LoggerInterface::class),
            self::createStub(Dispatcher::class),
            self::createStub(ProductSpecRepository::class),
            self::createStub(SubscriptionRepository::class),
            self::resolve(HostingDeploymentRepository::class),
            self::resolve(MailManagementService::class),
            self::resolve(HostingDeploymentService::class),
        );

        $hostingServiceFactory
            ->expects($this->once())
            ->method('driver')
            ->with($driver)
            ->willReturn($hostingServiceMock = self::createMock(HostingServiceInterface::class));

        $hostingServiceMock
            ->expects(self::once())
            ->method('isDkimEnabled')
            ->with($hostingDeployment, self::DOMAIN)
            ->willReturn(false);

        $isEnabled = $hostingService->isDkimEnabled($driver->value, $hostingDeployment, self::DOMAIN);
        self::assertFalse($isEnabled);
    }

    #[Test]
    public function setDkim(): void
    {
        $hostingDeployment = new HostingDeploymentFactory()->createOne();
        $driver = ProviderSlug::PLESK;
        $hostingService = new HostingService(
            $hostingServiceFactory = self::createMock(HostingServiceFactory::class),
            self::resolve(LoggerInterface::class),
            self::createStub(Dispatcher::class),
            self::createStub(ProductSpecRepository::class),
            self::createStub(SubscriptionRepository::class),
            self::resolve(HostingDeploymentRepository::class),
            self::resolve(MailManagementService::class),
            self::resolve(HostingDeploymentService::class),
        );

        $hostingServiceFactory
            ->expects($this->once())
            ->method('driver')
            ->with($driver)
            ->willReturn($hostingServiceMock = self::createMock(HostingServiceInterface::class));

        $hostingServiceMock->expects(self::once())->method('setDkim')->with($hostingDeployment, self::DOMAIN, true);

        $hostingService->setDkim($driver->value, $hostingDeployment, self::DOMAIN, true);
    }

    #[Test]
    public function getDkimRecord(): void
    {
        $hostingDeployment = new HostingDeploymentFactory()->createOne();
        $driver = ProviderSlug::PLESK;
        $hostingService = new HostingService(
            $hostingServiceFactory = self::createMock(HostingServiceFactory::class),
            self::resolve(LoggerInterface::class),
            self::createStub(Dispatcher::class),
            self::createStub(ProductSpecRepository::class),
            self::createStub(SubscriptionRepository::class),
            self::resolve(HostingDeploymentRepository::class),
            self::resolve(MailManagementService::class),
            self::resolve(HostingDeploymentService::class),
        );

        $hostingServiceFactory
            ->expects($this->once())
            ->method('driver')
            ->with($driver)
            ->willReturn($hostingServiceMock = self::createMock(HostingServiceInterface::class));

        $hostingServiceMock
            ->expects(self::once())
            ->method('getDkimRecord')
            ->with($hostingDeployment, self::DOMAIN)
            ->willReturn(
                $dkimRecordMock = new DnsRecord('TXT', '_domainkey2.sandwave.io.', 'v=DKIM1; p=differentDKIM'),
            );

        $dkimRecord = $hostingService->getDkimRecord($driver->value, $hostingDeployment, self::DOMAIN);
        self::assertInstanceOf(DnsRecord::class, $dkimRecord);
        self::assertSame($dkimRecordMock, $dkimRecord);
    }

    #[Test]
    public function getDkimRecordNotFound(): void
    {
        $hostingDeployment = new HostingDeploymentFactory()->createOne();
        $driver = ProviderSlug::PLESK;
        $hostingService = new HostingService(
            $hostingServiceFactory = self::createMock(HostingServiceFactory::class),
            self::resolve(LoggerInterface::class),
            self::createStub(Dispatcher::class),
            self::createStub(ProductSpecRepository::class),
            self::createStub(SubscriptionRepository::class),
            self::resolve(HostingDeploymentRepository::class),
            self::resolve(MailManagementService::class),
            self::resolve(HostingDeploymentService::class),
        );

        $hostingServiceFactory
            ->expects($this->once())
            ->method('driver')
            ->with($driver)
            ->willReturn($hostingServiceMock = self::createMock(HostingServiceInterface::class));

        $hostingServiceMock
            ->expects(self::once())
            ->method('getDkimRecord')
            ->with($hostingDeployment, self::DOMAIN)
            ->willReturn(null);

        $dkimRecord = $hostingService->getDkimRecord($driver->value, $hostingDeployment, self::DOMAIN);
        self::assertNull($dkimRecord);
    }

    #[Test]
    public function getUserStats(): void
    {
        $customer = new CustomerFactory()->createOne();
        $subscription = new SubscriptionFactory()
            ->for($customer)
            ->for(new ProductFactory()->for(new ProductGroupFactory()->hosting()->createOne()))
            ->createOne();

        $hostingProvider = new ProviderFactory()->createOne([
            'type' => ProviderType::HOSTING,
            'slug' => ProviderSlug::DIRECTADMIN,
            'enabled' => true,
            'default' => true,
        ]);
        $hostingDeployment = new HostingDeploymentFactory()->for($subscription, 'subscription')->createOne([
            'provider_id' => $hostingProvider->id,
        ]);
        $driver = ProviderSlug::PLESK;
        $hostingDeploymentRepository = self::resolve(HostingDeploymentRepository::class);
        $hostingDeploymentService = self::resolve(HostingDeploymentService::class);
        $hostingService = new HostingService(
            $hostingServiceFactory = self::createMock(HostingServiceFactory::class),
            self::resolve(LoggerInterface::class),
            self::createStub(Dispatcher::class),
            self::createStub(ProductSpecRepository::class),
            self::createStub(SubscriptionRepository::class),
            $hostingDeploymentRepository,
            self::resolve(MailManagementService::class),
            $hostingDeploymentService,
        );

        $hostingServiceFactory
            ->expects($this->once())
            ->method('driver')
            ->with($driver)
            ->willReturn($hostingServiceMock = self::createMock(HostingServiceInterface::class));

        $userStatistics = self::createStub(UserStatistics::class);

        $server = $hostingDeployment->subscription->product->isMailOnlyServer()
            ? $hostingDeployment->mailOnlyServer
            : $hostingDeployment->server;

        $parameters = new HostingParameters();
        $parameters->setUsername($hostingDeploymentService->getUsername($hostingDeployment) ?? '');
        $parameters->setDomain($subscription->domain ?? '');
        $parameters->setIpv4Address($server?->getIpv4() ?? '');
        $parameters->setServer($server);

        $hostingServiceMock
            ->expects(self::once())
            ->method('getUserStats')
            ->with($parameters)
            ->willReturn($userStatistics);

        $userStats = $hostingService->getUserStats($subscription, $driver);
        self::assertSame($userStatistics, $userStats);
    }
}
