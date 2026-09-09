<?php

declare(strict_types=1);

namespace Tests\Domain\Domains;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DnsDeploymentFactory;
use Tests\Factories\DomainContactFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\DomainProviderBusinessUnitFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\RtrProviderCredentialsFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\Actions\AssignNameserversToDomainAction;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\DNS\Factories\NameserverAssignerFactory;
use Waterfront\Domain\DNS\Interfaces\NameserverAssignerInterface;
use Waterfront\Domain\DNS\Models\DnsDeployment;
use Waterfront\Domain\DNS\Models\DnsNameserver;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\DNS\Repository\DnsProductSpecRepository;
use Waterfront\Domain\DNS\Services\DnsExternalNameserverAssigner;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\DTO\Handles;
use Waterfront\Domain\Domains\DTO\RegistrationResult;
use Waterfront\Domain\Domains\DTO\RetrieveCustomerResponse;
use Waterfront\Domain\Domains\DTO\TransferResult;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Domains\Factories\DomainServiceFactory;
use Waterfront\Domain\Domains\Interfaces\DomainDriverInterface;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Domains\Models\DomainProviderBusinessUnit;
use Waterfront\Domain\Domains\Repositories\DomainDeploymentRepository;
use Waterfront\Domain\Domains\Repositories\DomainProviderBusinessUnitRepository;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Provision\DNS\Enums\NameserverType;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Infra\RtrClient\Services\RtrService;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(DomainService::class)]
class DomainServiceTest extends IntegrationTestCase
{
    public const string DOMAIN = 'test-domain.nl';

    public Product $domainProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $domainGroup = new ProductGroupFactory()->extension()->createOne();
        $this->domainProduct = new ProductFactory()->nlDomain()->for($domainGroup)->createOne();

