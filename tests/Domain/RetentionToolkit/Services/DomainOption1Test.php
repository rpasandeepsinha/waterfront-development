<?php

declare(strict_types=1);

namespace Tests\Domain\RetentionToolkit\Services;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\Factories\CustomerFactory;
use Tests\Factories\InvoiceFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Lighthouse\DTO\IdentityMetadataDTO;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\RetentionToolkit\DTO\RetentionOfferItemDTO;
use Waterfront\Domain\RetentionToolkit\DTO\RetentionOfferRequestDTO;
use Waterfront\Domain\RetentionToolkit\Enums\CustomerType;
use Waterfront\Domain\RetentionToolkit\Enums\ExecutionDate;
use Waterfront\Domain\RetentionToolkit\Enums\RetentionOfferCalculationStatus;
use Waterfront\Domain\RetentionToolkit\Enums\SelectedAction;
use Waterfront\Domain\RetentionToolkit\Models\CustomerRetentionOffer;
use Waterfront\Domain\RetentionToolkit\Services\RetentionToolkitService;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Models\SubscriptionMutation;
use Webmozart\Assert\Assert;

#[CoversClass(RetentionToolkitService::class)]
class DomainOption1Test extends IntegrationTestCase
{
    private Customer $customer;

    private Subscription $subscription;

    private Product $domainProduct;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-07-01 12:00:00');

        $this->customer = new CustomerFactory()->createOne();

        $this->domainProduct = new ProductFactory()->nlDomain()->createOne();

        new ProductPriceComponentFactory()
            ->for($this->domainProduct)
            ->prolongation()
            ->createOne([
                'price' => 1000,
            ]);

        $this->subscription = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->for($this->customer)
            ->for($this->domainProduct)
            ->withPrice()
            ->createOne([
                'start_date' => $startDate = new CarbonImmutable('2026-01-01'),
                'end_date' => $startDate->addYear(),
                'gross_price' => 365,
                'net_price' => 365,
            ]);
    }

    #[Test]
    public function calculateConsumer(): void
    {
        $this->createCurrentInvoice();

        $service = self::resolve(RetentionToolkitService::class);
        $results = $service->calculate($this->getRetentionOfferRequestDto(
            customerType: CustomerType::CONSUMER,
            executionDate: ExecutionDate::IMMEDIATE,
        ));
        self::assertCount(1, $results);
        $result = $results[0];

        self::assertSame(
            RetentionOfferCalculationStatus::CALCULATED,
            $result->status,
        );
        self::assertNotNull($result->price);
        self::assertSame(750, $result->price->offerNetPrice);
        self::assertSame(250, $result->price->discountAmount);
        self::assertSame(184, $result->creditTotal);
        self::assertSame(566, $result->payableAfterCredits);
        self::assertNotNull($result->effectiveDate);
        self::assertSame(
            CarbonImmutable::today()->getTimestamp(),
            $result->effectiveDate->getTimestamp(),
        );
        self::assertTrue($result->requiresNewInvoice);

        self::assertDatabaseEmpty(CustomerRetentionOffer::class);
        self::assertDatabaseEmpty(SubscriptionMutation::class);
    }

    #[Test]
    public function calculateBusiness(): void
    {
        $this->createCurrentInvoice();

        $service = self::resolve(RetentionToolkitService::class);
        $results = $service->calculate(
            $this->getRetentionOfferRequestDto(
                customerType: CustomerType::BUSINESS,
                executionDate: ExecutionDate::CONTRACT_END,
            ),
        );
        self::assertCount(1, $results);
        $result = $results[0];

        self::assertSame(
            RetentionOfferCalculationStatus::CALCULATED,
            $result->status,
        );
        self::assertNotNull($result->price);
        self::assertSame(750, $result->price->offerNetPrice);
        self::assertSame(250, $result->price->discountAmount);
        self::assertSame(0, $result->creditTotal);
        self::assertSame(750, $result->payableAfterCredits);
        self::assertNotNull($result->effectiveDate);
        self::assertSame(
            $this->subscription->end_date->getTimestamp(),
            $result->effectiveDate->getTimestamp(),
        );
        self::assertFalse($result->requiresNewInvoice);

        self::assertDatabaseEmpty(CustomerRetentionOffer::class);
        self::assertDatabaseEmpty(SubscriptionMutation::class);
    }

    #[Test]
    public function apply(): void
    {
        $service = self::resolve(RetentionToolkitService::class);
        $results = $service->apply(
            $this->getRetentionOfferRequestDto(
                customerType: CustomerType::CONSUMER,
                executionDate: ExecutionDate::IMMEDIATE,
            ),
            new IdentityMetadataDTO(
                uuid: Uuid::uuid4(),
                email: 'employee@yourhosting.nl',
            ),
        );

        self::assertCount(1, $results);
        $result = $results[0];

        self::assertSame(
            RetentionOfferCalculationStatus::CALCULATED,
            $result->status,
        );

        self::assertDatabaseHas(SubscriptionMutation::class, [
            'subscription_id' => $this->subscription->id,
            'gross_price' => 1000,
            'net_price' => 750,
            'contract_period' => 12,
            'billing_period' => 12,
        ]);
        self::assertDatabaseMissing(SubscriptionMutation::class, [
            'subscription_id' => $this->subscription->id,
            'mutated_at' => null,
        ]);
        self::assertDatabaseHas(CustomerRetentionOffer::class, [
            'customer_type' => CustomerType::CONSUMER->value,
            'subscription_id' => $this->subscription->id,
            'selected_action' => SelectedAction::DM_OPTION_1->value,
            'puzzel_ticket_id' => '1337',
            'discount_amount' => 250,
            'credit_amount' => 0,
        ]);
    }

    private function createCurrentInvoice(): void
    {
        new InvoiceFactory()
            ->for($this->customer)
            ->for($this->domainProduct)
            ->for($this->subscription)
            ->createOne([
                'start_date' => new CarbonImmutable('2026-01-01'),
                'end_date' => new CarbonImmutable('2027-01-01'),
                'gross_price' => 365,
                'net_price' => 365,
                'paid' => true,
            ]);
    }

    private function getRetentionOfferRequestDto(
        CustomerType $customerType,
        ExecutionDate $executionDate,
    ): RetentionOfferRequestDTO {
        $contractPeriod = $this->subscription->contract_period;
        $billingPeriod = $this->subscription->billing_period;
        Assert::positiveInteger($contractPeriod);
        Assert::positiveInteger($billingPeriod);

        return new RetentionOfferRequestDTO(
            customer: $this->customer,
            customerType: $customerType,
            puzzelTicketId: '1337',
            items: [
                new RetentionOfferItemDTO(
                    subscription: $this->subscription,
                    selectedAction: SelectedAction::DM_OPTION_1,
                    executionDate: $executionDate,
                    contractPeriod: $contractPeriod,
                    billingPeriod: $billingPeriod,
                    targetProduct: null,
                    cancelReason: null,
                    cancelReasonOther: null,
                ),
            ],
        );
    }
}
