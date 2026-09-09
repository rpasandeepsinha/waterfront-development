<?php

declare(strict_types=1);

namespace Tests\Apps\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductDiscountFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Console\Commands\Subscriptions\CreateSubscriptionInvoices;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Infra\Common\DateTimeFormat;
use Waterfront\Infra\Queue\HarborQueue;

#[CoversClass(CreateSubscriptionInvoices::class)]
class CreateSubscriptionInvoicesTest extends IntegrationTestCase
{
    private const string DOMAIN = 'test-domain.testing';

    private Customer $customer;

    private Product $product;

    private ProductPriceComponent $nlRegistrationPrice;

    public function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading(false);

        CarbonImmutable::setTestNow('2021-12-12 12:00:00');

        $harborQueue = self::createStub(HarborQueue::class);
        $this->app->bind(HarborQueue::class, fn (): HarborQueue => $harborQueue);

        $this->customer = new CustomerFactory()->withAddress()->createOne();

        new ProductDiscountFactory()->createOne([
            'name' => 'Domain discount',
            'description' => 'Domain discount',
        ]);

        $productGroup = new ProductGroupFactory()->extension()->createOne();

        $this->product = new ProductFactory()->for($productGroup)->createOne([
            'name' => '.nl',
            'slug' => 'extension_nl',
            'description' => '.nl domein',
            'weight' => 1,
        ]);

        $this->nlRegistrationPrice = new ProductPriceComponentFactory()->for($this->product)->registration()->createOne(['price' => 1099]);
        new ProductPriceComponentFactory()->for($this->product)->introduction()->createOne(['price' => 49]);
        new ProductPriceComponentFactory()->for($this->product)->createOne(['type' => PriceComponentType::PROMOTION, 'price' => 49]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    /**
     * This test replicates a situation we saw on production, so we don't encounter this problem again.
     *
     * @see https://yh-jira.atlassian.net/browse/WATER-4868
     */
    #[Test]
    public function canceledYearlySubscriptionDoesntResultInInvoice(): void
    {
        new SubscriptionFactory()
            ->for($this->product)
            ->for($this->customer)
            ->createOneQuietly([
                'domain' => self::DOMAIN,
                'net_price' => 99, // old price before it was changed
                'gross_price' => 999, // old price before it was changed
                'technical_status' => DomainStatus::ACTIVE->value,
                'administrative_status' => AdministrativeStatus::CANCELED->value,
                'cancel_date' => '2022-04-14',
                'start_date' => '2021-12-12',
                'end_date' => '2022-12-12',
                'next_billing_date' => '2022-12-12',
                'billing_period' => 12,
                'contract_period' => 12,
            ]);

        // Date when create invoice is run
        CarbonImmutable::setTestNow('2022-12-06 00:00:00');

        $this->artisan(CreateSubscriptionInvoices::class);

        self::assertSame(0, Invoice::count());
    }

    #[Test]
    public function canceledMonthlySubscriptionHasInvoice(): void
    {
        $this->nlRegistrationPrice->billing_period = 1;
        $this->nlRegistrationPrice->save();

        $subscription = new SubscriptionFactory()
            ->for($this->product)
            ->for($this->customer)
            ->withPrice()
            ->createOneQuietly([
                'domain' => self::DOMAIN,
                'net_price' => 99, // old price before it was changed
                'gross_price' => 999, // old price before it was changed
                'technical_status' => DomainStatus::ACTIVE->value,
                'administrative_status' => AdministrativeStatus::CANCELED->value,
                'cancel_date' => '2022-04-14',
                'start_date' => '2021-12-12',
                'end_date' => '2022-12-12',
                'next_billing_date' => '2022-06-12',
                'billing_period' => 1,
                'contract_period' => 12,
            ]);

        // Date when create invoice is run
        CarbonImmutable::setTestNow('2022-06-06 00:00:00');

        $this->artisan(CreateSubscriptionInvoices::class);

        self::assertSame(1, Invoice::query()->count());

        $invoice = Invoice::firstOrFail();

        self::assertSame($subscription->id, $invoice->subscription_id);
        self::assertSame('2022-06-12', $invoice->start_date->format(DateTimeFormat::DATE));
        self::assertSame('2022-07-12', $invoice->end_date->format(DateTimeFormat::DATE));
        self::assertSame(99, $invoice->net_price);
    }

    #[Test]
    public function activeMonthlySubscriptionHasInvoice(): void
    {
        $this->nlRegistrationPrice->billing_period = 1;
        $this->nlRegistrationPrice->save();

        $subscription = new SubscriptionFactory()
            ->for($this->product)
            ->for($this->customer)
            ->withPrice()
            ->createOneQuietly([
                'domain' => self::DOMAIN,
                'net_price' => 99, // old price before it was changed
                'gross_price' => 999, // old price before it was changed
                'technical_status' => DomainStatus::ACTIVE->value,
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
                'cancel_date' => null,
                'start_date' => '2021-12-12',
                'end_date' => '2022-12-12',
                'next_billing_date' => '2022-06-12',
                'billing_period' => 1,
                'contract_period' => 12,
            ]);

        // Date when create invoice is run
        CarbonImmutable::setTestNow('2022-06-06 00:00:00');

        $this->artisan(CreateSubscriptionInvoices::class);

        self::assertSame(1, Invoice::query()->count());

        $invoice = Invoice::firstOrFail();

        self::assertSame($subscription->id, $invoice->subscription_id);
        self::assertSame('2022-06-12', $invoice->start_date->format(DateTimeFormat::DATE));
        self::assertSame('2022-07-12', $invoice->end_date->format(DateTimeFormat::DATE));
        self::assertSame(99, $invoice->net_price);
    }

    #[Test]
    public function activeYearlySubscriptionHasProlongationInvoice(): void
    {
        $subscription = new SubscriptionFactory()
            ->for($this->product)
            ->for($this->customer)
            ->withPrice()
            ->createOneQuietly([
                'domain' => self::DOMAIN,
                'net_price' => 1099,
                'gross_price' => 1099,
                'technical_status' => DomainStatus::ACTIVE->value,
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
                'cancel_date' => null,
                'start_date' => '2021-12-12',
                'end_date' => '2023-12-12',
                'next_billing_date' => '2022-12-12',
                'billing_period' => 12,
                'contract_period' => 12,
            ]);

        // Date when create invoice is run
        CarbonImmutable::setTestNow('2022-12-13 00:00:00');

        $this->artisan(CreateSubscriptionInvoices::class);

        self::assertSame(1, Invoice::query()->count());

        $invoice = Invoice::firstOrFail();

        self::assertSame($subscription->id, $invoice->subscription_id);
        self::assertSame('2022-12-12', $invoice->start_date->format(DateTimeFormat::DATE));
        self::assertSame('2023-12-12', $invoice->end_date->format(DateTimeFormat::DATE));
        self::assertSame(1099, $invoice->net_price);
        self::assertNotNull($invoice->sent_to_harbor_at);
        self::assertSame('2022-12-13', $invoice->sent_to_harbor_at->format(DateTimeFormat::DATE));
    }
}
