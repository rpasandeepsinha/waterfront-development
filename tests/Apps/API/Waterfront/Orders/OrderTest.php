<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Orders;

use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use RealtimeRegister\RealtimeRegister;
use RealtimeRegister\Support\AuthorizedClient;
use RealtimeRegister\Support\RealtimeRegisterResponse;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\TemplateFactory;
use Tests\Infra\PowerDnsClient\PowerDnsMockHelper;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\DNS\Actions\AssignNameserversToDomainAction;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\DNS\Factories\NameserverAssignerFactory;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\DNS\Repository\DnsProductSpecRepository;
use Waterfront\Domain\DNS\Services\DnsLogService;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\DTO\DomainDetailsDTO;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Domains\Factories\DomainServiceFactory;
use Waterfront\Domain\Domains\Repositories\DomainDeploymentRepository;
use Waterfront\Domain\Domains\Repositories\DomainProviderBusinessUnitRepository;
use Waterfront\Domain\Domains\Serializers\DomainSerializerFactory;
use Waterfront\Domain\Hosting\Plesk\Mailer\MailPleskDetails;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Mailer\MailSubscriptionCreated;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\PowerDnsClient\Entities\PowerDnsSecKeySet;
use Waterfront\Infra\PowerDnsClient\Entities\PowerDnsZone;
use Waterfront\Infra\PowerDnsClient\PowerDnsClient;
use Waterfront\Infra\PowerDnsClient\Services\PowerDnsZoneToDnsZoneConverter;
use Waterfront\Infra\RtrClient\Services\RtrService;

#[CoversNothing]
#[AllowMockObjectsWithoutExpectations]
class OrderTest extends IntegrationTestCase
{
    use PowerDnsMockHelper;

    private Product $comProduct;

    private DomainDetailsDTO $domainDetails;

    private DomainService&MockObject $domainServiceMock;

    private AuthorizedClient&MockInterface $rtrClient;

