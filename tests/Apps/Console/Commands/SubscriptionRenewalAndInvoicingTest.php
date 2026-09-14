<?php

declare(strict_types=1);

namespace Tests\Apps\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProviderFactory;
use Tests\Infra\PowerDnsClient\PowerDnsMockHelper;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Console\Commands\Subscriptions\CreateSubscriptionInvoices;
use Waterfront\Apps\Console\Commands\Subscriptions\RenewSubscriptions;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\PowerDnsClient\Entities\PowerDnsSecKeySet;
use Waterfront\Infra\PowerDnsClient\Entities\PowerDnsZone;
use Waterfront\Infra\PowerDnsClient\Services\PowerDnsZoneToDnsZoneConverter;
use Waterfront\Infra\Queue\HarborQueue;

#[CoversClass(RenewSubscriptions::class)]
#[CoversClass(CreateSubscriptionInvoices::class)]
class SubscriptionRenewalAndInvoicingTest extends IntegrationTestCase
{
    use PowerDnsMockHelper;

    private Customer $customer;

    private Product $domainProduct;

    private Product $dnsProduct;

    public function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading(false);

        $harborQueue = self::createStub(HarborQueue::class);
        $this->app->bind(HarborQueue::class, fn (): HarborQueue => $harborQueue);

        $this->customer = new CustomerFactory()->withAddress()->createOne();
        $this->customer->has_direct_debit = true;
        ProviderFactory::new()->createOne([
            'type' => ProviderType::DOMAIN,
            'slug' => ProviderSlug::OPEN_PROVIDER,
            'enabled' => true,
            'default' => true,
        ]);
        $productGroup = new ProductGroupFactory()->extension()->createOne();
        $this->domainProduct = new ProductFactory()->for($productGroup)->createOne([
            'slug' => 'extension_com',
            'name' => '.com',
        ]);

        $dnsGroup = new ProductGroupFactory()->createOne([
            'name' => ProductGroupType::DNS,
            'slug' => ProductGroupType::DNS,
        ]);

