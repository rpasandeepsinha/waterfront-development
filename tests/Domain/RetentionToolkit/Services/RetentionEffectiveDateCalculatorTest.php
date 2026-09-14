<?php

declare(strict_types=1);

namespace Tests\Domain\RetentionToolkit\Services;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\SubscriptionMutationFactory;
use Tests\TestCase;
use Waterfront\Domain\RetentionToolkit\Enums\CustomerType;
use Waterfront\Domain\RetentionToolkit\Enums\ExecutionDate;
use Waterfront\Domain\RetentionToolkit\Enums\SelectedAction;
use Waterfront\Domain\RetentionToolkit\Exceptions\InvalidRetentionEffectiveDateException;
use Waterfront\Domain\RetentionToolkit\Services\RetentionEffectiveDateCalculator;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionMutationRepository;

#[CoversClass(RetentionEffectiveDateCalculator::class)]
class RetentionEffectiveDateCalculatorTest extends TestCase
{
    private const string NOW = '2026-01-31';

    private const string CONTRACT_START_DATE = '2025-01-01';

    private const string NOTICE_DATE = '2026-02-28';

    private const string FIXED_TERM_END_DATE = '2027-01-01';

    private const string INSUFFICIENT_NOTICE_END_DATE = '2026-02-27';

    private const string RENEWED_CONTRACT_END_DATE = '2028-02-27';

    private Subscription $subscription;

    private RetentionEffectiveDateCalculator $dateCalculator;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(self::NOW);

        $this->subscription = SubscriptionFactory::new()->makeOne([
            'start_date' => new CarbonImmutable(self::CONTRACT_START_DATE),
            'end_date' => new CarbonImmutable(self::NOTICE_DATE),
        ]);

        $subscriptionMutationRepository = self::createStub(
            SubscriptionMutationRepository::class,
        );
        $subscriptionMutationRepository->method('findOpenMutation')->willReturn(null);
        $this->dateCalculator = new RetentionEffectiveDateCalculator(
            $subscriptionMutationRepository,
        );
    }

    #[Test]
    public function immediateConsumerOfferUsesToday(): void
    {
        $result = $this->dateCalculator->calculate(
            subscription: $this->subscription,
            customerType: CustomerType::CONSUMER,
            selectedAction: SelectedAction::TK_OPTION_2,
            executionDate: ExecutionDate::IMMEDIATE,
        );

        self::assertSame(
            new CarbonImmutable(self::NOW)->getTimestamp(),
            $result->getTimestamp(),
        );
    }

    #[Test]
    public function businessOfferUsesContractEnd(): void
    {
        $result = $this->dateCalculator->calculate(
            subscription: $this->subscription,
            customerType: CustomerType::BUSINESS,
            selectedAction: SelectedAction::TK_OPTION_2,
            executionDate: ExecutionDate::CONTRACT_END,
        );

        self::assertSame(
            $this->subscription->end_date->getTimestamp(),
            $result->getTimestamp(),
        );
    }

    #[Test]
    public function rejectsImmediateBusinessExecution(): void
    {
        $this->expectException(InvalidRetentionEffectiveDateException::class);

        $this->dateCalculator->calculate(
            subscription: $this->subscription,
            customerType: CustomerType::BUSINESS,
            selectedAction: SelectedAction::RF,
            executionDate: ExecutionDate::IMMEDIATE,
        );
    }

    #[Test]
    public function consumerRfImmediateUsesNoticeDateAfterInitialTerm(): void
    {
        $result = $this->dateCalculator->calculate(
            subscription: $this->subscription,
            customerType: CustomerType::CONSUMER,
            selectedAction: SelectedAction::RF,
            executionDate: ExecutionDate::IMMEDIATE,
        );

        self::assertSame(
            new CarbonImmutable(self::NOTICE_DATE)->getTimestamp(),
            $result->getTimestamp(),
        );
    }

    #[Test]
    public function consumerRfImmediateRespectsInitialFixedTerm(): void
    {
        $this->subscription->contract_period = 24;

        $result = $this->dateCalculator->calculate(
            subscription: $this->subscription,
            customerType: CustomerType::CONSUMER,
            selectedAction: SelectedAction::RF,
            executionDate: ExecutionDate::IMMEDIATE,
        );

        self::assertSame(
            new CarbonImmutable(self::FIXED_TERM_END_DATE)->getTimestamp(),
            $result->getTimestamp(),
        );
    }

    #[Test]
    public function consumerRfContractEndUsesCurrentEndDate(): void
    {
        $result = $this->dateCalculator->calculate(
            subscription: $this->subscription,
            customerType: CustomerType::CONSUMER,
            selectedAction: SelectedAction::RF,
            executionDate: ExecutionDate::CONTRACT_END,
        );

        self::assertSame(
            $this->subscription->end_date->getTimestamp(),
            $result->getTimestamp(),
        );
    }

    #[Test]
    public function businessRfUsesCurrentEndDateAtNoticeBoundary(): void
    {
        $result = $this->dateCalculator->calculate(
            subscription: $this->subscription,
            customerType: CustomerType::BUSINESS,
            selectedAction: SelectedAction::RF,
            executionDate: ExecutionDate::CONTRACT_END,
        );

        self::assertSame(
            $this->subscription->end_date->getTimestamp(),
            $result->getTimestamp(),
        );
    }

    #[Test]
    public function businessRfUsesNextContractEndWithInsufficientNotice(): void
    {
        $this->subscription->end_date = new CarbonImmutable(
            self::INSUFFICIENT_NOTICE_END_DATE,
        );
        $this->subscription->contract_period = 24;

        $result = $this->dateCalculator->calculate(
            subscription: $this->subscription,
            customerType: CustomerType::BUSINESS,
            selectedAction: SelectedAction::RF,
            executionDate: ExecutionDate::CONTRACT_END,
        );

        self::assertSame(
            new CarbonImmutable(self::RENEWED_CONTRACT_END_DATE)->getTimestamp(),
            $result->getTimestamp(),
        );
    }

    #[Test]
    public function businessRfUsesOpenMutationPeriodForNextContractEnd(): void
    {
        $this->subscription->end_date = new CarbonImmutable(
            self::INSUFFICIENT_NOTICE_END_DATE,
        );
        $this->subscription->contract_period = 12;
        $openMutation = SubscriptionMutationFactory::new()->makeOne([
            'contract_period' => 24,
        ]);
        $subscriptionMutationRepository = self::createStub(
            SubscriptionMutationRepository::class,
        );
        $subscriptionMutationRepository->method('findOpenMutation')->willReturn($openMutation);
        $dateCalculator = new RetentionEffectiveDateCalculator(
            $subscriptionMutationRepository,
        );

        $result = $dateCalculator->calculate(
            subscription: $this->subscription,
            customerType: CustomerType::BUSINESS,
            selectedAction: SelectedAction::RF,
            executionDate: ExecutionDate::CONTRACT_END,
        );

        self::assertSame(
            new CarbonImmutable(self::RENEWED_CONTRACT_END_DATE)->getTimestamp(),
            $result->getTimestamp(),
        );
    }

    #[Test]
    public function consumerRfNoticeUsesCalendarMonthWithoutOverflow(): void
    {
        $result = $this->dateCalculator->calculate(
            subscription: $this->subscription,
            customerType: CustomerType::CONSUMER,
            selectedAction: SelectedAction::RF,
            executionDate: ExecutionDate::IMMEDIATE,
        );

        self::assertSame(
            new CarbonImmutable(self::NOTICE_DATE)->getTimestamp(),
            $result->getTimestamp(),
        );
    }
}