    protected function setUp(): void
    {
        parent::setUp();

        new ServerFactory()->createOne();
        $domainProvider = ProviderFactory::new()->createOne(
            [
                'type'    => ProviderType::DOMAIN,
                'slug'    => ProviderSlug::OPEN_PROVIDER,
                'enabled' => true,
                'default' => true,
            ]
        );
        ProviderFactory::new()->createOne(['type' => ProviderType::HOSTING, 'slug' => ProviderSlug::PLESK, 'enabled' => true, 'default' => true]);
        ProviderFactory::new()->createOne(
            [
                'type'    => ProviderType::SSL,
                'slug'    => ProviderSlug::OPEN_PROVIDER,
                'enabled' => true,
                'default' => true,
            ]
        );

        $dnsGroup = new ProductGroupFactory()->dns();
        $dnsProduct = new ProductFactory()->for($dnsGroup)->freeDns()->createOne();
        ProductSpecFactory::new()
            ->enable(ProductSpecName::DNS_CAN_COUPLE_HOSTING_OR_REDIRECT)
            ->for($dnsProduct)
            ->create();
        new ProductPriceComponentFactory()->for($dnsProduct)->registration()->createOne(['price'   => 0]);

        $extensionGroup = new ProductGroupFactory()->extension()->createOne();
        $hostingGroup = new ProductGroupFactory()->hosting()->createOne();
        $sslGroup = new ProductGroupFactory()->ssl()->createOne();

        $sslProduct = new ProductFactory()->for($sslGroup)->createOne([
            'name' => 'Single Domain',
            'slug' => 'ssl_single_domain',
        ]);
        new ProductSpecFactory()->for($sslProduct)->createOne(
            [
                'name'  => 'ssl.product_id',
                'value' => $sslProduct->id,
            ]
        );

        new ProductPriceComponentFactory()->for($sslProduct)->registration()->createOne(['price'   => 120]);
        new ProductPriceComponentFactory()->for($sslProduct)->createOne(['type' => PriceComponentType::PROMOTION, 'price' => 96]);

        $this->comProduct = new ProductFactory()->for($extensionGroup)->createOne([
            'name' => '.com',
            'slug' => 'extension_com',
        ]);

        new ProductSpecFactory()->for($this->comProduct)->createOne(
            [
                'name'  => 'domain.provider_id',
                'value' => $domainProvider->id,
            ]
        );

        new ProductPriceComponentFactory()->for($this->comProduct)->registration()->createOne(['price' => 120]);
        new ProductPriceComponentFactory()->for($this->comProduct)->createOne(['type' => PriceComponentType::PROMOTION, 'price' => 96]);

        $hostingProduct = new ProductFactory()->for($hostingGroup)->createOne([
            'name' => 'premium',
            'slug' => 'hosting_premium',
        ]);

        new ProductPriceComponentFactory()->for($hostingProduct)->registration()->createOne(['price'   => 120]);
        new ProductPriceComponentFactory()->for($hostingProduct)->createOne(['type' => PriceComponentType::PROMOTION, 'price' => 96]);

        $otherGroup = new ProductGroupFactory()->other()->createOne();

        $otherProduct = new ProductFactory()->for($otherGroup)->createOne([
            'name' => 'other_product',
            'slug' => 'other_product_slug',
        ]);

        new ProductPriceComponentFactory()->for($otherProduct)->registration()->createOne([
            'price'   => 100,
            'billing_period'  => 1,
            'contract_period' => 1,
        ]);

        new TemplateFactory()->createMany([
            [
                'slug' => MailPleskDetails::getTemplateSlug(),
            ],
            [
                'slug' => MailSubscriptionCreated::getTemplateSlug(),
            ],
        ]);

        $mockRtr = self::mock(AuthorizedClient::class);
        $this->rtrClient = $mockRtr;

        $this->app->bind(function () use ($mockRtr): RealtimeRegister {
            $externalRtr = new RealtimeRegister('api-key');
            $externalRtr->setClient($mockRtr);
            return $externalRtr;
        });

        $this->app->when(RtrService::class)
            ->needs(RealtimeRegister::class)
            ->give(function () use ($mockRtr) {
                $externalRtr = new RealtimeRegister('api-key');
                $externalRtr->setClient($mockRtr);
                return $externalRtr;
            });

        $domainDetails = include __DIR__ . '/data/domainDetailsValid.php';
        $serializer = DomainSerializerFactory::getSerializer();
        $domainDetails = $serializer->denormalize($domainDetails, DomainDetailsDTO::class);
        $this->domainDetails = $domainDetails;

        $this->domainServiceMock = $this->getMockBuilder(DomainService::class)
            ->setConstructorArgs(
                [
                    self::resolve(NameserverAssignerFactory::class),
                    self::resolve(AssignNameserversToDomainAction::class),
                    self::resolve(DomainServiceFactory::class),
                    self::resolve(LoggerInterface::class),
                    self::resolve(DnsDeploymentRepository::class),
                    self::resolve(DnsProductSpecRepository::class),
                    self::resolve(DnsService::class),
                    self::resolve(DomainDeploymentRepository::class),
                    self::resolve(DomainProviderBusinessUnitRepository::class),
                ]
            )
            ->onlyMethods(['fetchDomain'])
            ->getMock();

        $this->app->bind(DomainService::class, fn () => $this->domainServiceMock);
    }