        $this->dnsProduct = new ProductFactory()->createOne([
            'product_group_id' => $dnsGroup->id,
            'name' => ProductType::FREE_DNS->value,
            'slug' => ProductType::FREE_DNS->value,
        ]);
    }

    /**
     *
     *
     * @param array<array<int, CarbonImmutable>> $expectedInvoiceDates
     */
    #[DataProvider('orderDataProvider')]
    #[Test]
    public function renewsCorrectSubscriptionsAndCreatesInvoices(
        CarbonImmutable $orderDate,
        int $contractPeriod,
        int $billingPeriod,
        array $expectedInvoiceDates,
        CarbonImmutable $expectedSubscriptionEndDate,
        CarbonImmutable $expectedNextBillingDate,
    ): void {
        $this->travelTo($orderDate);

        $this->placeOrder($contractPeriod, $billingPeriod);

        $subscription = Subscription::whereHas(
            'product',
            fn ($query) => $query->where('slug', 'extension_com'),
        )->firstOrFail();

        $child = $subscription->children->firstOrFail();

        $totalBillingCycles = $contractPeriod / $billingPeriod;

        $this->checkSubscriptionDates($subscription, $contractPeriod, $billingPeriod);
        $this->checkSubscriptionDates($child, $contractPeriod, $billingPeriod);

        $this->artisan(RenewSubscriptions::class);
        $this->artisan(CreateSubscriptionInvoices::class);
        $subscription->refresh();
        $child->refresh();

        // Nothing should change, because the order is placed today. Just a sanity check.
        $this->checkSubscriptionDates($subscription, $contractPeriod, $billingPeriod);
        $this->checkSubscriptionDates($child, $contractPeriod, $billingPeriod);

        for ($currentBillingCycle = 1; $currentBillingCycle < $totalBillingCycles; $currentBillingCycle++) {
            $this->travel($billingPeriod)->months();

            [$expectedInvoiceStartDate, $expectedInvoiceEndDate] = $expectedInvoiceDates[$currentBillingCycle];

            $this->artisan(RenewSubscriptions::class);
            $this->artisan(CreateSubscriptionInvoices::class);
            $subscription->refresh();
            $child->refresh();

            self::assertSame($orderDate->toDateString(), $subscription->start_date->toDateString());
            self::assertSame(
                $orderDate->addMonths($contractPeriod)->toDateString(),
                $subscription->end_date->toDateString(),
            );
            self::assertSame($orderDate->toDateString(), $child->start_date->toDateString());
            self::assertSame($orderDate->addMonths($contractPeriod)->toDateString(), $child->end_date->toDateString());

            if ($currentBillingCycle < ($totalBillingCycles - 1)) {
                self::assertSame(
                    CarbonImmutable::now()->addMonths($billingPeriod)->toDateString(),
                    $subscription->next_billing_date->toDateString(),
                );
                self::assertSame(
                    CarbonImmutable::now()->addMonths($billingPeriod)->toDateString(),
                    $child->next_billing_date->toDateString(),
                );
            } else {
                self::assertSame(
                    $subscription->end_date->toDateString(),
                    $subscription->next_billing_date->toDateString(),
                );
                self::assertSame($child->end_date->toDateString(), $child->next_billing_date->toDateString());
            }

            if ($subscription->invoices()->count() == ($currentBillingCycle + 1)) {
                self::assertSame($subscription->invoices()->count(), $currentBillingCycle + 1);
                self::assertSame($child->invoices()->count(), $currentBillingCycle + 1);

                $invoice = $subscription->invoices()->orderBy('start_date', 'desc')->firstOrFail();
                self::assertSame($expectedInvoiceStartDate->toDateString(), $invoice->start_date->toDateString());
                self::assertSame($expectedInvoiceEndDate->toDateString(), $invoice->end_date->toDateString());

                $invoice = $child->invoices()->orderBy('start_date', 'desc')->firstOrFail();
                self::assertSame($expectedInvoiceStartDate->toDateString(), $invoice->start_date->toDateString());
                self::assertSame($expectedInvoiceEndDate->toDateString(), $invoice->end_date->toDateString());
            }
        }

        // The subscription renews and a new invoice cycle starts.
        $this->travel($billingPeriod)->months();

        $this->artisan(RenewSubscriptions::class);
        $this->artisan(CreateSubscriptionInvoices::class);
        $subscription->refresh();
        $child->refresh();

        self::assertSame($orderDate->toDateString(), $subscription->start_date->toDateString());
        self::assertSame($expectedSubscriptionEndDate->toDateString(), $subscription->end_date->toDateString());
        self::assertSame($expectedNextBillingDate->toDateString(), $subscription->next_billing_date->toDateString());

        self::assertSame($orderDate->toDateString(), $child->start_date->toDateString());
        self::assertSame($expectedSubscriptionEndDate->toDateString(), $child->end_date->toDateString());
        self::assertSame($expectedNextBillingDate->toDateString(), $child->next_billing_date->toDateString());

        $invoice = $subscription->invoices()->orderBy('start_date', 'desc')->firstOrFail();
        /** @var array<CarbonImmutable> $expectedLastInvoiceDates */
        $expectedLastInvoiceDates = end($expectedInvoiceDates);

        self::assertCount($subscription->invoices()->count(), $expectedInvoiceDates);
        self::assertSame($expectedLastInvoiceDates[0]->toDateString(), $invoice->start_date->toDateString());
        self::assertSame($expectedLastInvoiceDates[1]->toDateString(), $invoice->end_date->toDateString());

        $invoice = $subscription->invoices()->orderBy('start_date', 'desc')->firstOrFail();
        self::assertCount($child->invoices()->count(), $expectedInvoiceDates);
        self::assertSame($expectedLastInvoiceDates[0]->toDateString(), $invoice->start_date->toDateString());
        self::assertSame($expectedLastInvoiceDates[1]->toDateString(), $invoice->end_date->toDateString());
    }

    /**
     * The simulation will run from the moment the order is placed until a full contract period and one billing period in months have elapsed.
     * This means that the subscription has been renewed once and 'contract_period / billing_period + 1' invoices have been created.
     *
     * @return array<mixed>
     */
    public static function orderDataProvider(): array
    {
        return [
            'Ordered 2022-01-01 with a contract period of 12 and a billing period of 4' => [
                new CarbonImmutable('2022-01-01'),
                12,
                4,
                [
                    [new CarbonImmutable('2022-05-01'), new CarbonImmutable('2022-09-01')],
                    [new CarbonImmutable('2022-09-01'), new CarbonImmutable('2023-01-01')],
                    [new CarbonImmutable('2023-01-01'), new CarbonImmutable('2023-05-01')],
                ],
                new CarbonImmutable('2024-01-01'),
                new CarbonImmutable('2023-05-01'),
            ],
            'Ordered 2022-01-15 with a contract period of 12 and a billing period of 4' => [
                new CarbonImmutable('2022-01-15'),
                12,
                4,
                [
                    [new CarbonImmutable('2022-05-15'), new CarbonImmutable('2022-09-15')],
                    [new CarbonImmutable('2022-09-15'), new CarbonImmutable('2023-01-15')],
                    [new CarbonImmutable('2023-01-15'), new CarbonImmutable('2023-05-15')],
                ],
                new CarbonImmutable('2024-01-15'),
                new CarbonImmutable('2023-05-15'),
            ],
            'Ordered 2022-01-31 with a contract period of 12 and a billing period of 4' => [
                new CarbonImmutable('2022-01-31'),
                12,
                4,
                [
                    [new CarbonImmutable('2022-05-31'), new CarbonImmutable('2022-09-31')],
                    [new CarbonImmutable('2022-09-31'), new CarbonImmutable('2023-01-31')],
                    [new CarbonImmutable('2023-01-31'), new CarbonImmutable('2023-05-31')],
                ],
                new CarbonImmutable('2024-01-31'),
                new CarbonImmutable('2023-05-31'),
            ],
            'Ordered 2022-01-01 with a contract period of 12 and a billing period of 12' => [
                new CarbonImmutable('2022-01-01'),
                12,
                12,
                [
                    [new CarbonImmutable('2023-01-01'), new CarbonImmutable('2024-01-01')],
                ],
                new CarbonImmutable('2024-01-01'),
                new CarbonImmutable('2024-01-01'),
            ],
            'Ordered 2022-01-15 with a contract period of 12 and a billing period of 12' => [
                new CarbonImmutable('2022-01-15'),
                12,
                12,
                [
                    [new CarbonImmutable('2023-01-15'), new CarbonImmutable('2024-01-15')],
                ],
                new CarbonImmutable('2024-01-15'),
                new CarbonImmutable('2024-01-15'),
            ],
            'Ordered 2022-01-31 with a contract period of 12 and a billing period of 12' => [
                new CarbonImmutable('2022-01-31'),
                12,
                12,
                [
                    [new CarbonImmutable('2023-01-31'), new CarbonImmutable('2024-01-31')],
                ],
                new CarbonImmutable('2024-01-31'),
                new CarbonImmutable('2024-01-31'),
            ],
            'Ordered 2022-01-01 with a contract period of 12 and a billing period of 1' => [
                new CarbonImmutable('2022-01-01'),
                12,
                1,
                [
                    [new CarbonImmutable('2022-02-01'), new CarbonImmutable('2022-03-01')],
                    [new CarbonImmutable('2022-03-01'), new CarbonImmutable('2022-04-01')],
                    [new CarbonImmutable('2022-04-01'), new CarbonImmutable('2022-05-01')],
                    [new CarbonImmutable('2022-05-01'), new CarbonImmutable('2022-06-01')],
                    [new CarbonImmutable('2022-06-01'), new CarbonImmutable('2022-07-01')],
                    [new CarbonImmutable('2022-07-01'), new CarbonImmutable('2022-08-01')],
                    [new CarbonImmutable('2022-08-01'), new CarbonImmutable('2022-09-01')],
                    [new CarbonImmutable('2022-09-01'), new CarbonImmutable('2022-10-01')],
                    [new CarbonImmutable('2022-10-01'), new CarbonImmutable('2022-11-01')],
                    [new CarbonImmutable('2022-11-01'), new CarbonImmutable('2022-12-01')],
                    [new CarbonImmutable('2022-12-01'), new CarbonImmutable('2023-01-01')],
                    [new CarbonImmutable('2023-01-01'), new CarbonImmutable('2023-02-01')],
                ],
                new CarbonImmutable('2024-01-01'),
                new CarbonImmutable('2023-02-01'),
            ],
            'Ordered 2022-01-15 with a contract period of 12 and a billing period of 1' => [
                new CarbonImmutable('2022-01-15'),
                12,
                1,
                [
                    [new CarbonImmutable('2022-02-15'), new CarbonImmutable('2022-03-15')],
                    [new CarbonImmutable('2022-03-15'), new CarbonImmutable('2022-04-15')],
                    [new CarbonImmutable('2022-04-15'), new CarbonImmutable('2022-05-15')],
                    [new CarbonImmutable('2022-05-15'), new CarbonImmutable('2022-06-15')],
                    [new CarbonImmutable('2022-06-15'), new CarbonImmutable('2022-07-15')],
                    [new CarbonImmutable('2022-07-15'), new CarbonImmutable('2022-08-15')],
                    [new CarbonImmutable('2022-08-15'), new CarbonImmutable('2022-09-15')],
                    [new CarbonImmutable('2022-09-15'), new CarbonImmutable('2022-10-15')],
                    [new CarbonImmutable('2022-10-15'), new CarbonImmutable('2022-11-15')],
                    [new CarbonImmutable('2022-11-15'), new CarbonImmutable('2022-12-15')],
                    [new CarbonImmutable('2022-12-15'), new CarbonImmutable('2023-01-15')],
                    [new CarbonImmutable('2023-01-15'), new CarbonImmutable('2023-02-15')],
                ],
                new CarbonImmutable('2024-01-15'),
                new CarbonImmutable('2023-02-15'),
            ],
            'Ordered 2022-01-31 with a contract period of 12 and a billing period of 1' => [
                new CarbonImmutable('2022-01-31'),
                12,
                1,
                [
                    [new CarbonImmutable('2022-02-31'), new CarbonImmutable('2022-04-03')], // edge case
                    [new CarbonImmutable('2022-04-03'), new CarbonImmutable('2022-05-03')],
                    [new CarbonImmutable('2022-05-03'), new CarbonImmutable('2022-06-03')],
                    [new CarbonImmutable('2022-06-03'), new CarbonImmutable('2022-07-03')],
                    [new CarbonImmutable('2022-07-03'), new CarbonImmutable('2022-08-03')],
                    [new CarbonImmutable('2022-08-03'), new CarbonImmutable('2022-09-03')],
                    [new CarbonImmutable('2022-09-03'), new CarbonImmutable('2022-10-03')],
                    [new CarbonImmutable('2022-10-03'), new CarbonImmutable('2022-11-03')],
                    [new CarbonImmutable('2022-11-03'), new CarbonImmutable('2022-12-03')],
                    [new CarbonImmutable('2022-12-03'), new CarbonImmutable('2023-01-03')],
                    [new CarbonImmutable('2023-01-03'), new CarbonImmutable('2023-01-31')],
                    [new CarbonImmutable('2023-01-31'), new CarbonImmutable('2023-02-31')],
                ],
                new CarbonImmutable('2024-01-31'),
                new CarbonImmutable('2023-03-03'),
            ],
            'Ordered 2022-01-01 with a contract period of 24 and a billing period of 12' => [
                new CarbonImmutable('2022-01-01'),
                24,
                12,
                [
                    [new CarbonImmutable('2023-01-01'), new CarbonImmutable('2024-01-01')],
                    [new CarbonImmutable('2024-01-01'), new CarbonImmutable('2025-01-01')],
                ],
                new CarbonImmutable('2026-01-01'),
                new CarbonImmutable('2025-01-01'),
            ],
            'Ordered 2022-01-15 with a contract period of 24 and a billing period of 12' => [
                new CarbonImmutable('2022-01-15'),
                24,
                12,
                [
                    [new CarbonImmutable('2023-01-15'), new CarbonImmutable('2024-01-15')],
                    [new CarbonImmutable('2024-01-15'), new CarbonImmutable('2025-01-15')],
                ],
                new CarbonImmutable('2026-01-15'),
                new CarbonImmutable('2025-01-15'),
            ],
            'Ordered 2022-01-31 with a contract period of 24 and a billing period of 12' => [
                new CarbonImmutable('2022-01-31'),
                24,
                12,
                [
                    [new CarbonImmutable('2023-01-31'), new CarbonImmutable('2024-01-31')],
                    [new CarbonImmutable('2024-01-31'), new CarbonImmutable('2025-01-31')],
                ],
                new CarbonImmutable('2026-01-31'),
                new CarbonImmutable('2025-01-31'),
            ],
            'Ordered 2022-01-01 with a contract period of 1 and a billing period of 1' => [
                new CarbonImmutable('2022-01-01'),
                1,
                1,
                [
                    [new CarbonImmutable('2022-02-01'), new CarbonImmutable('2022-03-01')],
                ],
                new CarbonImmutable('2022-03-01'),
                new CarbonImmutable('2022-03-01'),
            ],
            'Ordered 2022-01-15 with a contract period of 1 and a billing period of 1' => [
                new CarbonImmutable('2022-01-15'),
                1,
                1,
                [
                    [new CarbonImmutable('2022-02-15'), new CarbonImmutable('2022-03-15')],
                ],
                new CarbonImmutable('2022-03-15'),
                new CarbonImmutable('2022-03-15'),
            ],
            'Ordered 2022-01-31 with a contract period of 1 and a billing period of 1' => [
                new CarbonImmutable('2022-01-31'),
                1,
                1,
                [
                    [new CarbonImmutable('2022-02-31'), new CarbonImmutable('2022-04-03')],
                ],
                new CarbonImmutable('2022-04-03'),
                new CarbonImmutable('2022-04-03'),
            ],
            'Ordered on 2022-01-01 with a contract period of 2 and a billing period of 1' => [
                new CarbonImmutable('2022-01-01'),
                2,
                1,
                [
                    [new CarbonImmutable('2022-02-01'), new CarbonImmutable('2022-03-01')],
                    [new CarbonImmutable('2022-03-01'), new CarbonImmutable('2022-04-01')],
                ],
                new CarbonImmutable('2022-05-01'),
                new CarbonImmutable('2022-04-01'),
            ],
            'Ordered on 2022-01-15 with a contract period of 2 and a billing period of 1' => [
                new CarbonImmutable('2022-01-15'),
                2,
                1,
                [
                    [new CarbonImmutable('2022-02-15'), new CarbonImmutable('2022-03-15')],
                    [new CarbonImmutable('2022-03-15'), new CarbonImmutable('2022-04-15')],
                ],
                new CarbonImmutable('2022-05-15'),
                new CarbonImmutable('2022-04-15'),
            ],
            'Ordered on 2022-01-31 with a contract period of 2 and a billing period of 1' => [
                new CarbonImmutable('2022-01-31'),
                2,
                1,
                [
                    [new CarbonImmutable('2022-03-03'), new CarbonImmutable('2022-03-31')],
                    [new CarbonImmutable('2022-03-31'), new CarbonImmutable('2022-05-01')],
                ],
                new CarbonImmutable('2022-05-31'),
                new CarbonImmutable('2022-05-01'),
            ],
        ];
    }

    private function checkSubscriptionDates(
        Subscription $subscription,
        int $contractPeriod,
        int $billingPeriod,
    ): void {
        $now = CarbonImmutable::now();

        self::assertSame($now->toDateString(), $subscription->start_date->toDateString());
        self::assertSame(
            $now->addMonths($billingPeriod)->toDateString(),
            $subscription->next_billing_date->toDateString(),
        );
        self::assertSame($now->addMonths($contractPeriod)->toDateString(), $subscription->end_date->toDateString());

        self::assertSame(0, Invoice::count());
    }

    private function placeOrder(int $contractPeriod, int $billingPeriod): void
    {
        new ProductPriceComponentFactory()
            ->for($this->domainProduct)
            ->registration()
            ->createOne([
                'price' => 96,
                'billing_period' => $billingPeriod,
                'contract_period' => $contractPeriod,
            ]);

        new ProductPriceComponentFactory()
            ->for($this->domainProduct)
            ->prolongation()
            ->createOne([
                'price' => 96,
                'billing_period' => $billingPeriod,
                'contract_period' => $contractPeriod,
            ]);

        new ProductPriceComponentFactory()
            ->for($this->dnsProduct)
            ->registration()
            ->createOne([
                'price' => 0,
                'billing_period' => $billingPeriod,
                'contract_period' => $contractPeriod,
            ]);

        new ProductPriceComponentFactory()
            ->for($this->dnsProduct)
            ->prolongation()
            ->createOne([
                'price' => 0,
                'billing_period' => $billingPeriod,
                'contract_period' => $contractPeriod,
            ]);

        $this->applyPdnsMockForOrder('example.com');

        $this->actingAsCustomer($this->customer)->postJson(
            $this->generateRoute('partners.order.order'),
            $this->createOrderPayload($contractPeriod, $billingPeriod),
        );
    }

    /** @return array<mixed> */
    private function createOrderPayload(int $contractPeriod, int $billingPeriod): array
    {
        return [
            'total_price' => 96,
            'payment_method' => 'ideal',
            'subscriptions' => [
                'extension' => [
                    [
                        'uuid' => 'f8f1a80f-4ae3-4cfc-a33e-b2f4d7c6f14f',
                        'domain' => 'example.com',
                        'slug' => 'extension_com',
                        'status' => 'registration',
                        'price' => 96,
                        'gross_price' => 96,
                        'billing_period' => $billingPeriod,
                        'contract_period' => $contractPeriod,
                        'children' => [
                            'dns' => [
                                [
                                    'uuid' => '90b1c8f5-7e1b-4f5d-8118-6a83aa5083d9',
                                    'status' => 'registration',
                                    'domain' => 'example.com',
                                    'billing_period' => $billingPeriod,
                                    'contract_period' => $contractPeriod,
                                    'price' => 0,
                                    'gross_price' => 0,
                                    'slug' => 'free-dns',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function applyPdnsMockForOrder(string $domain): void
    {
        $domainZone = $this->mockDomainZone($domain);
        $keySet = $this->getMockDnsKeySet();

        $mockDnsService = self::createStub(DnsService::class);

        $mockDnsService->method('getDnsZone')->willReturn($domainZone);

        $mockDnsService->method('getDnsZoneKeys')->willReturn($keySet);

        $this->app->bind(DnsService::class, fn () => $mockDnsService);
    }

    private function mockDomainZone(string $domain): DnsZone
    {
        $zoneConverter = self::resolve(PowerDnsZoneToDnsZoneConverter::class);
        /** @var array<string, mixed> $pdnsZone */
        $pdnsZone = json_decode($this->getMockedZoneResponseBody($domain), true, 512, JSON_THROW_ON_ERROR);

        return $zoneConverter->convertFromPowerDnsZone(
            PowerDnsZone::fromArray(
                $pdnsZone,
            ),
        );
    }

    private function getMockDnsKeySet(): PowerDnsSecKeySet
    {
        /** @var array<array<string,mixed>> $keys */
        $keys = json_decode($this->getMockedKeyResponseBody(), true, 512, JSON_THROW_ON_ERROR);

        return PowerDnsSecKeySet::fromArray($keys);
    }
}
