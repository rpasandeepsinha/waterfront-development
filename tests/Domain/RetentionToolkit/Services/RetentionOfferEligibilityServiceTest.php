<?php

declare(strict_types=1);

namespace Tests\Domain\RetentionToolkit\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\SubscriptionMutationFactory;
use Tests\TestCase;
use Waterfront\Domain\RetentionToolkit\DTO\RetentionOfferEligibilityResultDTO;
use Waterfront\Domain\RetentionToolkit\Enums\RetentionOfferEligibilityCode;
use Waterfront\Domain\RetentionToolkit\Enums\SelectedAction;
use Waterfront\Domain\RetentionToolkit\Services\RetentionOfferActionEligibilityService;
use Waterfront\Domain\RetentionToolkit\Services\RetentionOfferEligibilityService;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionMutationRepository;

#[CoversClass(RetentionOfferEligibilityService::class)]
class RetentionOfferEligibilityServiceTest extends TestCase
{
    private Subscription $subscription;

    private SubscriptionMutationRepository&Stub $subscriptionMutationRepository;

    private RetentionOfferActionEligibilityService&Stub $actionEligibilityService;

    private RetentionOfferEligibilityService $eligibilityService;

    protected function setUp(): void
    {
        parent::setUp();

        $productGroup = ProductGroupFactory::new()->hosting()->makeOne();
        $product = ProductFactory::new()->hostingGold($productGroup)->makeOne();
        $product->setRelation('productGroup', $productGroup);

        $this->subscription = SubscriptionFactory::new()->administrativeStatusActive()->makeOne();
        $this->subscription->setRelation('product', $product);

        $this->subscriptionMutationRepository = self::createStub(
            SubscriptionMutationRepository::class,
        );
        $this->subscriptionMutationRepository->method('findOpenMutation')->willReturn(null);

        $this->actionEligibilityService = self::createStub(
            RetentionOfferActionEligibilityService::class,
        );

        $this->eligibilityService = new RetentionOfferEligibilityService(
            $this->subscriptionMutationRepository,
            $this->actionEligibilityService,
        );
    }

    #[Test]
    public function rejectsInactiveSubscription(): void
    {
        $subscription = SubscriptionFactory::new()->administrativeStatusCancelled()->makeOne();

        $result = $this->eligibilityService->determineEligibility(
            subscription: $subscription,
            selectedAction: SelectedAction::TK_OPTION_2,
            contractPeriod: 12,
            billingPeriod: 12,
            targetProduct: null,
        );

        self::assertSame(
            RetentionOfferEligibilityCode::INACTIVE_SUBSCRIPTION,
            $result->code,
        );
    }

    #[Test]
    public function rejectsMismatchedContractPeriod(): void
    {
        $result = $this->eligibilityService->determineEligibility(
            subscription: $this->subscription,
            selectedAction: SelectedAction::TK_OPTION_2,
            contractPeriod: 24,
            billingPeriod: 12,
            targetProduct: null,
        );

        self::assertSame(
            RetentionOfferEligibilityCode::INVALID_CONTRACT_PERIOD,
            $result->code,
        );
    }

    #[Test]
    public function rejectsMismatchedBillingPeriod(): void
    {
        $result = $this->eligibilityService->determineEligibility(
            subscription: $this->subscription,
            selectedAction: SelectedAction::TK_OPTION_2,
            contractPeriod: 12,
            billingPeriod: 1,
            targetProduct: null,
        );

        self::assertSame(
            RetentionOfferEligibilityCode::INVALID_BILLING_PERIOD,
            $result->code,
        );
    }

    #[Test]
    public function rejectsCommercialOfferWhenSubscriptionHasOpenMutation(): void
    {
        $subscriptionMutationRepository = self::createStub(
            SubscriptionMutationRepository::class,
        );
        $subscriptionMutationRepository
            ->method('findOpenMutation')
            ->willReturn(SubscriptionMutationFactory::new()->makeOne());

        $service = new RetentionOfferEligibilityService(
            $subscriptionMutationRepository,
            $this->actionEligibilityService,
        );

        $result = $service->determineEligibility(
            subscription: $this->subscription,
            selectedAction: SelectedAction::TK_OPTION_2,
            contractPeriod: 12,
            billingPeriod: 12,
            targetProduct: null,
        );

        self::assertSame(
            RetentionOfferEligibilityCode::OPEN_MUTATION,
            $result->code,
        );
        self::assertSame(
            'The subscription has an open mutation that must be reviewed first.',
            $result->reason,
        );
    }

