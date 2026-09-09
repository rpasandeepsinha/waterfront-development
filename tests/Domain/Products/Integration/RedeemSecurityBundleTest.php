<?php

declare(strict_types=1);

namespace Tests\Domain\Products\Integration;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductAllowedChangeFactory;
use Tests\Factories\ProductExperimentOfferingsFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\SecurityBundleController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Orders\Models\Order;
use Waterfront\Domain\Orders\Models\OrderLineItem;
use Waterfront\Domain\Products\Enums\ProductType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Jobs\ChangeProvisioningJob;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\ProvisionService;

#[CoversClass(SecurityBundleController::class)]
class RedeemSecurityBundleTest extends IntegrationTestCase
{
    private string $redeemRoute;

    private Customer $customer;

    private Product $premiumDnsProduct;

    private Product $acronisProduct;

    private Product $freeDnsProduct;

    protected function setUp(): void
    {
        parent::setUp();
        Model::preventLazyLoading(false);

        Bus::fake([ChangeProvisioningJob::class]);
        $this->app->bind(ProvisionService::class, fn (): ProvisionService => self::createStub(ProvisionService::class));

        $this->redeemRoute = $this->generateRoute('partners.security-bundle.redeem');
        $this->customer = new CustomerFactory()->withAddress()->createOne();

        $dnsProductGroup = new ProductGroupFactory()->dns()->createOne();
        $this->premiumDnsProduct = new ProductFactory()->for($dnsProductGroup)->createOne(['slug' => ProductType::PREMIUM_DNS->value]);
        $this->freeDnsProduct = new ProductFactory()->for($dnsProductGroup)->createOne(['slug' => ProductType::FREE_DNS->value]);
        $this->acronisProduct = new ProductFactory()->backupAcronis()->createOne();

        foreach ([$this->premiumDnsProduct, $this->acronisProduct] as $product) {
            new ProductPriceComponentFactory()->for($product)->registration()->createOne([
                'billing_period'  => 12,
                'contract_period' => 12,
                'price'           => 500,
            ]);
        }

        new ProductAllowedChangeFactory()
            ->upgradeChange()
            ->createOne([
                'from_product_id' => $this->freeDnsProduct->id,
                'to_product_id'   => $this->premiumDnsProduct->id,
            ]);
    }

    #[Test]
    public function everyFreeDnsSubscriptionUpgradedToPremiumDns(): void
    {
        $this->offering(acronisFree: true);

        $firstDnsSubscription = $this->dnsSubscription('first-domain.nl');
        $secondDnsSubscription = $this->dnsSubscription('second-domain.nl');
        $thirdDnsSubscription = $this->dnsSubscription('second-domain.nl');

        $this->actingAsCustomer($this->customer);

        $this->postJson($this->redeemRoute, [
            'products' => [ProductType::PREMIUM_DNS->value],
        ])->assertStatus(Response::HTTP_NO_CONTENT);

        $firstDnsSubscription->refresh();
        $secondDnsSubscription->refresh();
        $thirdDnsSubscription->refresh();

        self::assertSame($this->premiumDnsProduct->uuid, $firstDnsSubscription->product_uuid);
        self::assertSame($this->premiumDnsProduct->uuid, $secondDnsSubscription->product_uuid);
        self::assertSame($this->premiumDnsProduct->uuid, $thirdDnsSubscription->product_uuid);

        // net_price is what every following billing cycle is charged.
        self::assertSame(0, $firstDnsSubscription->net_price);
        self::assertSame(0, $secondDnsSubscription->net_price);

        // Zero-priced lines are marked not to invoice, so the biller produces nothing at all for them.
        foreach ($this->orderLines() as $orderLine) {
            self::assertFalse($orderLine->should_invoice);
            self::assertSame(0, $orderLine->net_price);
        }

        self::assertSame(0, Invoice::query()->where('customer_id', $this->customer->id)->count());
    }

    #[Test]
    public function suspendedDnsSubscriptionsAreLeftAlone(): void
    {
        $this->offering(acronisFree: true);

        $activeDnsSubscription = $this->dnsSubscription('active-domain.nl');
        $suspendedDnsSubscription = $this->suspendedDnsSubscription('suspended-domain.nl');

        $this->actingAsCustomer($this->customer);

        $this->postJson($this->redeemRoute, [
            'products' => [ProductType::PREMIUM_DNS->value],
        ])->assertStatus(Response::HTTP_NO_CONTENT);

        $activeDnsSubscription->refresh();
        $suspendedDnsSubscription->refresh();

        self::assertSame($this->premiumDnsProduct->uuid, $activeDnsSubscription->product_uuid);

        self::assertSame($this->freeDnsProduct->uuid, $suspendedDnsSubscription->product_uuid);
        self::assertCount(1, $this->orderLines());
    }

    #[Test]
    public function freeBackUpBecomesAFreeSubscription(): void
    {
        $this->offering(acronisFree: true);

        $this->actingAsCustomer($this->customer);

        $this->postJson($this->redeemRoute, [
            'products' => [$this->acronisProduct->slug],
        ])->assertStatus(Response::HTTP_NO_CONTENT);

        $backupSubscription = Subscription::query()
            ->where('customer_id', $this->customer->id)
            ->where('product_uuid', $this->acronisProduct->uuid)
            ->sole();

        self::assertSame(0, $backupSubscription->net_price);

        $orderLine = $this->orderLines()->sole();

        self::assertFalse($orderLine->should_invoice);
        self::assertSame(0, $orderLine->net_price);
        self::assertSame(0, Invoice::query()->where('customer_id', $this->customer->id)->count());
    }

