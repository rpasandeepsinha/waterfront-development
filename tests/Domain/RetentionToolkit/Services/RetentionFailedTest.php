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
use Waterfront\Domain\RetentionToolkit\DTO\RetentionOfferItemDTO;
use Waterfront\Domain\RetentionToolkit\DTO\RetentionOfferRequestDTO;
use Waterfront\Domain\RetentionToolkit\Enums\CustomerType;
use Waterfront\Domain\RetentionToolkit\Enums\ExecutionDate;
use Waterfront\Domain\RetentionToolkit\Enums\RetentionOfferCalculationStatus;
use Waterfront\Domain\RetentionToolkit\Enums\SelectedAction;
use Waterfront\Domain\RetentionToolkit\Models\CustomerRetentionOffer;
use Waterfront\Domain\RetentionToolkit\Services\RetentionToolkitService;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Models\SubscriptionMutation;
use Webmozart\Assert\Assert;

#[CoversClass(RetentionToolkitService::class)]
class RetentionFailedTest extends IntegrationTestCase
{
    private const string NOW = '2026-07-01 12:00:00';

    private const string CONSUMER_CANCELLATION_DATE = '2026-08-01 00:00:00';

    private const string BUSINESS_CANCELLATION_DATE = '2027-01-01 00:00:00';

    private const string LATE_BUSINESS_CURRENT_END_DATE = '2026-07-15 00:00:00';

    private const string LATE_BUSINESS_CANCELLATION_DATE = '2027-07-15 00:00:00';

    private Customer $customer;

    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(self::NOW);

        $this->customer = new CustomerFactory()->createOne();

        $domainProduct = new ProductFactory()
            ->nlDomain()
            ->createOne();

        $this->subscription = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->for($this->customer)
            ->for($domainProduct)
            ->createOne([
                'start_date' => new CarbonImmutable('2025-01-01'),
                'end_date' => new CarbonImmutable(
                    self::BUSINESS_CANCELLATION_DATE,
                ),
            ]);

        new InvoiceFactory()
            ->for($this->customer)
            ->for($domainProduct)
            ->for($this->subscription)
            ->createOne([
                'start_date' => new CarbonImmutable('2026-01-01'),
                'end_date' => new CarbonImmutable('2027-01-01'),
                'gross_price' => 365,
                'net_price' => 365,
                'paid' => true,
            ]);

        $this->actingAsEmployee(email: 'employee@yourhosting.nl');
    }

    #[Test]
    public function calculateConsumer(): void
    {
        $results = self::resolve(RetentionToolkitService::class)
            ->calculate($this->getRetentionOfferRequestDto(
                customerType: CustomerType::CONSUMER,
                executionDate: ExecutionDate::IMMEDIATE,
            ));

        self::assertCount(1, $results);
        $result = $results[0];

        $expectedCancellationDate = new CarbonImmutable(
            self::CONSUMER_CANCELLATION_DATE
        );

        self::assertSame(
            RetentionOfferCalculationStatus::CALCULATED,
            $result->status,
        );

        self::assertNotNull($result->effectiveDate);
        self::assertSame(
            $expectedCancellationDate->getTimestamp(),
            $result->effectiveDate->getTimestamp(),
        );

        self::assertNotNull($result->cancellationDate);
        self::assertSame(
            $expectedCancellationDate->getTimestamp(),
            $result->cancellationDate->getTimestamp(),
        );

        self::assertSame(
            153,
            $result->creditTotal,
        );

        self::assertDatabaseEmpty(CustomerRetentionOffer::class);
        self::assertDatabaseEmpty(SubscriptionMutation::class);
    }

    #[Test]
    public function calculateBusiness(): void
    {
        $results = self::resolve(RetentionToolkitService::class)
            ->calculate($this->getRetentionOfferRequestDto(
                customerType: CustomerType::BUSINESS,
                executionDate: ExecutionDate::CONTRACT_END,
            ));

        self::assertCount(1, $results);
        $result = $results[0];

        self::assertSame(
            RetentionOfferCalculationStatus::CALCULATED,
            $result->status,
        );

        $expectedCancellationDate = new CarbonImmutable(
            self::BUSINESS_CANCELLATION_DATE,
        );

        self::assertNotNull($result->effectiveDate);
        self::assertSame(
            $expectedCancellationDate->getTimestamp(),
            $result->effectiveDate->getTimestamp(),
        );

        self::assertNotNull($result->cancellationDate);
        self::assertSame(
            $expectedCancellationDate->getTimestamp(),
            $result->cancellationDate->getTimestamp(),
        );

        self::assertSame(
            0,
            $result->creditTotal,
        );

        self::assertDatabaseEmpty(CustomerRetentionOffer::class);
        self::assertDatabaseEmpty(SubscriptionMutation::class);
    }

    #[Test]
    public function apply(): void
    {
        $results = self::resolve(RetentionToolkitService::class)
            ->apply(
                $this->getRetentionOfferRequestDto(
                    customerType: CustomerType::BUSINESS,
                    executionDate: ExecutionDate::CONTRACT_END,
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

        self::assertDatabaseHas(Subscription::class, [
            'id' => $this->subscription->id,
            'administrative_status' => AdministrativeStatus::CANCELED->value,
            'cancel_date' => self::NOW,
            'cancel_reason' => SubscriptionCancelReason::REASON_CANCELLATION->value,
            'end_date' => self::BUSINESS_CANCELLATION_DATE,
        ]);
        self::assertDatabaseHas(CustomerRetentionOffer::class, [
            'customer_type' => CustomerType::BUSINESS->value,
            'subscription_id' => $this->subscription->id,
            'selected_action' => SelectedAction::RF->value,
            'puzzel_ticket_id' => '1337',
            'credit_amount' => 0,
            'effective_at' => self::BUSINESS_CANCELLATION_DATE,
        ]);
    }

    #[Test]
    public function applyLateBusinessCancellationRenewsBeforeCancellation(): void
    {
        new ProductPriceComponentFactory()
            ->for($this->subscription->product)
            ->prolongation()
            ->createOne([
                'billing_period' => 12,
                'contract_period' => 12,
                'price' => 365,
            ]);

        $this->subscription->end_date = new CarbonImmutable(
            self::LATE_BUSINESS_CURRENT_END_DATE,
        );
        $this->subscription->save();

        $results = self::resolve(RetentionToolkitService::class)
            ->apply(
                $this->getRetentionOfferRequestDto(
                    customerType: CustomerType::BUSINESS,
                    executionDate: ExecutionDate::CONTRACT_END,
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

        $expectedCancellationDate = new CarbonImmutable(
            self::LATE_BUSINESS_CANCELLATION_DATE,
        );

        self::assertNotNull($result->cancellationDate);
        self::assertSame(
            $expectedCancellationDate->getTimestamp(),
            $result->cancellationDate->getTimestamp(),
        );

        $this->subscription->refresh();

        self::assertSame(
            AdministrativeStatus::CANCELED->value,
            $this->subscription->administrative_status,
        );
        self::assertSame(
            $expectedCancellationDate->getTimestamp(),
            $this->subscription->end_date->getTimestamp(),
        );
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
                    selectedAction: SelectedAction::RF,
                    executionDate: $executionDate,
                    contractPeriod: $contractPeriod,
                    billingPeriod: $billingPeriod,
                    targetProduct: null,
                    cancelReason: SubscriptionCancelReason::REASON_CANCELLATION,
                    cancelReasonOther: null,
                ),
            ],
        );
    }
}
