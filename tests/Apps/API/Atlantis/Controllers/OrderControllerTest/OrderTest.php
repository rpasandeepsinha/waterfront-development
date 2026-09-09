<?php

declare(strict_types=1);

namespace Tests\Apps\API\Atlantis\Controllers\OrderControllerTest;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use SandwaveIo\LighthouseAuthBase\Identity\KratosIdentity;
use SandwaveIo\LighthouseAuthBase\Identity\Traits;
use Symfony\Component\Serializer\Serializer;
use Tests\Factories\CustomerFactory;
use Tests\Factories\OrderFactory;
use Tests\Factories\OrderLineItemFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\TemplateFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\OrderController;
use Waterfront\Apps\API\Waterfront\Requests\Cart\CartOrderRequest;
use Waterfront\Domain\Cart\Services\CartService;
use Waterfront\Domain\Cart\Services\ValidationService;
use Waterfront\Domain\Customers\Enums\PaymentType;
use Waterfront\Domain\DNS\Actions\DisableZonePresigningAction;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\DNS\ValueObjects\Fqdn;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\DTO\RegistrationResult;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Ferry\Services\DnsMigrationService;
use Waterfront\Domain\Hosting\Plesk\Mailer\MailPleskDetails;
use Waterfront\Domain\Invoices\Services\AdministrationFeesManager;
use Waterfront\Domain\Orders\DTO\CartOrder;
use Waterfront\Domain\Orders\DTO\CartOrderSubscription;
use Waterfront\Domain\Orders\Models\Order;
use Waterfront\Domain\Orders\Serializers\CartSerializerFactory;
use Waterfront\Domain\Orders\Services\OrderService;
use Waterfront\Domain\Payments\Services\CustomerSharedPaymentService;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Products\CalculatePriceService;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Mailer\MailSubscriptionCreated;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\SubscriptionService;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Authentication\DTO\AuthenticatedCustomer;
use Waterfront\Infra\PowerDnsClient\Enums\PowerDnsZoneKind;