        new SubscriptionFactory()
            ->for((new CustomerFactory()))
            ->forDomain(self::DOMAIN)
            ->for($this->domainProduct)
            ->has(
                new DomainDeploymentFactory()
                ->for(new ProviderFactory()->domainOpenProvider()->createOne())
            )
            ->createOne();
    }

    #[Test]
    public function minimalRegister(): void
    {
        $testCustomerId = 1337;
        $domainSubscription = new SubscriptionFactory()
            ->for(new CustomerFactory()->state(['id' => $testCustomerId]))
            ->forDomain(self::DOMAIN)
            ->has(
                new DomainDeploymentFactory()
                    ->withRtrProvider()
                    ->for(new DomainContactFactory()->state(['customer_id' => $testCustomerId]), 'contactOwner')
            )
            ->for($this->domainProduct)
            ->createOne();

        $testHandle = '123-TestKees-AAAAAAAAAAAAAAAAAAAAAAAAAAA';
        $mockLogger = self::createMock(LoggerInterface::class);
        $mockServiceFactory = self::createMock(DomainServiceFactory::class);
        $mockRtrService = self::createMock(RtrService::class);

        $domainService = new DomainService(
            self::resolve(NameserverAssignerFactory::class),
            self::resolve(AssignNameserversToDomainAction::class),
            $mockServiceFactory,
            $mockLogger,
            self::resolve(DnsDeploymentRepository::class),
            self::resolve(DnsProductSpecRepository::class),
            self::resolve(DnsService::class),
            self::resolve(DomainDeploymentRepository::class),
            self::resolve(DomainProviderBusinessUnitRepository::class),
        );

        $mockRtrService->expects(self::once())
            ->method('createContact')
            ->willReturn($testHandle);

        $mockRtrService->expects(self::once())
            ->method('minimalRegister')
            ->with($domainSubscription->domainDeployment, self::callback(fn (Handles $handle) => $handle->getOwnerHandle() === $testHandle))
            ->willReturn(new RegistrationResult(DomainStatus::PENDING));

        $mockServiceFactory->expects(self::exactly(2))
            ->method('driver')
            ->with(ProviderSlug::REALTIME_REGISTER)
            ->willReturn($mockRtrService);

        $mockLogger->expects(self::once())
            ->method('info')
        ->with(
            'Starting minimal register for domain {domain.name}',
            [
                LoggingContextKeys::DOMAIN_NAME => $domainSubscription->domain,
                LoggingContextKeys::SUBSCRIPTION_UUID => $domainSubscription->uuid,
                LoggingContextKeys::SUBSCRIPTION_ID => $domainSubscription->id,
            ]
        );

        self::assertNotNull($domainSubscription->domainDeployment);

        $result = $domainService->minimalRegister($domainSubscription->domainDeployment);
        self::assertSame(DomainStatus::PENDING, $result->getStatus());
    }

    #[Test]
    public function minimalTransfer(): void
    {
        $testCustomerId = 1337;
        $domainSubscription = new SubscriptionFactory()
            ->for(new CustomerFactory()->state(['id' => $testCustomerId]))
            ->forDomain(self::DOMAIN)
            ->has(
                new DomainDeploymentFactory()
                    ->withRtrProvider()
                    ->for(new DomainContactFactory()->state(['customer_id' => $testCustomerId]), 'contactOwner')
            )
            ->for($this->domainProduct)
            ->createOne();

        $testHandle = '123-TestKees-AAAAAAAAAAAAAAAAAAAAAAAAAAA';
        $mockLogger = self::createMock(LoggerInterface::class);
        $mockServiceFactory = self::createMock(DomainServiceFactory::class);
        $mockRtrService = self::createMock(RtrService::class);

        $domainService = new DomainService(
            self::resolve(NameserverAssignerFactory::class),
            self::resolve(AssignNameserversToDomainAction::class),
            $mockServiceFactory,
            $mockLogger,
            self::resolve(DnsDeploymentRepository::class),
            self::resolve(DnsProductSpecRepository::class),
            self::resolve(DnsService::class),
            self::resolve(DomainDeploymentRepository::class),
            self::resolve(DomainProviderBusinessUnitRepository::class),
        );

        $mockRtrService->expects(self::once())
            ->method('createContact')
            ->willReturn($testHandle);

        $mockRtrService->expects(self::once())
            ->method('minimalTransfer')
            ->with($domainSubscription->domainDeployment, self::callback(fn (Handles $handle) => $handle->getOwnerHandle() === $testHandle))
            ->willReturn(new TransferResult(TechnicalStatus::PENDING->value));

        $mockServiceFactory->expects(self::exactly(2))
            ->method('driver')
            ->with(ProviderSlug::REALTIME_REGISTER)
            ->willReturn($mockRtrService);

        $mockLogger->expects(self::once())
            ->method('info')
            ->with(
                'Starting minimal transfer for domain {domain.name}',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domainSubscription->domain,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $domainSubscription->uuid,
                    LoggingContextKeys::SUBSCRIPTION_ID => $domainSubscription->id,
                ]
            );

        self::assertNotNull($domainSubscription->domainDeployment);

        $result = $domainService->minimalTransfer($domainSubscription->domainDeployment);
        self::assertSame(TechnicalStatus::PENDING->value, $result->getStatus());
    }

    #[Test]
    public function addIsExternalInformation(): void
    {
        $domainForSub1 = 'domain1.nl';
        $domainForSub2 = 'domain2.nl';

        $customer = new CustomerFactory()->createOne();
        $dnsProduct = new ProductFactory()->freeDns()->createOne();

        $domainSubscription1 = new SubscriptionFactory()
            ->for($customer)
            ->forDomain($domainForSub1)
            ->has(
                new DomainDeploymentFactory()
                    ->withRtrProvider()
                    ->for(new DomainContactFactory()->state(['customer_id' => $customer->id]), 'contactOwner')
            )
            ->for($this->domainProduct)
            ->createOne();

        new SubscriptionFactory()
            ->for($customer)
            ->forDomain($domainForSub1)
            ->for($dnsProduct)
            ->parentSubscription($domainSubscription1)
            ->has(new DnsDeploymentFactory()->withExternalNameserver())
            ->createOne();

        $domainSubscription2 = new SubscriptionFactory()
            ->for($customer)
            ->forDomain($domainForSub2)
            ->has(
                new DomainDeploymentFactory()
                    ->withRtrProvider()
                    ->for(new DomainContactFactory()->state(['customer_id' => $customer->id]), 'contactOwner')
            )
            ->for($this->domainProduct)
            ->createOne();

        new SubscriptionFactory()
            ->for($customer)
            ->forDomain($domainForSub2)
            ->for($dnsProduct)
            ->parentSubscription($domainSubscription2)
            ->has(new DnsDeploymentFactory()->withInternalNameserver())
            ->createOne();

        $mockLogger = self::createStub(LoggerInterface::class);
        $mockServiceFactory = self::createStub(DomainServiceFactory::class);

        $domainService = new DomainService(
            self::resolve(NameserverAssignerFactory::class),
            self::resolve(AssignNameserversToDomainAction::class),
            $mockServiceFactory,
            $mockLogger,
            self::resolve(DnsDeploymentRepository::class),
            self::resolve(DnsProductSpecRepository::class),
            self::resolve(DnsService::class),
            self::resolve(DomainDeploymentRepository::class),
            self::resolve(DomainProviderBusinessUnitRepository::class),
        );

        $domains = [$domainForSub1, $domainForSub2];

        $result = $domainService->addIsExternalInformation($domains);
        Assert::assertSame($domainForSub1, $result[0]['domain']);
        Assert::assertTrue($result[0]['is_external']);

        Assert::assertSame($domainForSub2, $result[1]['domain']);
        Assert::assertFalse($result[1]['is_external']);
    }

    #[Test]
    public function resetToInternalWithoutZone(): void
    {
        $domain = 'test-domain-reset-nameservers-without-zone.nl';

        $domainSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->forDomain($domain)
            ->for($this->domainProduct)
            ->createOne();

        $domainDeployment = new DomainDeploymentFactory()
            ->withRtrProvider()
            ->for($domainSubscription)
            ->createOne();

        $dnsDeployment = DnsDeploymentFactory::new()
            ->for(
                new SubscriptionFactory()
                    ->for(new CustomerFactory())
                    ->for(new ProductFactory()->freeDns())
                    ->forDomain($domain)
                    ->state(['parent_subscription_id' => $domainSubscription->id])
            )
            ->withExternalNameserver()
            ->createOne();

        $mockExternalAssigner = self::createMock(DnsExternalNameserverAssigner::class);
        $mockDnsSpecRepo = self::createMock(DnsProductSpecRepository::class);
        $mockDnsService = self::createMock(DnsService::class);
        $mockDomainDriver = self::createMock(DomainDriverInterface::class);
        $mockDomainFactory = self::createMock(DomainServiceFactory::class);
        $mockAssignNameserverAction = self::createMock(AssignNameserversToDomainAction::class);
        $mockNameserverAssignerFactory = self::createMock(NameserverAssignerFactory::class);
        $mockLogger = self::createMock(LoggerInterface::class);
        $mockDnsDeploymentRepository = self::createMock(DnsDeploymentRepository::class);

        $mockDnsDeploymentRepository->expects(self::once())
            ->method('getDnsDeploymentFromDomain')
            ->with($domain)
            ->willReturn($dnsDeployment);

        $mockNameserverAssignerFactory->expects(self::once())
            ->method('createAssigner')
            ->with(NameserverType::EXTERNAL)
            ->willReturn($mockExternalAssigner);

        $mockExternalAssigner->expects(self::once())
            ->method('clear')
            ->with(self::callback(fn (DnsDeployment $receivedDnsDeployment) => $receivedDnsDeployment->id === $dnsDeployment->id));

        $mockDnsSpecRepo->expects(self::once())
            ->method('isPremiumDns')
            ->with(self::callback(fn (Product $receivedProduct) => $receivedProduct->id === $dnsDeployment->subscription->product->id))
            ->willReturn(false);

        $mockDomainFactory->expects(self::once())
            ->method('driver')
            ->with(ProviderSlug::REALTIME_REGISTER)
            ->willReturn($mockDomainDriver);

        $mockDnsService->expects(self::once())
            ->method('hasDnsZone')
            ->willReturn(false);

        $mockLogger->expects(self::once())
            ->method('debug')
            ->with(
                'No internal zone yet for {domain.name}. Creating now',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DNS,
                    LoggingContextKeys::PROVISIONING_ID => $dnsDeployment->id,
                ]
            );

        $dnsNameservers = $dnsDeployment->dnsNameservers->all();
        $nameservers = array_map(fn (DnsNameserver $ns) => new Nameserver($ns->nameserver), $dnsNameservers);

        $mockDnsDeploymentRepository->expects(self::once())
            ->method('getNameservers')
            ->with(self::assertCallbackIsModel($dnsDeployment))
            ->willReturn($nameservers);

        $mockDnsService->expects(self::once())
            ->method('createDnsZone')
            ->with($domain, 'default', null, null, false, $nameservers);

        $mockDomainDriver->expects(self::once())
            ->method('enableDnssec')
            ->with($domainSubscription->domain)
            ->willReturn(true);

        $mockAssignNameserverAction->expects(self::once())
            ->method('assign')
            ->with(self::callback(fn (DomainDeployment $receivedDeployment) => $receivedDeployment->id === $domainDeployment->id));

        $domainService = new DomainService(
            nameserverAssignerFactory: $mockNameserverAssignerFactory,
            assignNameserversToDomainAction: $mockAssignNameserverAction,
            domainServiceFactory: $mockDomainFactory,
            logger: $mockLogger,
            dnsDeploymentRepository: $mockDnsDeploymentRepository,
            dnsProductSpecRepository: $mockDnsSpecRepo,
            dnsService: $mockDnsService,
            domainDeploymentRepository: self::resolve(DomainDeploymentRepository::class),
            businessUnitRepository: self::resolve(DomainProviderBusinessUnitRepository::class),
        );

        $reset = $domainService->resetNameServersToInternal($domainDeployment);

        self::assertTrue($reset);
        self::assertSame(NameserverType::INTERNAL, $dnsDeployment->refresh()->nameserver_type);
    }

    #[Test]
    public function resetToInternal(): void
    {
        $domain = 'test-domain-reset-nameservers.nl';

        $domainSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->forDomain($domain)
            ->for($this->domainProduct)
            ->createOne();

        $domainDeployment = new DomainDeploymentFactory()
            ->withRtrProvider()
            ->for($domainSubscription)
            ->createOne();

        $dnsDeployment = DnsDeploymentFactory::new()
            ->for(
                new SubscriptionFactory()
                ->for(new CustomerFactory())
                ->for(new ProductFactory()->freeDns())
                ->forDomain($domain)
                ->state(['parent_subscription_id' => $domainSubscription->id])
            )
            ->withExternalNameserver()
            ->createOne();

        $mockExternalAssigner = self::createMock(DnsExternalNameserverAssigner::class);
        $mockDnsSpecRepo = self::createMock(DnsProductSpecRepository::class);
        $mockDnsService = self::createMock(DnsService::class);
        $mockDomainDriver = self::createMock(DomainDriverInterface::class);
        $mockDomainFactory = self::createMock(DomainServiceFactory::class);
        $mockAssignNameserverAction = self::createMock(AssignNameserversToDomainAction::class);
        $mockNameserverAssignerFactory = self::createMock(NameserverAssignerFactory::class);

        $mockNameserverAssignerFactory->expects(self::once())
            ->method('createAssigner')
            ->with(NameserverType::EXTERNAL)
            ->willReturn($mockExternalAssigner);

        $mockExternalAssigner->expects(self::once())
            ->method('clear')
            ->with(self::callback(fn (DnsDeployment $receivedDnsDeployment) => $receivedDnsDeployment->id === $dnsDeployment->id));

        $mockDnsSpecRepo->expects(self::once())
            ->method('isPremiumDns')
            ->with(self::callback(fn (Product $receivedProduct) => $receivedProduct->id === $dnsDeployment->subscription->product->id))
            ->willReturn(false);

        $mockDomainFactory->expects(self::once())
            ->method('driver')
            ->with(ProviderSlug::REALTIME_REGISTER)
            ->willReturn($mockDomainDriver);

        $mockDnsService->expects(self::once())
            ->method('hasDnsZone')
            ->willReturn(true);

        $mockDomainDriver->expects(self::once())
            ->method('enableDnssec')
            ->with($domainSubscription->domain)
            ->willReturn(true);

        $mockAssignNameserverAction->expects(self::once())
            ->method('assign')
            ->with(self::callback(fn (DomainDeployment $receivedDeployment) => $receivedDeployment->id === $domainDeployment->id));

        $domainService = new DomainService(
            nameserverAssignerFactory: $mockNameserverAssignerFactory,
            assignNameserversToDomainAction: $mockAssignNameserverAction,
            domainServiceFactory: $mockDomainFactory,
            logger: self::createStub(LoggerInterface::class),
            dnsDeploymentRepository: self::resolve(DnsDeploymentRepository::class),
            dnsProductSpecRepository: $mockDnsSpecRepo,
            dnsService: $mockDnsService,
            domainDeploymentRepository: self::resolve(DomainDeploymentRepository::class),
            businessUnitRepository: self::resolve(DomainProviderBusinessUnitRepository::class),
        );

        $reset = $domainService->resetNameServersToInternal($domainDeployment);

        self::assertTrue($reset);
        self::assertSame(NameserverType::INTERNAL, $dnsDeployment->refresh()->nameserver_type);
    }

    #[Test]
    public function resetPremiumToInternal(): void
    {
        $domain = 'test-domain-reset-nameservers.nl';

        $domainSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->forDomain($domain)
            ->for($this->domainProduct)
            ->createOne();

        $domainDeployment = new DomainDeploymentFactory()
            ->withRtrProvider()
            ->for($domainSubscription)
            ->createOne();

        $dnsDeployment = DnsDeploymentFactory::new()
            ->for(
                new SubscriptionFactory()
                ->for(new CustomerFactory())
                ->for(new ProductFactory()->freeDns())
                ->forDomain($domain)
                ->state(['parent_subscription_id' => $domainSubscription->id])
            )
            ->withExternalNameserver()
            ->createOne();

        $mockExternalAssigner = self::createMock(DnsExternalNameserverAssigner::class);
        $mockDnsSpecRepo = self::createMock(DnsProductSpecRepository::class);
        $mockDomainDriver = self::createMock(DomainDriverInterface::class);
        $mockDomainFactory = self::createMock(DomainServiceFactory::class);
        $mockAssignNameserverAction = self::createMock(AssignNameserversToDomainAction::class);
        $mockNameserverAssignerFactory = self::createMock(NameserverAssignerFactory::class);
        $mockDnsService = self::createMock(DnsService::class);

        $mockNameserverAssignerFactory->expects(self::once())
            ->method('createAssigner')
            ->with(NameserverType::EXTERNAL)
            ->willReturn($mockExternalAssigner);

        $mockExternalAssigner->expects(self::once())
            ->method('clear')
            ->with(self::callback(fn (DnsDeployment $receivedDnsDeployment) => $receivedDnsDeployment->id === $dnsDeployment->id));

        $mockDnsSpecRepo->expects(self::once())
            ->method('isPremiumDns')
            ->with(self::callback(fn (Product $receivedProduct) => $receivedProduct->id === $dnsDeployment->subscription->product->id))
            ->willReturn(true);

        $mockDnsService->expects(self::once())
            ->method('hasDnsZone')
            ->willReturn(true);

        $mockDomainFactory->expects(self::once())
            ->method('driver')
            ->with(ProviderSlug::REALTIME_REGISTER)
            ->willReturn($mockDomainDriver);

        $mockDomainDriver->expects(self::once())
            ->method('enableDnssec')
            ->with($domainSubscription->domain)
            ->willReturn(true);

        $mockAssignNameserverAction->expects(self::once())
            ->method('assign')
            ->with(self::callback(fn (DomainDeployment $receivedDeployment) => $receivedDeployment->id === $domainDeployment->id));

        $domainService = new DomainService(
            nameserverAssignerFactory: $mockNameserverAssignerFactory,
            assignNameserversToDomainAction: $mockAssignNameserverAction,
            domainServiceFactory: $mockDomainFactory,
            logger: self::createStub(LoggerInterface::class),
            dnsDeploymentRepository: self::resolve(DnsDeploymentRepository::class),
            dnsProductSpecRepository: $mockDnsSpecRepo,
            dnsService: $mockDnsService,
            domainDeploymentRepository: self::resolve(DomainDeploymentRepository::class),
            businessUnitRepository: self::resolve(DomainProviderBusinessUnitRepository::class),
        );

        $reset = $domainService->resetNameServersToInternal($domainDeployment);

        self::assertTrue($reset);
        self::assertSame(NameserverType::VANITY, $dnsDeployment->refresh()->nameserver_type);
    }

    #[Test]
    public function setCustomNameservers(): void
    {
        $domain = 'test-domain-custom-nameservers.nl';
        $nameservers = [
            new Nameserver('ns1.external.com'),
            new Nameserver('ns2.external.com'),
        ];

        $domainSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->forDomain($domain)
            ->for($this->domainProduct)
            ->createOne();

        $domainDeployment = new DomainDeploymentFactory()
            ->withRtrProvider()
            ->for($domainSubscription)
            ->createOne();

        $dnsDeployment = DnsDeploymentFactory::new()
            ->for(
                new SubscriptionFactory()
                    ->for(new CustomerFactory())
                    ->for(new ProductFactory()->freeDns())
                    ->forDomain($domain)
                    ->state(['parent_subscription_id' => $domainSubscription->id])
            )
            ->withExternalNameserver()
            ->createOne();

        $mockNameserverAssignFactory = self::mock(NameserverAssignerFactory::class);
        $mockDnsDeploymentRepo = self::createMock(DnsDeploymentRepository::class);
        $mockAssigner = self::createMock(NameserverAssignerInterface::class);
        $mockExternalAssigner = self::createMock(DnsExternalNameserverAssigner::class);
        $mockDomainFactory = self::createMock(DomainServiceFactory::class);
        $mockDomainDriver = self::createMock(DomainDriverInterface::class);

        $mockDnsDeploymentRepo->expects(self::once())
            ->method('getDnsDeploymentFromDomain')
            ->with($domain)
            ->willReturn($dnsDeployment);

        $mockNameserverAssignFactory
            ->shouldReceive('createAssigner')
            ->once()
            ->with($dnsDeployment->nameserver_type)
            ->andReturn($mockAssigner);

        $mockAssigner->expects(self::once())
            ->method('clear');

        $mockNameserverAssignFactory
            ->shouldReceive('createAssigner')
            ->once()
            ->with(NameserverType::EXTERNAL)
            ->andReturn($mockExternalAssigner);

        $mockExternalAssigner->expects(self::once())
            ->method('assign')
            ->with(
                self::callback(fn (DnsDeployment $receivedDnsDeployment) => $receivedDnsDeployment->id === $dnsDeployment->id),
                $nameservers
            );

        $mockDomainFactory->expects(self::once())
            ->method('driver')
            ->with(ProviderSlug::REALTIME_REGISTER)
            ->willReturn($mockDomainDriver);

        $mockDomainDriver->expects(self::once())
            ->method('updateNameServers')
            ->with($domain, $nameservers)
            ->willReturn(true);

        $domainService = new DomainService(
            nameserverAssignerFactory: $mockNameserverAssignFactory,
            assignNameserversToDomainAction: self::resolve(AssignNameserversToDomainAction::class),
            domainServiceFactory: $mockDomainFactory,
            logger: self::createStub(LoggerInterface::class),
            dnsDeploymentRepository: $mockDnsDeploymentRepo,
            dnsProductSpecRepository: self::resolve(DnsProductSpecRepository::class),
            dnsService: self::resolve(DnsService::class),
            domainDeploymentRepository: self::resolve(DomainDeploymentRepository::class),
            businessUnitRepository: self::resolve(DomainProviderBusinessUnitRepository::class),
        );

        $nameserversSet = $domainService->setCustomNameservers($domainDeployment, $nameservers);

        self::assertTrue($nameserversSet);
    }

    #[Test]
    public function linkContactWithBusinessUnit(): void
    {
        $domainService = new DomainService(
            nameserverAssignerFactory: self::createStub(NameserverAssignerFactory::class),
            assignNameserversToDomainAction: self::createStub(AssignNameserversToDomainAction::class),
            domainServiceFactory: $domainServiceFactory = self::createMock(DomainServiceFactory::class),
            logger: self::createStub(LoggerInterface::class),
            dnsDeploymentRepository: self::resolve(DnsDeploymentRepository::class),
            dnsProductSpecRepository: self::resolve(DnsProductSpecRepository::class),
            dnsService: self::createStub(DnsService::class),
            domainDeploymentRepository: self::resolve(DomainDeploymentRepository::class),
            businessUnitRepository: self::resolve(DomainProviderBusinessUnitRepository::class),
        );

        $customer = new CustomerFactory()
            ->createOne();

        $subscription = new SubscriptionFactory()
            ->for($this->domainProduct)
            ->for($customer)
            ->forDomain('yourhosting.nl')
            ->createOne();

        new DomainProviderBusinessUnitFactory()
            ->createOne([
                'slug' => 'argeweb',
            ]);
        // Query after creation so that recently created isn't set to true for assertion later
        $businessUnit = DomainProviderBusinessUnit::query()
            ->where('slug', 'argeweb')
            ->first();
        self::assertNotNull($businessUnit);

        new DomainDeploymentFactory()
            ->for($subscription)
            ->withRtrProvider()
            ->createOne(['domain_business_unit_id' => $businessUnit->id]);

        $rtrProvider = Provider::where('slug', ProviderSlug::REALTIME_REGISTER)
            ->where('type', ProviderType::DOMAIN)
            ->first();

        $mockDriver = self::createMock(DomainDriverInterface::class);

        $mockDriver->expects(self::once())
            ->method('doesContactExist')
            ->with('EXTERNAL-HANDLE')
            ->willReturn(true);
        $mockDriver->expects(self::once())
            ->method('ensureContactValidatedForDomain');
        $mockDriver->expects(self::once())
            ->method('linkContactHandle');

        // 1x for doesContactExist inside findOrCreateExternalHandle, 1x for ensureContactValidatedForDomain, 1x for linkContactHandle.
        $domainServiceFactory->expects(self::exactly(3))
            ->method('driver')
            ->with(ProviderSlug::REALTIME_REGISTER, $businessUnit)
            ->willReturn($mockDriver);

        $domains = [
            [
                'domain' => 'yourhosting.nl',
            ],
        ];
        $contact = new DomainContactFactory()
            ->for($customer)
            ->createOne();
        self::assertInstanceOf(Provider::class, $rtrProvider);
        $contact->providers()->attach(
            $rtrProvider,
            [
                'external_contact' => 'EXTERNAL-HANDLE',
                'domain_business_unit_id' => $businessUnit->id,
            ]
        );
        $contact->save();
        $result = $domainService->linkContactHandle($domains, $contact);
        self::assertTrue($result);
    }

    #[Test]
    public function unlinkContactHandleWithBusinessUnit(): void
    {
        $domainService = new DomainService(
            nameserverAssignerFactory: self::createStub(NameserverAssignerFactory::class),
            assignNameserversToDomainAction: self::createStub(AssignNameserversToDomainAction::class),
            domainServiceFactory: $domainServiceFactory = self::createMock(DomainServiceFactory::class),
            logger: self::createStub(LoggerInterface::class),
            dnsDeploymentRepository: self::resolve(DnsDeploymentRepository::class),
            dnsProductSpecRepository: self::resolve(DnsProductSpecRepository::class),
            dnsService: self::createStub(DnsService::class),
            domainDeploymentRepository: self::resolve(DomainDeploymentRepository::class),
            businessUnitRepository: self::resolve(DomainProviderBusinessUnitRepository::class),
        );

        $customer = new CustomerFactory()
            ->createOne();

        $subscription = new SubscriptionFactory()
            ->for($this->domainProduct)
            ->for($customer)
            ->forDomain('yourhosting.nl')
            ->createOne();

        new DomainProviderBusinessUnitFactory()
            ->createOne([
                'slug' => 'argeweb',
            ]);
        // Query after creation so that recently created isn't set to true for assertion later
        $businessUnit = DomainProviderBusinessUnit::query()
            ->where('slug', 'argeweb')
            ->first();
        self::assertNotNull($businessUnit);

        new DomainDeploymentFactory()
            ->for($subscription)
            ->withRtrProvider()
            ->createOne([
                'domain_business_unit_id' => $businessUnit->id,
            ]);

        $rtrProvider = Provider::where('slug', ProviderSlug::REALTIME_REGISTER)
            ->where('type', ProviderType::DOMAIN)
            ->first();

        $domainServiceFactory->expects(self::exactly(3))
            ->method('driver')
            ->with(ProviderSlug::REALTIME_REGISTER, $businessUnit)
            ->willReturn($domainDriver = self::createMock(DomainDriverInterface::class));

        $domainDriver->expects(self::once())
            ->method('retrieveCustomerHandle')
            ->willReturn(
                new RetrieveCustomerResponse(
                    status: DomainStatus::ACTIVE,
                    reason: null,
                    responseCode: 1,
                    handle: 'EXTERNAL-HANDLE',
                    organization: null,
                    vat: '21',
                    firstName: 'John',
                    lastName: 'Doe',
                    gender: '',
                    phone: '+31612345678',
                    email: 'info@yourhosting.nl',
                    streetName: 'Street',
                    streetNumber: '1',
                    zip: '1111AA',
                    city: 'City',
                    countryCode: 'NL',
                )
            );

        $domains = [
            'yourhosting.nl',
        ];
        $contact = new DomainContactFactory()
            ->for($customer)
            ->createOne();
        self::assertInstanceOf(Provider::class, $rtrProvider);
        $contact->providers()->attach(
            $rtrProvider,
            [
                'external_contact' => 'EXTERNAL-HANDLE',
                'domain_business_unit_id' => $businessUnit->id,
            ]
        );
        $contact->save();
        $domainService->unlinkContactHandle($domains, $contact);
    }

    #[Test]
    public function destroyContactRemoteWithBusinessUnit(): void
    {
        $domainService = new DomainService(
            nameserverAssignerFactory: self::createStub(NameserverAssignerFactory::class),
            assignNameserversToDomainAction: self::createStub(AssignNameserversToDomainAction::class),
            domainServiceFactory: $domainServiceFactory = self::createMock(DomainServiceFactory::class),
            logger: self::createStub(LoggerInterface::class),
            dnsDeploymentRepository: self::resolve(DnsDeploymentRepository::class),
            dnsProductSpecRepository: self::resolve(DnsProductSpecRepository::class),
            dnsService: self::createStub(DnsService::class),
            domainDeploymentRepository: self::resolve(DomainDeploymentRepository::class),
            businessUnitRepository: self::resolve(DomainProviderBusinessUnitRepository::class),
        );

        $customer = new CustomerFactory()
            ->createOne();

        $subscription = new SubscriptionFactory()
            ->for($this->domainProduct)
            ->for($customer)
            ->forDomain('yourhosting.nl')
            ->createOne();

        new DomainProviderBusinessUnitFactory()
            ->createOne([
                'slug' => 'argeweb',
            ]);
        // Query after creation so that recently created isn't set to true for assertion later
        $businessUnit = DomainProviderBusinessUnit::query()
            ->where('slug', 'argeweb')
            ->first();
        self::assertNotNull($businessUnit);

        new DomainDeploymentFactory()
            ->for($subscription)
            ->withRtrProvider()
            ->createOne();

        $rtrProvider = Provider::where('slug', ProviderSlug::REALTIME_REGISTER)
            ->where('type', ProviderType::DOMAIN)
            ->first();

        $domainServiceFactory->expects(self::once())
            ->method('driver')
            ->with(ProviderSlug::REALTIME_REGISTER, $businessUnit);

        $contact = new DomainContactFactory()
            ->for($customer)
            ->createOne();
        self::assertInstanceOf(Provider::class, $rtrProvider);
        $contact->providers()->attach(
            $rtrProvider,
            [
                'external_contact' => 'EXTERNAL-HANDLE',
                'domain_business_unit_id' => $businessUnit->id,
            ]
        );
        $contact->save();
        $domainService->destroyContactRemote($customer, $contact);
    }

    #[Test]
    public function migratedCustomerWithOldHandleCreatesNewHandleDuringRegistration(): void
    {
        $customer = new CustomerFactory()->createOne();
        $argewebContactExternal = 12345;
        $rtrProvider = ProviderFactory::new()->domainRtr()->createOne();

        $argewebBusinessUnit = DomainProviderBusinessUnitFactory::new()
            ->argeweb()
            ->createOne();

        RtrProviderCredentialsFactory::new()
            ->state(['domain_business_unit_id' => $argewebBusinessUnit->id])
            ->createOne();

        $domainContact = new DomainContactFactory()
            ->state(['customer_id' => $customer->id])
            ->createOne();

        $domainContact->providers()->attach($rtrProvider, ['external_contact' => $argewebContactExternal, 'domain_business_unit_id' => $argewebBusinessUnit->id]);

        $argewebSubscription = new SubscriptionFactory()
            ->for($this->domainProduct)
            ->for($customer)
            ->forDomain('migrated-domain.nl')
            ->createOne();

        new DomainDeploymentFactory()
            ->for($argewebSubscription)
            ->withRtrProvider()
            ->state([
                'contact_owner_id' => $domainContact->id,
                'domain_business_unit_id' => $argewebBusinessUnit->id,
            ])
            ->createOne();

        // At this point we have a migrated customer
        // with a DomainContact only linked to Argeweb as BU and RTR as provider
        // and a domain deployment + subscription with Argeweb as BU and RTR as provider

        $domainSubscription = new SubscriptionFactory()
            ->for($customer)
            ->forDomain('new-domain-after-migrations.nl')
            ->has(
                new DomainDeploymentFactory()
                    ->withRtrProvider()
                    // Here we attach the domain contact that is currently only present at Argeweb RTR
                    ->for($domainContact, 'contactOwner')
            )
            ->for($this->domainProduct)
            ->createOne();

        $mockLogger = self::createMock(LoggerInterface::class);
        $mockServiceFactory = self::createMock(DomainServiceFactory::class);
        $mockRtrService = self::createMock(RtrService::class);

        $domainService = new DomainService(
            self::resolve(NameserverAssignerFactory::class),
            self::resolve(AssignNameserversToDomainAction::class),
            $mockServiceFactory,
            $mockLogger,
            self::resolve(DnsDeploymentRepository::class),
            self::resolve(DnsProductSpecRepository::class),
            self::resolve(DnsService::class),
            self::resolve(DomainDeploymentRepository::class),
            self::resolve(DomainProviderBusinessUnitRepository::class),
        );

        $testHandle = 'handle-created-on-WF-rtr';

        // We expect to create a new contact because the one created before only exists on Argeweb RTR
        $mockRtrService->expects(self::once())
            ->method('createContact')
            ->willReturn($testHandle);

        $mockRtrService->expects(self::once())
            ->method('minimalRegister')
            ->with($domainSubscription->domainDeployment, self::callback(fn (Handles $handle) => $handle->getOwnerHandle() === $testHandle))
            ->willReturn(new RegistrationResult(DomainStatus::PENDING));

        $mockServiceFactory->expects(self::exactly(2))
            ->method('driver')
            ->with(ProviderSlug::REALTIME_REGISTER)
            ->willReturn($mockRtrService);

        $mockLogger->expects(self::once())
            ->method('info')
            ->with(
                'Starting minimal register for domain {domain.name}',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domainSubscription->domain,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $domainSubscription->uuid,
                    LoggingContextKeys::SUBSCRIPTION_ID => $domainSubscription->id,
                ]
            );

        self::assertNotNull($domainSubscription->domainDeployment);

        $result = $domainService->minimalRegister($domainSubscription->domainDeployment);
        self::assertSame(DomainStatus::PENDING, $result->getStatus());
    }

    #[Test]
    public function minimalRegisterReusesExistingHandleWhenRemoteContactExists(): void
    {
        $existingHandle = 'EXISTING-HANDLE-AT-REMOTE';

        $customer = new CustomerFactory()->createOne();
        $rtrProvider = ProviderFactory::new()->domainRtr()->createOne();

        $domainContact = new DomainContactFactory()
            ->state(['customer_id' => $customer->id])
            ->createOne();

        $domainContact->providers()->attach($rtrProvider, [
            'external_contact' => $existingHandle,
        ]);

        $domainSubscription = new SubscriptionFactory()
            ->for($customer)
            ->forDomain('reuse-existing-handle.nl')
            ->has(
                new DomainDeploymentFactory()
                    ->withRtrProvider()
                    ->for($domainContact, 'contactOwner')
            )
            ->for($this->domainProduct)
            ->createOne();

        $mockLogger = self::createStub(LoggerInterface::class);
        $mockServiceFactory = self::createMock(DomainServiceFactory::class);
        $mockRtrService = self::createMock(RtrService::class);

        $domainService = new DomainService(
            self::resolve(NameserverAssignerFactory::class),
            self::resolve(AssignNameserversToDomainAction::class),
            $mockServiceFactory,
            $mockLogger,
            self::resolve(DnsDeploymentRepository::class),
            self::resolve(DnsProductSpecRepository::class),
            self::resolve(DnsService::class),
            self::resolve(DomainDeploymentRepository::class),
            self::resolve(DomainProviderBusinessUnitRepository::class),
        );

        $mockRtrService->expects(self::once())
            ->method('doesContactExist')
            ->with($existingHandle)
            ->willReturn(true);

        $mockRtrService->expects(self::never())->method('createContact');

        $mockRtrService->expects(self::once())
            ->method('minimalRegister')
            ->with(
                $domainSubscription->domainDeployment,
                self::callback(fn (Handles $handle): bool => $handle->getOwnerHandle() === $existingHandle)
            )
            ->willReturn(new RegistrationResult(DomainStatus::PENDING));

        // 1x for doesContactExist, 1x for minimalRegister
        $mockServiceFactory->expects(self::exactly(2))
            ->method('driver')
            ->with(ProviderSlug::REALTIME_REGISTER)
            ->willReturn($mockRtrService);

        self::assertNotNull($domainSubscription->domainDeployment);

        $result = $domainService->minimalRegister($domainSubscription->domainDeployment);

        self::assertSame(DomainStatus::PENDING, $result->getStatus());

        // Pivot row stays in place because the remote handle still exists.
        self::assertDatabaseHas('domain_contact_provider', [
            'domain_contact_id' => $domainContact->id,
            'provider_id' => $rtrProvider->id,
            'external_contact' => $existingHandle,
        ]);
    }

    #[Test]
    public function minimalRegisterRemovesPivotAndCreatesNewHandleWhenRemoteContactMissing(): void
    {
        $staleHandle = 'STALE-HANDLE-NOT-AT-REMOTE';
        $newHandle = 'FRESH-HANDLE-CREATED-AT-REMOTE';

        $customer = new CustomerFactory()->createOne();
        $rtrProvider = ProviderFactory::new()->domainRtr()->createOne();

        $domainContact = new DomainContactFactory()
            ->state(['customer_id' => $customer->id])
            ->createOne();

        $domainContact->providers()->attach($rtrProvider, [
            'external_contact' => $staleHandle,
        ]);

        self::assertDatabaseHas('domain_contact_provider', [
            'domain_contact_id' => $domainContact->id,
            'provider_id' => $rtrProvider->id,
            'external_contact' => $staleHandle,
        ]);

        $domainSubscription = new SubscriptionFactory()
            ->for($customer)
            ->forDomain('remove-stale-pivot.nl')
            ->has(
                new DomainDeploymentFactory()
                    ->withRtrProvider()
                    ->for($domainContact, 'contactOwner')
            )
            ->for($this->domainProduct)
            ->createOne();

        $mockLogger = self::createMock(LoggerInterface::class);
        $mockServiceFactory = self::createMock(DomainServiceFactory::class);
        $mockRtrService = self::createMock(RtrService::class);

        $domainService = new DomainService(
            self::resolve(NameserverAssignerFactory::class),
            self::resolve(AssignNameserversToDomainAction::class),
            $mockServiceFactory,
            $mockLogger,
            self::resolve(DnsDeploymentRepository::class),
            self::resolve(DnsProductSpecRepository::class),
            self::resolve(DnsService::class),
            self::resolve(DomainDeploymentRepository::class),
            self::resolve(DomainProviderBusinessUnitRepository::class),
        );

        $mockRtrService->expects(self::once())
            ->method('doesContactExist')
            ->with($staleHandle)
            ->willReturn(false);

        $mockRtrService->expects(self::once())
            ->method('createContact')
            ->willReturn($newHandle);

        $mockRtrService->expects(self::once())
            ->method('minimalRegister')
            ->with(
                $domainSubscription->domainDeployment,
                self::callback(fn (Handles $handle): bool => $handle->getOwnerHandle() === $newHandle)
            )
            ->willReturn(new RegistrationResult(DomainStatus::PENDING));

        // 1x doesContactExist, 1x createContact, 1x minimalRegister
        $mockServiceFactory->expects(self::exactly(3))
            ->method('driver')
            ->with(ProviderSlug::REALTIME_REGISTER)
            ->willReturn($mockRtrService);

        $mockLogger->expects(self::once())
            ->method('warning')
            ->with(
                self::callback(fn (string $message): bool => str_contains(
                    $message,
                    sprintf('DomainContact with handle [%s] exists in DB but not at remote', $staleHandle)
                )),
                self::callback(function (array $context) use ($staleHandle, $domainContact): bool {
                    self::assertSame(ProvisionType::DOMAIN_NAME, $context[LoggingContextKeys::PROVISIONING_TYPE]);
                    $meta = $context[LoggingContextKeys::META];
                    self::assertSame($staleHandle, $meta['external_contact']);
                    self::assertSame($domainContact->id, $meta['domain_contact_id']);
                    return true;
                })
            );

        self::assertNotNull($domainSubscription->domainDeployment);

        $result = $domainService->minimalRegister($domainSubscription->domainDeployment);

        self::assertSame(DomainStatus::PENDING, $result->getStatus());

        // Stale pivot row is removed, a new pivot row is attached for the freshly created handle.
        self::assertDatabaseMissing('domain_contact_provider', [
            'domain_contact_id' => $domainContact->id,
            'provider_id' => $rtrProvider->id,
            'external_contact' => $staleHandle,
        ]);
        self::assertDatabaseHas('domain_contact_provider', [
            'domain_contact_id' => $domainContact->id,
            'provider_id' => $rtrProvider->id,
            'external_contact' => $newHandle,
        ]);
    }
}