    #[DataProvider('nonPricedActionProvider')]
    #[Test]
    public function nonPricedActionSkipsCommercialEligibilityChecks(
        SelectedAction $selectedAction,
    ): void {
        $subscriptionMutationRepository = self::createMock(
            SubscriptionMutationRepository::class,
        );
        $subscriptionMutationRepository->expects(self::never())->method('findOpenMutation');

        $service = new RetentionOfferEligibilityService(
            $subscriptionMutationRepository,
            $this->actionEligibilityService,
        );

        $result = $service->determineEligibility(
            subscription: $this->subscription,
            selectedAction: $selectedAction,
            contractPeriod: 24,
            billingPeriod: 24,
            targetProduct: null,
        );

        self::assertSame(
            RetentionOfferEligibilityCode::NO_PRICE_REQUIRED,
            $result->code,
        );
    }

    /** @return iterable<string, array{SelectedAction}> */
    public static function nonPricedActionProvider(): iterable
    {
        yield 'RF' => [SelectedAction::RF];
        yield 'BZ' => [SelectedAction::BZ];
    }

    #[Test]
    public function acceptsTkOptionTwoForActiveSubscription(): void
    {
        $result = $this->eligibilityService->determineEligibility(
            subscription: $this->subscription,
            selectedAction: SelectedAction::TK_OPTION_2,
            contractPeriod: 12,
            billingPeriod: 12,
            targetProduct: null,
        );

        self::assertSame(
            RetentionOfferEligibilityCode::ELIGIBLE,
            $result->code,
        );
    }

    /** @param non-empty-string $eligibilityMethod */
    #[DataProvider('actionSpecificEligibilityProvider')]
    #[Test]
    public function delegatesActionSpecificEligibility(
        SelectedAction $selectedAction,
        int $contractPeriod,
        int $billingPeriod,
        string $eligibilityMethod,
    ): void {
        $expectedResult = new RetentionOfferEligibilityResultDTO(
            code: RetentionOfferEligibilityCode::ELIGIBLE,
            reason: null,
        );

        $actionEligibilityService = self::createMock(
            RetentionOfferActionEligibilityService::class,
        );
        $actionEligibilityService->expects(self::once())->method($eligibilityMethod)->willReturn($expectedResult);

        $eligibilityService = new RetentionOfferEligibilityService(
            $this->subscriptionMutationRepository,
            $actionEligibilityService,
        );

        $targetProduct = in_array(
            $selectedAction,
            [
                SelectedAction::DG_OPTION_1A,
                SelectedAction::DG_OPTION_1D,
            ],
            true,
        )
            ? ProductFactory::new()->makeOne()
            : null;

        $result = $eligibilityService->determineEligibility(
            subscription: $this->subscription,
            selectedAction: $selectedAction,
            contractPeriod: $contractPeriod,
            billingPeriod: $billingPeriod,
            targetProduct: $targetProduct,
        );

        self::assertSame(
            $expectedResult,
            $result,
        );
    }

    /** @return iterable<string, array{SelectedAction, positive-int, positive-int, non-empty-string}> */
    public static function actionSpecificEligibilityProvider(): iterable
    {
        yield 'DM Option 1' => [
            SelectedAction::DM_OPTION_1,
            12,
            12,
            'determineDmOptionOneEligibility',
        ];
        yield 'DG Option 1A' => [
            SelectedAction::DG_OPTION_1A,
            24,
            1,
            'determineDowngradeEligibility',
        ];
        yield 'DG Option 1D' => [
            SelectedAction::DG_OPTION_1D,
            36,
            36,
            'determineDowngradeEligibility',
        ];
        yield 'TK Option 3' => [
            SelectedAction::TK_OPTION_3,
            12,
            12,
            'determineHostingEligibility',
        ];
        yield 'TK Option 5' => [
            SelectedAction::TK_OPTION_5,
            12,
            12,
            'determineDomainOrHostingEligibility',
        ];
        yield 'TK Option 6' => [
            SelectedAction::TK_OPTION_6,
            12,
            12,
            'determineDomainOrHostingEligibility',
        ];
    }
}
