<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Cart;

use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainContactFactory;
use Tests\Factories\InvoiceFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\VoucherFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\OrderController;
use Waterfront\Domain\Customers\Enums\PaymentType;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\DNS\Events\CreateDns;
use Waterfront\Domain\Domains\DTO\CheckResult;
use Waterfront\Domain\Domains\Events\CreateDomain;
use Waterfront\Domain\Domains\Models\DomainContact;
use Waterfront\Domain\Experiment\Enums\ExperimentType;
use Waterfront\Domain\Experiment\Models\Experiment;
use Waterfront\Domain\Orders\Models\OrderLineItem;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Services\ProvisionService;
use Waterfront\Infra\RtrClient\Services\RtrService;

#[CoversClass(OrderController::class)]
class OrderControllerTest extends IntegrationTestCase
{
    private Product $nlProduct;

    private DomainContact $domainContact;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        new ProviderFactory()->domainPlaceholder()->createOne();
        $this->nlProduct = new ProductFactory()->nlDomain()->createOne();
        new ProductPriceComponentFactory()
            ->for($this->nlProduct)
            ->registration()
            ->createOne();

        $dnsProduct = new ProductFactory()->freeDns()->createOne();
        new ProductPriceComponentFactory()
            ->for($dnsProduct)
            ->registration()
            ->createOne();

        $this->customer = new CustomerFactory()->withAddress()->createOne();
        $this->domainContact = DomainContactFactory::new()->for($this->customer)->createOne();
    }

    #[Test]
    public function orderWithVoucherSuccessful(): void
    {
        ProviderFactory::new()->createOne([
            'type' => ProviderType::DOMAIN,
            'enabled' => true,
            'default' => true,
            'slug' => ProviderSlug::REALTIME_REGISTER,
        ]);

        Event::fake(
            [
                CreateDns::class,
                CreateDomain::class,
            ],
        );

        $rtrMock = self::mock(RtrService::class);

        $rtrMock->shouldReceive('check')->with('test.nl')->andReturn(new CheckResult('test.nl', 'free'));

        $rtrMock->shouldReceive('setHandle')->andReturnSelf();

        $rtrMock->shouldReceive('setClient')->andReturnSelf();

        $this->app->bind(RtrService::class, fn () => $rtrMock);

        new VoucherFactory()->for($this->nlProduct->productGroup)->createOne(['code' => 'dit-is-een-voucher']);

        $json = (string) file_get_contents(__DIR__ . '/data/order_payload.json');

        $payload = json_decode($json, true);
        self::assertIsArray($payload);
        $payload['subscriptions']['extension'][0]['contact_id'] = $this->domainContact->id;

        $this->actingAsCustomer($this->customer)
            ->postJson($this->generateRoute('partners.order.order'), $payload)
            ->assertOk();
    }

    #[Test]
    public function orderWithoutVoucherSuccessful(): void
    {
        $this->customer = new CustomerFactory()->withAddress()->createOne();
        ProviderFactory::new()->createOne([
            'type' => ProviderType::DOMAIN,
            'enabled' => true,
            'default' => true,
            'slug' => ProviderSlug::REALTIME_REGISTER,
        ]);

        Event::fake(
            [
                CreateDns::class,
                CreateDomain::class,
            ],
        );

        $rtrMock = self::mock(RtrService::class);

        $rtrMock->shouldReceive('check')->with('test.nl')->andReturn(new CheckResult('test.nl', 'free'));

        $rtrMock->shouldReceive('setHandle')->andReturnSelf();

        $rtrMock->shouldReceive('setClient')->andReturnSelf();

        $this->app->bind(RtrService::class, fn () => $rtrMock);

        DomainContactFactory::new()->for($this->customer)->createOne();

        $json = (string) file_get_contents(__DIR__ . '/data/order_payload_without_voucher.json');

        $payload = json_decode($json, true);
        self::assertIsArray($payload);
        $payload['subscriptions']['extension'][0]['contact_id'] = $this->domainContact->id;

        $this->actingAsCustomer($this->customer)
            ->postJson($this->generateRoute('partners.order.order'), $payload)
            ->assertOk();
    }

    #[Test]
    public function creditLimitIsSurpassed(): void
    {
        $this->customer = new CustomerFactory()
            ->withAddress()
            ->createOne(['credit_limit' => 1, 'payment_type' => PaymentType::DIRECT]);
        ProviderFactory::new()->createOne([
            'type' => ProviderType::DOMAIN,
            'enabled' => true,
            'default' => true,
            'slug' => ProviderSlug::REALTIME_REGISTER,
        ]);

        Event::fake(
            [
                CreateDns::class,
                CreateDomain::class,
            ],
        );

        $rtrMock = self::mock(RtrService::class);

        $rtrMock->shouldReceive('check')->with('test.nl')->andReturn(new CheckResult('test.nl', 'free'));

        $rtrMock->shouldReceive('setHandle')->andReturnSelf();

        $rtrMock->shouldReceive('setClient')->andReturnSelf();

        $this->app->bind(RtrService::class, fn () => $rtrMock);

        new InvoiceFactory()
            ->for($this->customer)
            ->for($this->nlProduct)
            ->createOne([
                'net_price' => 30000,
            ]);
        $this->customer->refresh();

        $json = (string) file_get_contents(__DIR__ . '/data/order_payload.json');
        $payload = json_decode($json, true);
        assert(is_array($payload));

        $this->actingAsCustomer($this->customer)
            ->postJson($this->generateRoute('partners.order.order'), $payload)
            ->assertJsonFragment([
                'message' => 'Customer has not enough disposable credit available.',
                'errors' => ['total_price' => ['Customer has not enough disposable credit available.']],
            ]);
    }

    #[Test]
    public function experimentSlugIsPersistedAsOrderLineProperty(): void
    {
        new ProviderFactory()->createOne([
            'type' => ProviderType::DOMAIN,
            'enabled' => true,
            'default' => true,
            'slug' => ProviderSlug::REALTIME_REGISTER,
        ]);

        new ProductPriceComponentFactory()
            ->for($this->nlProduct)
            ->registration()
            ->createOne([
                'price' => 100,
                'billing_period' => 12,
                'contract_period' => 12,
            ]);

        $experimentNl = new Experiment();
        $experimentNl->slug = ExperimentType::PRICING_LADDER;
        $experimentNl->save();

        $experimentNl->id = 1234;
        $experimentNl->save();

        $experimentNl->products()->save($this->nlProduct);

        $rtrMock = self::createStub(RtrService::class);
        $this->app->bind(RtrService::class, fn () => $rtrMock);

        $rtrMock = self::createStub(ProvisionService::class);
        $this->app->bind(ProvisionService::class, fn () => $rtrMock);

        $json = (string) file_get_contents(__DIR__ . '/data/order_payload_with_experiment_slug.json');
        $payload = json_decode($json, true);
        assert(is_array($payload));

        $this->actingAsCustomer($this->customer)
            ->postJson($this->generateRoute('partners.order.order'), $payload)
            ->assertOk();

        $nlProductOrderLine = OrderLineItem::where(['product_uuid' => $this->nlProduct->uuid])->firstOrFail();
        self::assertSame('pricing-ladder', $nlProductOrderLine->experiment_slug);
    }
}
