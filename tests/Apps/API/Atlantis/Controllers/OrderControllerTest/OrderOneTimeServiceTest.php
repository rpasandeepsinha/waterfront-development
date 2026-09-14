<?php

declare(strict_types=1);

namespace Tests\Apps\API\Atlantis\Controllers\OrderControllerTest;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProviderFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\OrderController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\DNS\Actions\DisableZonePresigningAction;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\DNS\ValueObjects\Fqdn;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\DTO\RegistrationResult;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Ferry\Services\DnsMigrationService;
use Waterfront\Domain\OneTimeServices\Enums\OneTimeServiceStatus;
use Waterfront\Domain\OneTimeServices\Models\OneTimeService;
use Waterfront\Domain\Orders\Models\Order;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\PowerDnsClient\Enums\PowerDnsZoneKind;

#[CoversClass(OrderController::class)]
class OrderOneTimeServiceTest extends IntegrationTestCase
{
    private Customer $customer;

    private Product $otsProduct;

    protected function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading(false);

        $dnsGroup = new ProductGroupFactory()->dns()->createOne(['name' => ProductGroupType::DNS]);
        $dnsProduct = new ProductFactory()->for($dnsGroup)->createOne([
            'name' => 'free-dns',
            'slug' => 'free-dns',
        ]);
        new ProductPriceComponentFactory()
            ->for($dnsProduct)
            ->registration()
            ->createOne(['price' => 0]);
        new ProductPriceComponentFactory()->for($dnsProduct)->createOne([
            'type' => PriceComponentType::PROMOTION,
            'price' => 0,
        ]);

        $extensionGroup = new ProductGroupFactory()
            ->extension()
            ->createOne(['name' => 'extension']);
        $extensionProduct = new ProductFactory()->for($extensionGroup)->createOne([
            'slug' => 'extension_com',
            'name' => '.com',
        ]);
        new ProductPriceComponentFactory()
            ->for($extensionProduct)
            ->registration()
            ->createOne(['price' => 120]);
        new ProductPriceComponentFactory()->for($extensionProduct)->createOne([
            'type' => PriceComponentType::PROMOTION,
            'price' => 96,
        ]);

        $otsGroup = new ProductGroupFactory()
            ->oneTimeService()
            ->createOne(['name' => ProductGroupType::ONE_TIME_SERVICE]);
        $this->otsProduct = new ProductFactory()->for($otsGroup)->createOne([
            'name' => 'Domain reactivation',
            'slug' => 'domain-reactivation',
        ]);

        ProviderFactory::new()->createOne([
            'type' => ProviderType::DOMAIN,
            'slug' => ProviderSlug::OPEN_PROVIDER,
            'enabled' => true,
            'default' => true,
        ]);

        $this->customer = new CustomerFactory()->withAddress()->createOne();
    }

    #[Test]
    public function successfulOrderWithOts(): void
    {
        new ProductPriceComponentFactory()
            ->for($this->otsProduct)
            ->oneTimeService()
            ->registration()
            ->createOne(['price' => 120]);

        $this->mockDns();

        $json = (string) file_get_contents(__DIR__ . '/data/order_payload_ots.json');

        /** @var array<int, array<string>> $orderPayload */
        $orderPayload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $this->actingAsCustomer($this->customer)
            ->postJson($this->generateRoute('partners.order.order'), $orderPayload)
            ->assertOk();

        Order::firstOrFail();
        $subscription = Subscription::whereProductGroupType(ProductGroupType::EXTENSION)->firstOrFail();
        $oneTimeService = OneTimeService::where('customer_id', $this->customer->id)->firstOrFail();
        self::assertSame($this->otsProduct->slug, $oneTimeService->product->slug);
        self::assertSame(OneTimeServiceStatus::OPEN, $oneTimeService->status);
        self::assertSame(120, $oneTimeService->gross_price);
        self::assertSame($subscription->id, $oneTimeService->subscription->id);
    }

    #[Test]
    public function incorrectProductSlug(): void
    {
        $json = (string) file_get_contents(__DIR__ . '/data/order_payload_ots_incorrect_slug.json');

        /** @var array<int, array<string>> $orderPayload */
        $orderPayload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $this->actingAsCustomer($this->customer)
            ->postJson($this->generateRoute('partners.order.order'), $orderPayload)
            ->assertUnprocessable();
    }

    #[Test]
    public function otsWithoutPrice(): void
    {
        $json = (string) file_get_contents(__DIR__ . '/data/order_payload_ots.json');

        /** @var array<int, array<string>> $orderPayload */
        $orderPayload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $this->actingAsCustomer($this->customer)
            ->postJson($this->generateRoute('partners.order.order'), $orderPayload)
            ->assertUnprocessable();
    }

    private function mockDns(): void
    {
        $mockDnsService = self::createStub(DnsService::class);
        $dnsZone = new DnsZone(new Fqdn('test.com'));
        $dnsZone->kind = PowerDnsZoneKind::MASTER->value;
        $this->app->bind(DnsService::class, fn (): DnsService => $mockDnsService);
        $mockDnsService->method('hasDnsZone')->willReturn(true);

        $mockDnsService->method('getDnsZone')->willReturn($dnsZone);

        $mockDnsService->method('applyDiffToZone')->willReturn($dnsZone);

        $mockDnsMigrationService = self::mock(DnsMigrationService::class);
        $this->app->bind(DnsMigrationService::class, fn (): DnsMigrationService => $mockDnsMigrationService);
        $mockDnsMigrationService->shouldReceive('changeToMasterAndEmptyMasters')->andReturn();

        $mockDisablePresignedAction = self::mock(DisableZonePresigningAction::class);
        $this->app->bind(
            DisableZonePresigningAction::class,
            fn (): DisableZonePresigningAction => $mockDisablePresignedAction,
        );
        $mockDisablePresignedAction->shouldReceive('disable')->andReturn();

        $mockDomainService = self::mock(DomainService::class);
        $this->app->bind(DomainService::class, fn (): DomainService => $mockDomainService);

        $mockDomainService->shouldReceive('minimalRegister')->andReturn(new RegistrationResult(DomainStatus::ACTIVE));

        $mockDomainService
            ->shouldReceive('registrationRequiresDnsBeforeSubmission')
            ->with('test.com')
            ->andReturnFalse();
    }
}
