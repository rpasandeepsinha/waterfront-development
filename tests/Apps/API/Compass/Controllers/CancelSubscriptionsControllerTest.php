<?php

declare(strict_types=1);

namespace Tests\Apps\API\Compass\Controllers;

use Carbon\CarbonImmutable;
use Generator;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Controllers\CancelSubscriptionsController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Subscriptions\Actions\CancelSubscriptionsAction;
use Waterfront\Domain\Subscriptions\DTO\Cancellation;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelType;
use Waterfront\Domain\Subscriptions\Exceptions\CancelCreditSubscriptionsException;
use Waterfront\Domain\Subscriptions\Exceptions\CreditSubscriptionsException;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\CreditSubscriptionService;
use Waterfront\Infra\Common\DateTimeFormat;

#[CoversClass(CancelSubscriptionsController::class)]
#[AllowMockObjectsWithoutExpectations]
class CancelSubscriptionsControllerTest extends IntegrationTestCase
{
    private const string STAGE_CREDIT = 'credit';

    private const string STAGE_CANCEL = 'cancel';

    private Customer $customer;

    private ProductGroup $productGroup;

    private Product $product;

    private CancelSubscriptionsAction&MockObject $cancelSubscriptionsAction;

    /**
     * @var list<string>
     */
    private array $performedStages = [];

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::now());

        $this->customer = new CustomerFactory()->createOne();
        $this->productGroup = new ProductGroupFactory()->dns()->createOne();
        $this->product = new ProductFactory()->for($this->productGroup)->createOne();

        $this->cancelSubscriptionsAction = self::createMock(CancelSubscriptionsAction::class);
        $this->app->bind(
            CancelSubscriptionsAction::class,
            fn (): CancelSubscriptionsAction => $this->cancelSubscriptionsAction,
        );
    }

    /**
     * @param list<string> $expectedStages
     */
    #[DataProvider('cancellationPayloadProvider')]
    #[Test]
    public function cancelBuildsCancellationFromPayloadAndExecutesIt(
        SubscriptionCancelReason $reason,
        ?string $reasonOther,
        SubscriptionCancelType $type,
        ?int $typeOtherDateDaysFromNow,
        bool $credit,
        array $expectedStages,
    ): void {
        $subscription = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->for($this->product)
            ->for($this->customer)
            ->createOne();
        $typeOtherDate = $typeOtherDateDaysFromNow === null
            ? null
            : CarbonImmutable::now()->addDays($typeOtherDateDaysFromNow);

        $matchesCancellation = self::callback(
            static fn (Cancellation $cancellation): bool => (
                $cancellation->getSubscriptions()->count() === 1
                && $cancellation->getSubscriptions()->firstOrFail()->uuid === $subscription->uuid
                && $cancellation->getCancelReason() === $reason
                && $cancellation->getCancelReasonOther() === $reasonOther
                && $cancellation->getCancelType() === $type
                && $cancellation
                    ->getSelectedCancellationEndDate()
                    ?->format(DateTimeFormat::DATE) === $typeOtherDate?->format(DateTimeFormat::DATE)
                && $cancellation->shouldCreditRelatedInvoices() === $credit
            ),
        );

        $creditSubscriptionService = self::createMock(CreditSubscriptionService::class);
        $creditSubscriptionService
            ->expects($credit ? self::once() : self::never())
            ->method('creditSubscriptions')
            ->with($matchesCancellation)
            ->willReturnCallback($this->recordStage(self::STAGE_CREDIT));
        $this->app->bind(
            CreditSubscriptionService::class,
            fn (): CreditSubscriptionService => $creditSubscriptionService,
        );

        $this->cancelSubscriptionsAction
            ->expects(self::once())
            ->method('execute')
            ->with($matchesCancellation)
            ->willReturnCallback($this->recordStage(self::STAGE_CANCEL));

        $this->actingAsEmployee()
            ->postJson(
                $this->generateRoute('admin.subscriptions.cancel-and-credit'),
                [
                    'subscription_uuids' => [$subscription->uuid],
                    'reason' => $reason->value,
                    'reason_other' => $reasonOther,
                    'type' => $type->value,
                    'type_other_date' => $typeOtherDate?->format(DateTimeFormat::DATE),
                    'credit' => $credit,
                ],
            )
            ->assertNoContent();

        self::assertSame($expectedStages, $this->performedStages);
    }

    /**
     * @return Generator<string, array{
     *     SubscriptionCancelReason,
     *     ?string,
     *     SubscriptionCancelType,
     *     ?int,
     *     bool,
     *     list<string>
     * }>
     */
    public static function cancellationPayloadProvider(): Generator
    {
        yield 'on the end date without crediting' => [
            SubscriptionCancelReason::REASON_CANCELLATION,
            null,
            SubscriptionCancelType::CANCEL_END_DATE,
            null,
            false,
            [self::STAGE_CANCEL],
        ];

        yield 'on another date with crediting' => [
            SubscriptionCancelReason::REASON_WET_VAN_DAM,
            null,
            SubscriptionCancelType::CANCEL_OTHER,
            -10,
            true,
            [self::STAGE_CREDIT, self::STAGE_CANCEL],
        ];

        yield 'with a free form reason' => [
            SubscriptionCancelReason::REASON_OTHER,
            'Customer moved to another provider',
            SubscriptionCancelType::CANCEL_END_DATE,
            null,
            false,
            [self::STAGE_CANCEL],
        ];
    }

    #[DataProvider('creditingNotAllowedProvider')]
    #[Test]
    public function cancelDerivesCreditFlagInsteadOfTrustingThePayload(
        SubscriptionCancelReason $reason,
        SubscriptionCancelType $type,
    ): void {
        $subscription = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->for($this->product)
            ->for($this->customer)
            ->createOne();

        $creditSubscriptionService = self::createMock(CreditSubscriptionService::class);
        $creditSubscriptionService->expects(self::never())->method('creditSubscriptions');
        $this->app->bind(
            CreditSubscriptionService::class,
            fn (): CreditSubscriptionService => $creditSubscriptionService,
        );

        $this->cancelSubscriptionsAction
            ->expects(self::once())
            ->method('execute')
            ->with(self::callback(
                static fn (Cancellation $cancellation): bool => $cancellation->shouldCreditRelatedInvoices() === false,
            ));

        $this->actingAsEmployee()
            ->postJson(
                $this->generateRoute('admin.subscriptions.cancel-and-credit'),
                [
                    'subscription_uuids' => [$subscription->uuid],
                    'reason' => $reason->value,
                    'type' => $type->value,
                    'type_other_date' => $type === SubscriptionCancelType::CANCEL_OTHER
                        ? CarbonImmutable::now()->format(DateTimeFormat::DATE)
                        : null,
                    'credit' => true,
                ],
            )
            ->assertNoContent();
    }

    /**
     * @return Generator<string, array{SubscriptionCancelReason, SubscriptionCancelType}>
     */
    public static function creditingNotAllowedProvider(): Generator
    {
        yield 'cancelling on the end date' => [
            SubscriptionCancelReason::REASON_WET_VAN_DAM,
            SubscriptionCancelType::CANCEL_END_DATE,
        ];

        yield 'cancelling because of a transfer' => [
            SubscriptionCancelReason::REASON_TRANSFER,
            SubscriptionCancelType::CANCEL_OTHER,
        ];

        yield 'cancelling because of bad debt' => [
            SubscriptionCancelReason::REASON_BAD_DEBT,
            SubscriptionCancelType::CANCEL_OTHER,
        ];

        yield 'cancelling because the domain was transferred away' => [
            SubscriptionCancelReason::REASON_DOMAIN_TRANSFERRED_AWAY,
            SubscriptionCancelType::CANCEL_OTHER,
        ];
    }

    #[DataProvider('allowCancelAsChildProvider')]
    #[Test]
    public function cancelOfChildSubscriptionDependsOnTheAllowCancelAsChildSpec(
        ?bool $allowCancelAsChild,
        bool $expectCancellation,
    ): void {
        $parentSub = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->for($this->product)
            ->for($this->customer)
            ->createOne();
        $childProduct = new ProductFactory()->for($this->productGroup)->createOne();
        $childSubscription = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->for($childProduct)
            ->for($this->customer)
            ->createOne(['parent_subscription_id' => $parentSub->id]);

        if ($allowCancelAsChild !== null) {
            $productSpecFactory = new ProductSpecFactory();
            $allowCancelAsChild
                ? $productSpecFactory
                    ->enable(ProductSpecName::PRODUCT_ALLOW_CANCEL_AS_CHILD)
                    ->for($childProduct)
                    ->createOne()
                : $productSpecFactory
                    ->disable(ProductSpecName::PRODUCT_ALLOW_CANCEL_AS_CHILD)
                    ->for($childProduct)
                    ->createOne();
        }

        if ($expectCancellation) {
            $this->cancelSubscriptionsAction
                ->expects(self::once())
                ->method('execute')
                ->with(self::callback(
                    static fn (Cancellation $cancellation): bool => (
                        $cancellation->getSubscriptions()->count() === 1
                        && $cancellation->getSubscriptions()->firstOrFail()->uuid === $childSubscription->uuid
                    ),
                ));
        } else {
            $creditSubscriptionService = self::createStub(creditSubscriptionService::class);
            $creditSubscriptionService->method('creditSubscriptions');

            $this->cancelSubscriptionsAction->expects(self::never())->method('execute');
        }

        $response = $this->actingAsEmployee()->postJson(
            $this->generateRoute('admin.subscriptions.cancel-and-credit'),
            [
                'subscription_uuids' => [$childSubscription->uuid],
                'reason' => SubscriptionCancelReason::REASON_CANCELLATION->value,
                'type' => SubscriptionCancelType::CANCEL_END_DATE->value,
                'credit' => false,
            ],
        );

        if ($expectCancellation) {
            $response->assertNoContent();

            return;
        }

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'subscription_uuids' => 'subscription.cancel.failure_cancel_with_parent',
            ]);
    }

    /**
     * @return Generator<string, array{?bool, bool}>
     */
    public static function allowCancelAsChildProvider(): Generator
    {
        yield 'specification enabled' => [true, true];
        yield 'specification disabled' => [false, false];
        yield 'specification missing' => [null, false];
    }

    #[Test]
    public function cancelFailsWhenSubscriptionsBelongToMultipleCustomers(): void
    {
        $subscription = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->for($this->product)
            ->for($this->customer)
            ->createOne();
        $otherCustomersSubscription = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->for($this->product)
            ->for(new CustomerFactory()->createOne())
            ->createOne();

        $creditSubscriptionService = self::createStub(CreditSubscriptionService::class);
        $creditSubscriptionService->method('creditSubscriptions');
        $this->app->bind(
            CreditSubscriptionService::class,
            fn (): CreditSubscriptionService => $creditSubscriptionService,
        );

        $this->cancelSubscriptionsAction->expects(self::never())->method('execute');

        $this->actingAsEmployee()
            ->postJson(
                $this->generateRoute('admin.subscriptions.cancel-and-credit'),
                [
                    'subscription_uuids' => [$subscription->uuid, $otherCustomersSubscription->uuid],
                    'reason' => SubscriptionCancelReason::REASON_CANCELLATION->value,
                    'type' => SubscriptionCancelType::CANCEL_END_DATE->value,
                    'credit' => false,
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'subscription_uuids' => 'subscription.cancel.failure_multi_customers',
            ]);
    }

    #[Test]
    public function cancelReportsEveryReasonWhyTheSelectionCannotBeCancelled(): void
    {
        $subscription = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->for($this->product)
            ->for($this->customer)
            ->createOne();
        $otherCustomersArchivedSubscription = new SubscriptionFactory()
            ->for($this->product)
            ->for(new CustomerFactory()->createOne())
            ->createOne(['administrative_status' => AdministrativeStatus::ARCHIVED->value]);

        $creditSubscriptionService = self::createStub(CreditSubscriptionService::class);
        $creditSubscriptionService->method('creditSubscriptions');
        $this->app->bind(
            CreditSubscriptionService::class,
            fn (): CreditSubscriptionService => $creditSubscriptionService,
        );

        $this->cancelSubscriptionsAction->expects(self::never())->method('execute');

        $this->actingAsEmployee()
            ->postJson(
                $this->generateRoute('admin.subscriptions.cancel-and-credit'),
                [
                    'subscription_uuids' => [$subscription->uuid, $otherCustomersArchivedSubscription->uuid],
                    'reason' => SubscriptionCancelReason::REASON_CANCELLATION->value,
                    'type' => SubscriptionCancelType::CANCEL_END_DATE->value,
                    'credit' => false,
                ],
            )
            ->assertUnprocessable()
            ->assertJsonPath('errors.subscription_uuids', [
                'subscription.cancel.failure_multi_customers',
                'subscription.cancel.failure_non_cancellable',
            ]);
    }

    /**
     * @param list<string> $statuses
     */
    #[DataProvider('nonCancellableStatusesProvider')]
    #[Test]
    public function cancelFailsWhenAnySubscriptionIsNotCancellable(array $statuses): void
    {
        $subscriptions = [];
        foreach ($statuses as $status) {
            $subscription = new SubscriptionFactory()
                ->for($this->product)
                ->for($this->customer)
                ->createOne(['administrative_status' => $status]);
            $subscriptions[] = $subscription;
        }

        $creditSubscriptionService = self::createStub(CreditSubscriptionService::class);
        $creditSubscriptionService->method('creditSubscriptions');
        $this->app->bind(
            CreditSubscriptionService::class,
            fn (): CreditSubscriptionService => $creditSubscriptionService,
        );

        $this->cancelSubscriptionsAction->expects(self::never())->method('execute');

        $this->actingAsEmployee()
            ->postJson(
                $this->generateRoute('admin.subscriptions.cancel-and-credit'),
                [
                    'subscription_uuids' => array_map(
                        static fn (Subscription $subscription): string => $subscription->uuid,
                        $subscriptions,
                    ),
                    'reason' => SubscriptionCancelReason::REASON_CANCELLATION->value,
                    'type' => SubscriptionCancelType::CANCEL_END_DATE->value,
                    'credit' => false,
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'subscription_uuids' => 'subscription.cancel.failure_non_cancellable',
            ]);
    }

    /**
     * @return Generator<string, array{list<string>}>
     */
    public static function nonCancellableStatusesProvider(): Generator
    {
        yield 'archiving' => [[AdministrativeStatus::ARCHIVING->value]];
        yield 'archived' => [[AdministrativeStatus::ARCHIVED->value]];
        yield 'expired' => [[AdministrativeStatus::EXPIRED->value]];
        yield 'active mixed with archived' => [
            [AdministrativeStatus::ACTIVE->value, AdministrativeStatus::ARCHIVED->value],
        ];
    }

    #[DataProvider('failingStageProvider')]
    #[Test]
    public function cancelReturnsServerErrorWhenExecutionFails(string $failingStage): void
    {
        $subscription = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->for($this->product)
            ->for($this->customer)
            ->createOne();
        $creditingRequested = $failingStage === self::STAGE_CREDIT;

        $creditSubscriptionService = self::createMock(CreditSubscriptionService::class);

        if ($creditingRequested) {
            $creditSubscriptionService
                ->expects(self::once())
                ->method('creditSubscriptions')
                ->willThrowException(new CreditSubscriptionsException('Harbor is unavailable'));

            $this->cancelSubscriptionsAction->expects(self::never())->method('execute');
        } else {
            $creditSubscriptionService->expects(self::never())->method('creditSubscriptions');

            $this->cancelSubscriptionsAction
                ->expects(self::once())
                ->method('execute')
                ->willThrowException(new CancelCreditSubscriptionsException('Failed to cancel selected subscriptions'));
        }

        $this->app->bind(
            CreditSubscriptionService::class,
            fn (): CreditSubscriptionService => $creditSubscriptionService,
        );

        $this->actingAsEmployee()
            ->postJson(
                $this->generateRoute('admin.subscriptions.cancel-and-credit'),
                [
                    'subscription_uuids' => [$subscription->uuid],
                    'reason' => SubscriptionCancelReason::REASON_WET_VAN_DAM->value,
                    'type' => $creditingRequested
                        ? SubscriptionCancelType::CANCEL_OTHER->value
                        : SubscriptionCancelType::CANCEL_END_DATE->value,
                    'type_other_date' => $creditingRequested
                        ? CarbonImmutable::now()->format(DateTimeFormat::DATE)
                        : null,
                    'credit' => $creditingRequested,
                ],
            )
            ->assertInternalServerError()
            ->assertJson(['message' => 'subscription.cancel.failure_execution']);
    }

    /**
     * @return Generator<string, array{string}>
     */
    public static function failingStageProvider(): Generator
    {
        yield 'crediting fails' => [self::STAGE_CREDIT];
        yield 'cancelling fails' => [self::STAGE_CANCEL];
    }

    private function recordStage(string $stage): callable
    {
        return function () use ($stage): void {
            $this->performedStages[] = $stage;
        };
    }
}