    #[DataProvider('customerProvider')]
    #[Test]
    public function orderCapitalizedDomain(string|null $organization): void
    {
        $domain = 'CAPITALIZEDDOMAIN.com';
        $expectedDomain = strtolower($domain);

        $this->domainServiceMock
            ->expects(self::once())
            ->method('fetchDomain')
            ->with($expectedDomain, ProviderSlug::OPEN_PROVIDER)
            ->willReturn($this->domainDetails);

        $domainZone = $this->mockDomainZone($domain);
        $keySet = $this->getMockDnsKeySet();

        $mockDnsLogService = self::createStub(DnsLogService::class);
        $this->app->bind(DnsLogService::class, fn (): DnsLogService => $mockDnsLogService);

        $pdnsMock = self::mock(PowerDnsClient::class);

        $pdnsMock->shouldReceive('getZone')
            ->with($expectedDomain)
            ->andReturn($domainZone);

        $pdnsMock->shouldReceive('changeZone')
            ->with($domainZone)
            ->andReturn($domainZone);

        $pdnsMock->shouldReceive('getKeys')
            ->with($expectedDomain)
            ->andReturn($keySet);

        $this->app->bind(PowerDnsClient::class, fn () => $pdnsMock);

        self::assertRtrTldInfoCalled();

        $json = (string) file_get_contents(__DIR__ . '/data/order_payload_capitalized.json');

        $customer = self::createCustomer($organization);

        /** @var array<int, array<string>> $orderPayload */
        $orderPayload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->actingAsCustomer($customer)->postJson(
            $this->generateRoute('partners.order.order'),
            $orderPayload
        )->assertOk();

        $domainSubscription = Subscription::where('domain', 'capitalizeddomain.com')->where(
            'product_uuid',
            $this->comProduct->uuid
        )->firstOrFail();
        self::assertSame(DomainStatus::ACTIVE->value, $domainSubscription->technical_status);
        self::assertSame('capitalizeddomain.com', $domainSubscription->domain);
        self::assertNull(Subscription::where('domain', 'CAPITALIZEDDOMAIN.com')->first());

        $dnsSubscription = Subscription::query()->whereProductGroupType(ProductGroupType::DNS)->where(
            'domain',
            $domainSubscription->domain
        )->firstOrFail();

        self::assertSame(AdministrativeStatus::ACTIVE->value, $dnsSubscription->administrative_status);
    }

    #[DataProvider('customerProvider')]
    #[Test]
    public function order(string|null $organization): void
    {
        $this->applyPdnsMockForOrder('test.com');
        self::assertRtrTldInfoCalled();

        $json = (string) file_get_contents(__DIR__ . '/data/order_payload.json');

        $customer = self::createCustomer($organization);

        /** @var array<int, array<string>> $orderPayload */
        $orderPayload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $response = $this->actingAsCustomer($customer)->postJson(
            $this->generateRoute('partners.order.order'),
            $orderPayload
        )->assertOk();

        $domainSubscription = Subscription::whereProductName('.com')->firstOrFail();
        self::assertSame(DomainStatus::ACTIVE->value, $domainSubscription->technical_status);

        $dnsSubscription = Subscription::query()->whereProductGroupType(ProductGroupType::DNS)->where(
            'domain',
            $domainSubscription->domain
        )->firstOrFail();

        self::assertSame(AdministrativeStatus::ACTIVE->value, $dnsSubscription->administrative_status);

        $hostingDeployment = Subscription::whereProductName('premium')->firstOrFail();
        self::assertSame(TechnicalStatus::OK->value, $hostingDeployment->technical_status);
        self::assertTrue(Subscription::whereProductName('Single Domain')->exists());

        $baseSubscription = Subscription::query()->whereProductGroupType(ProductGroupType::SSL)
            ->where('domain', 'test.com')
            ->firstOrFail();

        $baseSubscription->sslDeployment?->firstOrFail();

        $response->assertJson([
            'status' => 'ok',
        ]);
    }