    #[Test]
    public function paidBackUpIsChargedRegularPrice(): void
    {
        $this->offering(acronisFree: false);

        $this->actingAsCustomer($this->customer);

        $this->postJson($this->redeemRoute, [
            'products' => [$this->acronisProduct->slug],
        ])->assertStatus(Response::HTTP_NO_CONTENT);

        $backupSubscription = Subscription::query()
            ->where('customer_id', $this->customer->id)
            ->where('product_uuid', $this->acronisProduct->uuid)
            ->sole();

        self::assertSame(500, $backupSubscription->net_price);

        $invoice = Invoice::query()->where('customer_id', $this->customer->id)->sole();

        self::assertSame($this->acronisProduct->id, $invoice->product_id);
        self::assertSame($backupSubscription->id, $invoice->subscription_id);
        self::assertSame(500, $invoice->net_price);
        self::assertSame(500, $invoice->gross_price);
        self::assertFalse($invoice->paid);
    }

    #[Test]
    public function paidBackupIsInvoicedWhilePremiumDnsIsNot(): void
    {
        $this->offering(acronisFree: false);

        $dnsSubscription = $this->dnsSubscription('mixed-domain.nl');

        $this->actingAsCustomer($this->customer);

        $this->postJson($this->redeemRoute, [
            'products' => [ProductType::PREMIUM_DNS->value, $this->acronisProduct->slug],
        ])->assertStatus(Response::HTTP_NO_CONTENT);

        $dnsSubscription->refresh();

        self::assertSame($this->premiumDnsProduct->uuid, $dnsSubscription->product_uuid);
        self::assertSame(0, $dnsSubscription->net_price);

        $invoice = Invoice::query()->where('customer_id', $this->customer->id)->sole();

        self::assertSame($this->acronisProduct->id, $invoice->product_id);
        self::assertSame(500, $invoice->net_price);
        self::assertFalse($invoice->paid);

        self::assertSame(
            0,
            Invoice::query()
                ->where('customer_id', $this->customer->id)
                ->where('product_id', $this->premiumDnsProduct->id)
                ->count()
        );
    }

    #[Test]
    public function offeringCannotRedeemedTwice(): void
    {
        $this->offering(acronisFree: true);

        $this->actingAsCustomer($this->customer);

        $this->postJson($this->redeemRoute, [
            'products' => [$this->acronisProduct->slug],
        ])->assertStatus(Response::HTTP_NO_CONTENT);

        // Says the offer is spent, rather than the generic "selected value is invalid".
        $this->postJson($this->redeemRoute, [
            'products' => [$this->acronisProduct->slug],
        ])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['products' => 'security-bundle.already-redeemed']);

        self::assertSame(1, Order::query()->where('customer_id', $this->customer->id)->count());
        self::assertSame(
            1,
            Subscription::query()
                ->where('customer_id', $this->customer->id)
                ->where('product_uuid', $this->acronisProduct->uuid)
                ->count()
        );
    }

    #[Test]
    public function claimingOnlyTheBackupForfeitsThePremiumDns(): void
    {
        $this->offering(acronisFree: true);

        $dnsSubscription = $this->dnsSubscription('forfeited-domain.nl');

        $this->actingAsCustomer($this->customer);

        $this->postJson($this->redeemRoute, [
            'products' => [$this->acronisProduct->slug],
        ])->assertStatus(Response::HTTP_NO_CONTENT);

        $this->postJson($this->redeemRoute, [
            'products' => [ProductType::PREMIUM_DNS->value],
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $dnsSubscription->refresh();

        self::assertSame($this->freeDnsProduct->uuid, $dnsSubscription->product_uuid);
    }

    #[Test]
    public function customerThatIsNotEliglble(): void
    {
        $this->actingAsCustomer($this->customer);

        $this->postJson($this->redeemRoute, [
            'products' => [$this->acronisProduct->slug],
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        self::assertSame(0, Order::query()->where('customer_id', $this->customer->id)->count());
    }

    #[Test]
    public function anEmptyProductListIsRejected(): void
    {
        $this->offering(acronisFree: true);

        $this->actingAsCustomer($this->customer);

        $this->postJson($this->redeemRoute, ['products' => []])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function offering(bool $acronisFree): void
    {
        new ProductExperimentOfferingsFactory()
            ->offeringFirstProduct($this->premiumDnsProduct, free: true)
            ->offeringSecondProduct($this->acronisProduct, free: $acronisFree)
            ->withCustomer($this->customer)
            ->createOne();
    }

    /**
     * @return Collection<int, OrderLineItem>
     */
    private function orderLines(): Collection
    {
        return OrderLineItem::query()
            ->whereHas('order', fn ($query) => $query->where('customer_id', $this->customer->id))
            ->get();
    }

    private function suspendedDnsSubscription(string $domain): Subscription
    {
        return new SubscriptionFactory()
            ->forDomain($domain)
            ->administrativeStatus(AdministrativeStatus::SUSPENDED->value)
            ->withPrice()
            ->createOne([
                'customer_id'  => $this->customer->id,
                'product_uuid' => $this->freeDnsProduct->uuid,
            ]);
    }

    private function dnsSubscription(string $domain): Subscription
    {
        return new SubscriptionFactory()
            ->forDomain($domain)
            ->administrativeStatusActive()
            ->withPrice()
            ->createOne([
                'customer_id'  => $this->customer->id,
                'product_uuid' => $this->freeDnsProduct->uuid,
            ]);
    }
}
