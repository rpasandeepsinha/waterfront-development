<?php

declare(strict_types=1);

namespace Tests\Domain\Invoices\Unit\Service;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\InvoiceFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Invoices\Repositories\InvoiceRepository;
use Waterfront\Domain\Invoices\Services\InvoicePrefillResolver;

#[CoversClass(InvoicePrefillResolver::class)]
class InvoicePrefillResolverTest extends IntegrationTestCase
{
    #[Test]
    public function resolveForSubscriptionReturnsNullDefaultsWhenNoLastInvoiceExists(): void
    {
        $customer = new CustomerFactory()->createOne();
        $productGroup = new ProductGroupFactory()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();
        $subscription = new SubscriptionFactory()->for($product)->for($customer)->createOne(['billing_period' => 12]);
        $repository = $this->createMock(InvoiceRepository::class);
        $repository->expects(self::once())
            ->method('getLastDebitInvoiceForSubscription')
            ->with($subscription)
            ->willReturn(null);

        $resolver = new InvoicePrefillResolver($repository);
        $result = $resolver->resolveForSubscription($subscription);

        self::assertNull($result->startDate);
        self::assertNull($result->endDate);
        self::assertNull($result->grossPrice);
        self::assertNull($result->netPrice);
        self::assertSame($product->id, $result->product->id);
    }

    #[Test]
    public function resolveForSubscriptionReusesLastInvoiceDatesAndPricesWhenPeriodAndProductMatch(): void
    {
        $customer = new CustomerFactory()->createOne();
        $productGroup = new ProductGroupFactory()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();
        $subscription = new SubscriptionFactory()
            ->for($product)
            ->for($customer)
            ->createOne(['billing_period' => 12]);

        $startDate = CarbonImmutable::parse('2025-01-01');
        $endDate = CarbonImmutable::parse('2026-01-01');

        $lastInvoice = new InvoiceFactory()
            ->for($subscription)
            ->for($customer)
            ->for($subscription->product)
            ->createOne(
                [
                'period' => 12,
                'gross_price' => 1200,
                'net_price' => 992,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]
            );

        $repository = $this->createStub(InvoiceRepository::class);
        $repository->method('getLastDebitInvoiceForSubscription')->willReturn($lastInvoice);

        $resolver = new InvoicePrefillResolver($repository);
        $result = $resolver->resolveForSubscription($subscription);

        self::assertInstanceOf(CarbonImmutable::class, $result->startDate);
        self::assertInstanceOf(CarbonImmutable::class, $result->endDate);
        self::assertTrue($startDate->equalTo($result->startDate));
        self::assertTrue($endDate->equalTo($result->endDate));
        self::assertSame(1200, $result->grossPrice);
        self::assertSame(992, $result->netPrice);
    }

    #[Test]
    public function resolveForSubscriptionComputesNewEndDateAndNullPricesWhenBillingPeriodDiffers(): void
    {
        $customer = new CustomerFactory()->createOne();
        $productGroup = new ProductGroupFactory()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();
        $subscription = new SubscriptionFactory()
            ->for($product)
            ->for($customer)
            ->createOne(['billing_period' => 12]);
        $startDate = CarbonImmutable::parse('2025-01-01');

        $lastInvoice = new InvoiceFactory()
            ->for($subscription)
            ->for($customer)
            ->for($subscription->product)
            ->createOne(
                [
                    'period' => 1,
                    'gross_price' => 1200,
                    'net_price' => 992,
                    'start_date' => $startDate,
                    'end_date' => CarbonImmutable::parse('2025-02-01'),
                ]
            );

        $repository = $this->createStub(InvoiceRepository::class);
        $repository->method('getLastDebitInvoiceForSubscription')->willReturn($lastInvoice);

        $resolver = new InvoicePrefillResolver($repository);
        $result = $resolver->resolveForSubscription($subscription);

        self::assertInstanceOf(CarbonImmutable::class, $result->endDate);
        self::assertTrue($startDate->addMonths(12)->equalTo($result->endDate));
        self::assertNull($result->grossPrice);
        self::assertNull($result->netPrice);
    }

    #[Test]
    public function resolveForSubscriptionSetsNullPricesWhenProductDiffers(): void
    {
        $customer = new CustomerFactory()->createOne();
        $productGroup = new ProductGroupFactory()->createOne();
        $productFirst = new ProductFactory()->for($productGroup)->createOne(['name' => 'first one']);
        $productSecond = new ProductFactory()->for($productGroup)->createOne(['name' => 'second one']);
        $subscription = new SubscriptionFactory()
            ->for($productFirst)
            ->for($customer)
            ->createOne(['billing_period' => 12]);

        $lastInvoice = new InvoiceFactory()
            ->for($customer)
            ->for($productSecond)
            ->createOne(
                [
                    'period' => 12,
                    'gross_price' => 1200,
                    'net_price' => 992,
                    'start_date' => CarbonImmutable::parse('2025-01-01'),
                    'end_date' => CarbonImmutable::parse('2026-01-01'),
                ]
            );

        $repository = $this->createStub(InvoiceRepository::class);
        $repository->method('getLastDebitInvoiceForSubscription')->willReturn($lastInvoice);

        $resolver = new InvoicePrefillResolver($repository);
        $result = $resolver->resolveForSubscription($subscription);

        self::assertNull($result->grossPrice);
        self::assertNull($result->netPrice);
    }
}