    #[DataProvider('customerProvider')]
    #[Test]
    public function orderWithDifferentDriverTld(string|null $organization): void
    {
        $domain = 'test.com';

        $domainZone = $this->mockDomainZone($domain);
        $keySet = $this->getMockDnsKeySet();

        $pdnsMock = self::mock(PowerDnsClient::class);

        $pdnsMock->shouldReceive('getZone')
            ->with($domain)
            ->andReturn($domainZone);

        $pdnsMock->shouldReceive('changeZone')
            ->with($domainZone)
            ->andReturn($domainZone);

        $pdnsMock->shouldReceive('getKeys')
            ->with($domain)
            ->andReturn($keySet);

        $this->app->bind(PowerDnsClient::class, fn () => $pdnsMock);
        self::assertRtrTldInfoCalled();

        $json = (string) file_get_contents(__DIR__ . '/data/order_payload.json');

        $customer = self::createCustomer($organization);

        /** @var array<int, array<string>> $orderPayload */
        $orderPayload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $response = $this->actingAsCustomer($customer)->postJson(
            $this->generateRoute('partners.order.order'),
            $orderPayload
        )->assertOk();

        $domainSubscription = Subscription::whereProductName('.com')->firstOrFail();
        self::assertSame(DomainStatus::ACTIVE->value, $domainSubscription->technical_status);

        $dnsSubscription = Subscription::query()->whereProductGroupType(ProductGroupType::DNS)->where(
            'domain',
            $domainSubscription->domain
        )->firstOrFail();
        self::assertSame(AdministrativeStatus::ACTIVE->value, $dnsSubscription->administrative_status);

        $hostingDeployment = Subscription::whereProductName('premium')->firstOrFail();
        self::assertSame(TechnicalStatus::OK->value, $hostingDeployment->technical_status);
        self::assertTrue(Subscription::whereProductName('Single Domain')->exists());

        $baseSubscription = Subscription::query()->whereProductGroupType(ProductGroupType::SSL)
            ->where('domain', 'test.com')
            ->firstOrFail();

        $baseSubscription->sslDeployment?->firstOrFail();

        $order = $customer->orders()->latest()->firstOrFail();

        $response->assertJson([
            'transactionId' => $order->uuid,
            'status'        => 'ok',
        ]);
    }

    /**
     * @return array<int, array<int, bool|string|null>>
     */
    public static function customerProvider(): array
    {
        return [
            ['company'],
            [null],
        ];
    }

    private function createCustomer(string|null $organization): Customer
    {
        return new CustomerFactory()->withAddress()->createOne(['organization' => $organization]);
    }

    private function applyPdnsMockForOrder(string $domain): void
    {
        $domainZone = $this->mockDomainZone($domain);
        $keySet = $this->getMockDnsKeySet();

        $pdnsMock = self::mock(PowerDnsClient::class);

        $pdnsMock->shouldReceive('getZone')
            ->with($domain)
            ->andReturn($domainZone);

        $pdnsMock->shouldReceive('changeZone')
            ->with($domainZone)
            ->andReturn($domainZone);

        $pdnsMock->shouldReceive('getKeys')
            ->with($domain)
            ->andReturn($keySet);

        $this->app->bind(PowerDnsClient::class, fn () => $pdnsMock);
    }

    private function mockDomainZone(string $domain): DnsZone
    {
        $zoneConverter = self::resolve(PowerDnsZoneToDnsZoneConverter::class);
        /** @var array<string, mixed> $pdnsZone */
        $pdnsZone = json_decode($this->getMockedZoneResponseBody($domain), true, 512, JSON_THROW_ON_ERROR);
        return $zoneConverter->convertFromPowerDnsZone(
            PowerDnsZone::fromArray(
                $pdnsZone
            )
        );
    }

    private function getMockDnsKeySet(): PowerDnsSecKeySet
    {
        /** @var array<array<string,mixed>> $keys */
        $keys = json_decode($this->getMockedKeyResponseBody(), true, 512, JSON_THROW_ON_ERROR);
        return PowerDnsSecKeySet::fromArray($keys);
    }

    private function assertRtrTldInfoCalled(): void
    {
        $tldMetaDataResponse = (string) file_get_contents(__DIR__ . '/data/rtr-tld-metadata-com.json');
        $this->rtrClient
            ->shouldReceive('get')
            ->once()
            ->with(sprintf('v2/tlds/%s/info', 'com'))
            ->andReturn(new RealtimeRegisterResponse($tldMetaDataResponse, [], 200));
    }
}
