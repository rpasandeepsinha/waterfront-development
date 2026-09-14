<?php

declare(strict_types=1);

namespace Tests\Apps\API\Atlantis\Controllers\OrderControllerTest;

use Illuminate\Testing\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\OrderController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\OneTimeServices\Models\OneTimeService;
use Waterfront\Domain\Orders\Models\Order;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Services\ProvisionService;

#[CoversClass(OrderController::class)]
class OrderTransferServiceTest extends IntegrationTestCase
{
    private Customer $customer;

    private Product $otsProduct;

    private Product $extensionProduct;

    private Product $dnsProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $dnsGroup = new ProductGroupFactory()->dns()->createOne(['name' => ProductGroupType::DNS]);
        $this->dnsProduct = new ProductFactory()->for($dnsGroup)->createOne([
            'name' => 'free-dns',
            'slug' => 'free-dns',
        ]);
        new ProductPriceComponentFactory()
            ->for($this->dnsProduct)
            ->registration()
            ->createOne(['price' => 0]);
        new ProductPriceComponentFactory()->for($this->dnsProduct)->createOne([
            'type' => PriceComponentType::PROMOTION,
            'price' => 0,
        ]);
        ProductSpecFactory::new()
            ->enable(ProductSpecName::DNS_CAN_COUPLE_HOSTING_OR_REDIRECT)
            ->for($this->dnsProduct)
            ->create();

        $extensionGroup = new ProductGroupFactory()
            ->extension()
            ->createOne(['name' => 'extension']);
        $this->extensionProduct = new ProductFactory()->for($extensionGroup)->createOne([
            'slug' => 'extension_com',
            'name' => '.com',
        ]);
        new ProductPriceComponentFactory()
            ->for($this->extensionProduct)
            ->registration()
            ->createOne(['price' => 120]);
        new ProductPriceComponentFactory()->for($this->extensionProduct)->createOne([
            'type' => PriceComponentType::PROMOTION,
            'price' => 96,
        ]);

        $otsGroup = new ProductGroupFactory()
            ->oneTimeService()
            ->createOne(['name' => ProductGroupType::ONE_TIME_SERVICE]);
        $this->otsProduct = new ProductFactory()->for($otsGroup)->createOne([
            'name' => 'transfer_service',
            'slug' => 'transfer_service',
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
        $hostingBrons = new ProductFactory()->hostingBrons()->createOne();
        $hostingPremium = new ProductFactory()->for($hostingBrons->productGroup)->createOne([
            'slug' => 'hosting_premium',
        ]);

        ProductSpecFactory::new()->createOne(
            [
                'name' => ProductSpecName::HAS_SERVICE_PLUS->value,
                'value' => '1',
                'product_id' => $hostingPremium->id,
            ],
        );

        new ProductPriceComponentFactory()
            ->for($hostingPremium)
            ->registration()
            ->createOne([
                'billing_period' => 12,
                'contract_period' => 12,
                'price' => 120,
            ]);

        new ProductPriceComponentFactory()
            ->for($hostingBrons)
            ->registration()
            ->createOne([
                'billing_period' => 12,
                'contract_period' => 12,
                'price' => 120,
            ]);

        new ProductPriceComponentFactory()
            ->for($this->otsProduct)
            ->oneTimeService()
            ->registration()
            ->createOne(['price' => 7500]);

        new SubscriptionFactory()
            ->administrativeStatusActive()
            ->for($this->customer)
            ->for($this->extensionProduct)
            ->createOne([
                'domain' => 'already-existing-domain.com',
            ]);

        new SubscriptionFactory()
            ->administrativeStatusActive()
            ->for($this->dnsProduct)
            ->for($this->customer)
            ->createOne([
                'domain' => 'already-existing-domain.com',
            ]);

        $this->app->bind(ProvisionService::class, fn () => self::createStub(ProvisionService::class));

        $json = (string) file_get_contents(__DIR__ . '/data/order_payload_transfer_service.json');

        /** @var array<int, array<string>> $orderPayload */
        $orderPayload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $response = $this->withoutExceptionHandling()
            ->actingAsCustomer($this->customer)
            ->postJson($this->generateRoute('partners.order.order'), $orderPayload);
        $response->assertOk();

        $order = Order::firstOrFail();

        Assert::assertCount(6, $order->lineItems);

        $hostingBronsOrderLine = $order->lineItems()->where('product_uuid', $hostingBrons->uuid)->firstOrFail();
        $transferBronsLine = $order
            ->lineItems()
            ->where('product_uuid', $this->otsProduct->uuid)
            ->where('parent_id', $hostingBronsOrderLine->id)
            ->firstOrFail();
        $hostingPremiumOrderLine = $order->lineItems()->where('product_uuid', $hostingPremium->uuid)->firstOrFail();
        $transferPremiumLine = $order
            ->lineItems()
            ->where('product_uuid', $this->otsProduct->uuid)
            ->where('parent_id', $hostingPremiumOrderLine->id)
            ->firstOrFail();

        self::assertSame(7500, $transferBronsLine->net_price);
        self::assertSame(0, $transferPremiumLine->net_price);

        $otsBrons = OneTimeService::where('id', $transferBronsLine->one_time_service_id)->firstOrFail();
        $otsPremium = OneTimeService::where('id', $transferPremiumLine->one_time_service_id)->firstOrFail();

        self::assertSame(7500, $otsBrons->gross_price);
        self::assertSame(0, $otsBrons->discount_percentage);

        self::assertSame(7500, $otsPremium->gross_price);
        self::assertSame(100, $otsPremium->discount_percentage);

        $invoiceLineBrons = Invoice::where('product_id', $this->otsProduct->id)
            ->where('subscription_id', $hostingBronsOrderLine->subscription?->id)
            ->firstOrFail();
        $invoiceLinePremium = Invoice::where('product_id', $this->otsProduct->id)
            ->where('subscription_id', $hostingPremiumOrderLine->subscription?->id)
            ->firstOrFail();

        self::assertSame(7500, $invoiceLineBrons->gross_price);
        self::assertSame(7500, $invoiceLineBrons->net_price);

        self::assertSame(7500, $invoiceLinePremium->gross_price);
        self::assertSame(0, $invoiceLinePremium->net_price);
    }
}
