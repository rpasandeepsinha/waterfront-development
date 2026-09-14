<?php

declare(strict_types=1);

namespace Tests\Domain\RetentionToolkit\Services;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Lighthouse\DTO\IdentityMetadataDTO;
use Waterfront\Domain\RetentionToolkit\Actions\ApplyRetentionCancellationAction;
use Waterfront\Domain\RetentionToolkit\DTO\RetentionOfferItemDTO;
use Waterfront\Domain\RetentionToolkit\DTO\RetentionOfferRequestDTO;
use Waterfront\Domain\RetentionToolkit\Enums\CustomerType;
use Waterfront\Domain\RetentionToolkit\Enums\ExecutionDate;
use Waterfront\Domain\RetentionToolkit\Enums\RetentionOfferCalculationStatus;
use Waterfront\Domain\RetentionToolkit\Enums\SelectedAction;
use Waterfront\Domain\RetentionToolkit\Models\CustomerRetentionOffer;
use Waterfront\Domain\RetentionToolkit\Services\RetentionToolkitService;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Models\SubscriptionMutation;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

#[CoversClass(RetentionToolkitService::class)]
class PreserveWithoutOfferTest extends IntegrationTestCase
{
    private RetentionToolkitService $service;

    private Customer $customer;

    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = self::resolve(RetentionToolkitService::class);

        $this->customer = new CustomerFactory()->createOne();

        $this->subscription = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->for($this->customer)
            ->for(new ProductFactory()->nlDomain())
            ->createOne();
    }

    #[Test]
    public function calculate(): void
    {
        $results = $this->service->calculate($this->getRetentionOfferRequestDto());
        self::assertCount(1, $results);

        $result = $results[0];

        self::assertSame(
            RetentionOfferCalculationStatus::CALCULATED,
            $result->status,
        );
        self::assertSame(0, $result->creditTotal);
        self::assertNull($result->effectiveDate);
        self::assertFalse($result->requiresNewInvoice);

        self::assertDatabaseEmpty(CustomerRetentionOffer::class);
        self::assertDatabaseEmpty(SubscriptionMutation::class);
    }

    #[Test]
    public function apply(): void
    {
        $results = $this->service->apply(
            $this->getRetentionOfferRequestDto(),
            new IdentityMetadataDTO(
                Uuid::uuid4(),
                'employee@yourhosting.nl',
            ),
        );

        self::assertCount(1, $results);
        $result = $results[0];
        self::assertSame(
            RetentionOfferCalculationStatus::CALCULATED,
            $result->status,
        );
        self::assertSame(0, $result->creditTotal);
        self::assertNull($result->effectiveDate);
        self::assertFalse($result->requiresNewInvoice);

        self::assertDatabaseHas(CustomerRetentionOffer::class, [
            'customer_type' => CustomerType::BUSINESS->value,
            'subscription_id' => $this->subscription->id,
            'selected_action' => SelectedAction::BZ->value,
            'puzzel_ticket_id' => '1337',
            'subscription_mutation_id' => null,
            'discount_amount' => null,
            'credit_amount' => null,
        ]);
        self::assertDatabaseEmpty(SubscriptionMutation::class);
    }

    #[Test]
    public function applyRollsBackEarlierOfferWhenLaterItemFails(): void
    {
        $secondSubscription = SubscriptionFactory::new()
            ->administrativeStatusActive()
            ->for($this->customer)
            ->for($this->subscription->product)
            ->createOne([
                'start_date' => CarbonImmutable::today()->subYear(),
                'end_date' => CarbonImmutable::today()->addYear(),
            ]);

        $contractPeriod = $this->subscription->contract_period;
        $billingPeriod = $this->subscription->billing_period;
        Assert::positiveInteger($contractPeriod);
        Assert::positiveInteger($billingPeriod);

        $secondContractPeriod = $secondSubscription->contract_period;
        $secondBillingPeriod = $secondSubscription->billing_period;
        Assert::positiveInteger($secondContractPeriod);
        Assert::positiveInteger($secondBillingPeriod);

        $request = new RetentionOfferRequestDTO(
            customer: $this->customer,
            customerType: CustomerType::BUSINESS,
            puzzelTicketId: '1337',
            items: [
                new RetentionOfferItemDTO(
                    subscription: $this->subscription,
                    selectedAction: SelectedAction::BZ,
                    executionDate: ExecutionDate::CONTRACT_END,
                    contractPeriod: $contractPeriod,
                    billingPeriod: $billingPeriod,
                    targetProduct: null,
                    cancelReason: null,
                    cancelReasonOther: null,
                ),
                new RetentionOfferItemDTO(
                    subscription: $secondSubscription,
                    selectedAction: SelectedAction::RF,
                    executionDate: ExecutionDate::CONTRACT_END,
                    contractPeriod: $secondContractPeriod,
                    billingPeriod: $secondBillingPeriod,
                    targetProduct: null,
                    cancelReason: SubscriptionCancelReason::REASON_CANCELLATION,
                    cancelReasonOther: null,
                ),
            ],
        );

        $applyRetentionCancellationAction = self::createMock(
            ApplyRetentionCancellationAction::class,
        );
        $exception = new RuntimeException('Cancellation failed.');

        $applyRetentionCancellationAction->expects(self::once())->method('execute')->willThrowException($exception);

        $createdByMetadata = new IdentityMetadataDTO(
            Uuid::uuid4(),
            'employee@yourhosting.nl',
        );
        $logger = self::createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('critical')
            ->with(
                'Retention Toolkit application failed; transaction rolled back.',
                [
                    LoggingContextKeys::CUSTOMER_ID => $this->customer->id,
                    LoggingContextKeys::IDENTITY_UUID => $createdByMetadata->uuid,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

        $this->app->bind(
            ApplyRetentionCancellationAction::class,
            fn () => $applyRetentionCancellationAction,
        );

        $this->app->bind(
            LoggerInterface::class,
            fn () => $logger,
        );

        self::expectExceptionObject($exception);

        try {
            self::resolve(RetentionToolkitService::class)
                ->apply(
                    request: $request,
                    createdByMetadata: $createdByMetadata,
                );
        } finally {
            self::assertDatabaseEmpty(CustomerRetentionOffer::class);
        }
    }

    private function getRetentionOfferRequestDto(): RetentionOfferRequestDTO
    {
        $contractPeriod = $this->subscription->contract_period;
        $billingPeriod = $this->subscription->billing_period;
        Assert::positiveInteger($contractPeriod);
        Assert::positiveInteger($billingPeriod);

        return new RetentionOfferRequestDTO(
            customer: $this->customer,
            customerType: CustomerType::BUSINESS,
            puzzelTicketId: '1337',
            items: [
                new RetentionOfferItemDTO(
                    subscription: $this->subscription,
                    selectedAction: SelectedAction::BZ,
                    executionDate: ExecutionDate::CONTRACT_END,
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
