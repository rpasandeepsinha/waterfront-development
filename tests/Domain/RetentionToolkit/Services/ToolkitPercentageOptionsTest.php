<?php

declare(strict_types=1);

namespace Tests\Domain\RetentionToolkit\Services;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Models\SubscriptionMutation;
use Webmozart\Assert\Assert;

#[CoversClass(RetentionToolkitService::class)]
class ToolkitPercentageOptionsTest extends IntegrationTestCase
{
    private const string NOW = '2026-07-01 12:00:00';

    private Customer $customer;

    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(self::NOW);

        $this->customer = new CustomerFactory()->createOne();

        $hostingProduct = new ProductFactory()
            ->hostingGold()
            ->createOne();

        new ProductPriceComponentFactory()
            ->for($hostingProduct)
            ->prolongation()
            ->createOne([
                'price' => 12000,
            ]);

        $this->subscription = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->for($this->customer)
            ->for($hostingProduct)
            ->createOne([
                'start_date' => $startDate = new CarbonImmutable('2026-01-01'),
                'end_date' => $startDate->addYear(),
                'billing_period' => 12,
                'contract_period' => 12,
                'gross_price' => 365,
                'net_price' => 365,
            ]);

        new InvoiceFactory()
            ->for($this->customer)
            ->for($hostingProduct)
            ->for($this->subscription)
            ->createOne([
                'start_date' => new CarbonImmutable('2026-01-01'),
                'end_date' => new CarbonImmutable('2027-01-01'),
                'gross_price' => 365,
                'net_price' => 365,
                'paid' => true,
            ]);
    }

    #[DataProvider('percentageOptionProvider')]
    #[Test]
    public function calculateConsumer(
        SelectedAction $selectedAction,
        int $offerNetPrice,
        int $discountAmount,
    ): void {
        $results = self::resolve(RetentionToolkitService::class)
            ->calculate($this->getRetentionOfferRequestDto(
                selectedAction: $selectedAction,
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
        self::assertSame(
            $offerNetPrice,
            $result->price->offerNetPrice,
        );
        self::assertSame(
            $discountAmount,
            $result->price->discountAmount,
        );
        self::assertSame(184, $result->creditTotal);
        self::assertSame(
            $offerNetPrice - 184,
            $result->payableAfterCredits,
        );
        self::assertNotNull($result->effectiveDate);
        self::assertSame(
            CarbonImmutable::today()->getTimestamp(),
            $result->effectiveDate->getTimestamp(),
        );
        self::assertTrue($result->requiresNewInvoice);

        self::assertDatabaseEmpty(CustomerRetentionOffer::class);
        self::assertDatabaseEmpty(SubscriptionMutation::class);
    }

    #[DataProvider('percentageOptionProvider')]
    #[Test]
    public function calculateBusiness(
        SelectedAction $selectedAction,
        int $offerNetPrice,
        int $discountAmount,
    ): void {
        $results = self::resolve(RetentionToolkitService::class)
            ->calculate($this->getRetentionOfferRequestDto(
                selectedAction: $selectedAction,
                customerType: CustomerType::BUSINESS,
                executionDate: ExecutionDate::CONTRACT_END,
            ));

        self::assertCount(1, $results);
        $result = $results[0];

        self::assertSame(
            RetentionOfferCalculationStatus::CALCULATED,
            $result->status,
        );
        self::assertNotNull($result->price);
        self::assertSame(
            $offerNetPrice,
            $result->price->offerNetPrice,
        );
        self::assertSame(
            $discountAmount,
            $result->price->discountAmount,
        );
        self::assertSame(0, $result->creditTotal);
        self::assertSame(
            $offerNetPrice,
            $result->payableAfterCredits,
        );
        self::assertNotNull($result->effectiveDate);
        self::assertSame(
            $this->subscription->end_date->getTimestamp(),
            $result->effectiveDate->getTimestamp(),
        );
        self::assertFalse($result->requiresNewInvoice);

        self::assertDatabaseEmpty(CustomerRetentionOffer::class);
        self::assertDatabaseEmpty(SubscriptionMutation::class);
    }

    #[DataProvider('percentageOptionProvider')]
    #[Test]
    public function apply(
        SelectedAction $selectedAction,
        int $offerNetPrice,
        int $discountAmount,
    ): void {
        $results = self::resolve(RetentionToolkitService::class)
            ->apply(
                $this->getRetentionOfferRequestDto(
                    selectedAction: $selectedAction,
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

        self::assertDatabaseHas(SubscriptionMutation::class, [
            'subscription_id' => $this->subscription->id,
            'gross_price' => 12000,
            'net_price' => $offerNetPrice,
            'contract_period' => 12,
            'billing_period' => 12,
            'mutated_at' => null,
        ]);
        self::assertDatabaseHas(CustomerRetentionOffer::class, [
            'customer_type' => CustomerType::BUSINESS->value,
            'subscription_id' => $this->subscription->id,
            'selected_action' => $selectedAction->value,
            'puzzel_ticket_id' => '1337',
            'discount_amount' => $discountAmount,
            'credit_amount' => 0,
            'effective_at' => $this->subscription->end_date,
        ]);
    }

    /** @return iterable<string, array{SelectedAction, positive-int, positive-int}> */
    public static function percentageOptionProvider(): iterable
    {
        yield 'TK Option 2' => [
            SelectedAction::TK_OPTION_2,
            10200,
            1800,
        ];

        yield 'TK Option 3' => [
            SelectedAction::TK_OPTION_3,
            9600,
            2400,
        ];

        yield 'TK Option 5' => [
            SelectedAction::TK_OPTION_5,
            9000,
            3000,
        ];

        yield 'TK Option 6' => [
            SelectedAction::TK_OPTION_6,
            6000,
            6000,
        ];
    }

    private function getRetentionOfferRequestDto(
        SelectedAction $selectedAction,
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
                    selectedAction: $selectedAction,
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