#[CoversClass(OrderController::class)]
#[AllowMockObjectsWithoutExpectations]
class OrderTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $dnsProduct = new ProductFactory()->freeDns()->createOne();
        ProductSpecFactory::new()
            ->enable(ProductSpecName::DNS_CAN_COUPLE_HOSTING_OR_REDIRECT)
            ->for($dnsProduct)
            ->create();
        new ProductPriceComponentFactory()->for($dnsProduct)->registration()->createOne(['price' => 0]);
        new ProductPriceComponentFactory()->for($dnsProduct)->createOne(['type' => PriceComponentType::PROMOTION, 'price' => 0]);

        $extensionGroup = new ProductGroupFactory()->extension()->createOne(['name' => 'extension']);
        $extensionProduct = new ProductFactory()->for($extensionGroup)->createOne([
            'slug' => 'extension_com',
            'name' => '.com',
        ]);
        new ProductPriceComponentFactory()->for($extensionProduct)->registration()->createOne(['price' => 120]);
        new ProductPriceComponentFactory()->for($extensionProduct)->createOne(['type' => PriceComponentType::PROMOTION, 'price' => 96]);

        $hostingGroup = new ProductGroupFactory()->hosting()->createOne(['name' => 'hosting']);
        $hostingProduct = new ProductFactory()->for($hostingGroup)->createOne([
            'slug' => 'hosting_premium',
            'name' => 'premium',
        ]);
        new ProductPriceComponentFactory()->for($hostingProduct)->registration()->createOne(['price' => 120]);
        new ProductPriceComponentFactory()->for($extensionProduct)->createOne(['type' => PriceComponentType::PROMOTION, 'price' => 96]);

        $sslGroup = new ProductGroupFactory()->ssl()->createOne(['name' => 'ssl']);
        $sslProduct = new ProductFactory()->for($sslGroup)->createOne([
            'slug' => 'ssl_single_domain',
            'name' => 'Single Domain',
        ]);
        new ProductPriceComponentFactory()->for($sslProduct)->registration()->createOne(['price' => 120]);
        new ProductPriceComponentFactory()->for($sslProduct)->createOne(['type' => PriceComponentType::PROMOTION, 'price' => 96]);

        new ServerFactory()->createOne();
        ProviderFactory::new()->createOne(['type' => ProviderType::DOMAIN, 'slug' => ProviderSlug::OPEN_PROVIDER, 'enabled' => true, 'default' => true]);
        ProviderFactory::new()->createOne(['type' => ProviderType::HOSTING, 'slug' => ProviderSlug::PLESK, 'enabled' => true, 'default' => true]);
        ProviderFactory::new()->createOne(['type' => ProviderType::SSL, 'slug' => ProviderSlug::OPEN_PROVIDER, 'enabled' => true, 'default' => true]);
    }

    #[DataProvider('customerProvider')]
    #[Test]
    public function order(string|null $organization): void
    {
        $mockDnsService = self::createStub(DnsService::class);
        $dnsZone = new DnsZone(new Fqdn('test.com'));
        $dnsZone->kind = PowerDnsZoneKind::MASTER->value;
        $this->app->bind(DnsService::class, fn (): DnsService => $mockDnsService);
        $mockDnsService->method('hasDnsZone')
            ->willReturn(true);

        $mockDnsService->method('getDnsZone')
            ->willReturn($dnsZone);

        $mockDnsService->method('applyDiffToZone')
            ->willReturn($dnsZone);

        $mockDnsMigrationService = self::mock(DnsMigrationService::class);
        $this->app->bind(DnsMigrationService::class, fn (): DnsMigrationService => $mockDnsMigrationService);
        $mockDnsMigrationService->shouldReceive('changeToMasterAndEmptyMasters')
            ->andReturn();

        $mockDisablePresignedAction = self::mock(DisableZonePresigningAction::class);
        $this->app->bind(DisableZonePresigningAction::class, fn (): DisableZonePresigningAction => $mockDisablePresignedAction);
        $mockDisablePresignedAction->shouldReceive('disable')
            ->andReturn();

        $mockDomainService = self::mock(DomainService::class);
        $this->app->bind(DomainService::class, fn (): DomainService => $mockDomainService);

        $mockDomainService->shouldReceive('minimalRegister')
            ->andReturn(new RegistrationResult(DomainStatus::ACTIVE));

        $mockDomainService->shouldReceive('registrationRequiresDnsBeforeSubmission')
            ->with('test.com')
            ->andReturnFalse();

        new TemplateFactory()->createMany([
            [
                'slug' => MailPleskDetails::getTemplateSlug(),
            ],
            [
                'slug' => MailSubscriptionCreated::getTemplateSlug(),
            ],
        ]);

        /** @var string[] $payload */
        $payload = json_decode((string) file_get_contents(__DIR__ . '/data/order_payload.json'), true, 512, JSON_THROW_ON_ERROR);

        $nonIntegratedServicesServers = Server::query()->where('type', '!=', ServerType::PLESK)->get();

        $nonIntegratedServicesServers->each(function ($model): void {
            $model->delete();
        });

        $customer = new CustomerFactory()->withAddress()->createOne(['organization' => $organization]);

        $response = $this
            ->actingAsCustomer($customer)
            ->postJson($this->generateRoute('partners.order.order'), $payload)->assertOk();

        $order = Order::query()->firstOrFail();
        $domainSubscription = Subscription::whereProductName('.com')->firstOrFail();
        self::assertSame(DomainStatus::ACTIVE->value, $domainSubscription->technical_status);

        $dnsSubscription = Subscription::query()->whereProductGroupType(ProductGroupType::DNS)->where('domain', $domainSubscription->domain)->firstOrFail();
        self::assertSame(AdministrativeStatus::ACTIVE->value, $dnsSubscription->administrative_status);

        $hostingSubscription = Subscription::whereProductName('premium')->firstOrFail();
        self::assertSame(TechnicalStatus::OK->value, $hostingSubscription->technical_status);
        self::assertTrue(Subscription::whereProductName('Single Domain')->exists());

        $sslSub = Subscription::query()->whereProductGroupType(ProductGroupType::SSL)
                ->where('domain', 'test.com')
                ->first();
        self::assertInstanceOf(Subscription::class, $sslSub);

        $sslDeployment = $sslSub->sslDeployment;
        self::assertInstanceOf(SslDeployment::class, $sslDeployment);

        self::assertTrue($order->is_invoiced);
        self::assertFalse($sslDeployment->custom_csr, 'Custom csr was not provided in payload but ssl deployment says otherwise!');

        $response->assertJson([
            'transactionId'    => $order->uuid,
            'status'           => 'ok',
            'checkout_url'     => null,
        ]);
    }

    #[Test]
    public function orderWithActingAsUserSavesUuidAndMetadata(): void
    {
        $customer = new CustomerFactory()->createOne();
        $storeRequest = new CartOrderRequest();

        $serializedMock = self::createMock(Serializer::class);
        $serializedMock->expects(self::once())
            ->method('denormalize')
            ->willReturn(new CartOrder(
                'ideal',
                new CartOrderSubscription(
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                ),
                vouchers: null
            ));

        $order = new OrderFactory()->for($customer)->createOne();
        new OrderLineItemFactory()->for($order)->createOne();
        $cartSerializerFactory = self::createMock(CartSerializerFactory::class);
        $cartSerializerFactory->expects(self::once())
            ->method('get')
            ->willReturn($serializedMock);

        $orderService = self::createMock(OrderService::class);
        $orderService->expects(self::once())
            ->method('processCartToOrder')
            ->willReturn($order);

        $identitySchema = new KratosIdentity(
            Uuid::uuid4(),
            SchemaId::CUSTOMER,
            'active',
            null,
            new Traits('employee@our.company', null),
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
        );

        $authenticationManager = self::createStub(AuthenticationManager::class);
        $authenticatedCustomer = new AuthenticatedCustomer(
            $customer,
            $identitySchema,
            true,
        );

        $authenticationManager->method('getAuthenticatedCustomer')
            ->willReturn($authenticatedCustomer);

        $orderController = new OrderController(
            self::createStub(CustomerSharedPaymentService::class),
            $authenticationManager,
            $orderService,
            self::createStub(SubscriptionService::class),
            $cartSerializerFactory,
            self::createStub(CartService::class),
            self::createStub(ValidationService::class),
            self::createStub(CalculatePriceService::class),
            self::createStub(AdministrationFeesManager::class)
        );
        $orderController->order($storeRequest);

        self::assertSame($identitySchema->id->toString(), $order->ordered_by_uuid?->toString());
        self::assertSame(json_encode([
            'email' => $identitySchema->traits?->email,
            'schemaId' => 'customer',
        ], JSON_THROW_ON_ERROR), $order->ordered_by_metadata);
    }

    #[Test]
    public function orderOnCreditWithEmployeePrivileges(): void
    {
        $orderPaymentMethod = PaymentType::CREDIT->value;

        $customer = new CustomerFactory()->createOne(['payment_type' => PaymentType::DIRECT]);
        $storeRequest = new CartOrderRequest();
        $storeRequest->replace([
            'payment_method' => $orderPaymentMethod,
        ]);

        $serializedMock = self::createMock(Serializer::class);
        $serializedMock->expects(self::once())
            ->method('denormalize')
            ->willReturn(new CartOrder(
                paymentMethod: $orderPaymentMethod,
                subscriptions: new CartOrderSubscription(
                    backup: null,
                    dns: null,
                    ssl: null,
                    hosting: null,
                    extension: null,
                    vps: null,
                    other: null,
                    redirect: null,
                    vpsOs: null,
                    microsoft365: null,
                    resellerHosting: null,
                    addOn: null,
                    manualSubscription: null,
                ),
                vouchers: null,
            ));

        $order = new OrderFactory()->for($customer)->createOne();
        new OrderLineItemFactory()->for($order)->createOne();
        $cartSerializerFactory = self::createMock(CartSerializerFactory::class);
        $cartSerializerFactory->expects(self::once())
            ->method('get')
            ->willReturn($serializedMock);

        $orderService = self::createMock(OrderService::class);
        $orderService->expects(self::once())
            ->method('processCartToOrder')
            ->willReturn($order);

        $identitySchema = new KratosIdentity(
            Uuid::uuid4(),
            SchemaId::EMPLOYEE,
            'active',
            null,
            new Traits('employee@our.company', null),
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
        );

        $authenticationManager = self::createStub(AuthenticationManager::class);
        $authenticatedCustomer = new AuthenticatedCustomer(
            $customer,
            $identitySchema,
            true,
        );

        $authenticationManager->method('getAuthenticatedCustomer')
            ->willReturn($authenticatedCustomer);

        $subscriptionService = self::createMock(SubscriptionService::class);
        $subscriptionService->expects(self::once())
            ->method('dispatchProcessOrderJob');

        $paymentService = self::createMock(CustomerSharedPaymentService::class);
        $paymentService->expects(self::once())
            ->method('requiresDirectPayment')
            ->with($customer, $order, $orderPaymentMethod)
            ->willReturn(false);

        $orderController = new OrderController(
            $paymentService,
            $authenticationManager,
            $orderService,
            $subscriptionService,
            $cartSerializerFactory,
            self::createStub(CartService::class),
            self::createStub(ValidationService::class),
            self::createStub(CalculatePriceService::class),
            self::createStub(AdministrationFeesManager::class)
        );
        $orderController->order($storeRequest);
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
}
