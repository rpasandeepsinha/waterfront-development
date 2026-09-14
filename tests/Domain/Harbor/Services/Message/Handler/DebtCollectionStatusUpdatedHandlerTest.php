<?php

declare(strict_types=1);

namespace Tests\Domain\Harbor\Services\Message\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use SandwaveIo\HarborMessages\Message\DebtCollectionStatusUpdated;
use SandwaveIo\HarborMessages\Message\Enum\DebtCollectionStatus;
use Tests\Factories\ProductFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Harbor\Services\Message\Handler\DebtCollectionStatusUpdatedHandler;
use Waterfront\Domain\Notes\Actions\StoreNoteAction;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Domain\Subscriptions\Services\CancellationService;
use Waterfront\Domain\Subscriptions\Services\SuspendSubscriptionService;
use Waterfront\Domain\Subscriptions\Services\UnsuspendSubscriptionService;

#[CoversClass(DebtCollectionStatusUpdatedHandler::class)]
class DebtCollectionStatusUpdatedHandlerTest extends IntegrationTestCase
{
    #[Test]
    public function handleNormalUnsuspendsSubscription(): void
    {
        $subscription = SubscriptionFactory::new()
            ->withCustomer()
            ->for(ProductFactory::new()->nlDomain())
            ->administrativeStatusSuspended()
            ->createOne();

        $message = new DebtCollectionStatusUpdated(
            1337,
            DebtCollectionStatus::NORMAL,
            20240000002,
            DebtCollectionStatus::NORMAL,
            [
                [
                    'id' => $subscription->id,
                    'debt_collection_status' => DebtCollectionStatus::NORMAL,
                ],
            ],
        );

        $unsuspendSubscriptionAction = self::createMock(UnsuspendSubscriptionService::class);
        $unsuspendSubscriptionAction
            ->expects(self::once())
            ->method('execute')
            ->with(self::callback(
                fn (Subscription $subscription) => $subscription->id === $message->getSubscriptions()[0]['id'],
            ));

        $storeNoteAction = self::createMock(StoreNoteAction::class);
        $storeNoteAction->expects(self::once())->method('execute');

        $cancellationService = self::createMock(CancellationService::class);
        $cancellationService->expects(self::never())->method('cancel');

        $handler = new DebtCollectionStatusUpdatedHandler(
            self::resolve(SubscriptionRepository::class),
            self::createStub(SuspendSubscriptionService::class),
            $unsuspendSubscriptionAction,
            $storeNoteAction,
            self::createStub(LoggerInterface::class),
            $cancellationService,
        );

        $handler->handle($message);
    }

    #[Test]
    public function handleNormalWhenNotSuspendedDoesNothing(): void
    {
        $subscription = SubscriptionFactory::new()
            ->withCustomer()
            ->for(ProductFactory::new()->nlDomain())
            ->administrativeStatusActive()
            ->createOne();

        $message = new DebtCollectionStatusUpdated(
            1337,
            DebtCollectionStatus::NORMAL,
            20240000002,
            DebtCollectionStatus::NORMAL,
            [
                [
                    'id' => $subscription->id,
                    'debt_collection_status' => DebtCollectionStatus::NORMAL,
                ],
            ],
        );

        $unsuspendSubscriptionAction = self::createMock(UnsuspendSubscriptionService::class);
        $unsuspendSubscriptionAction->expects(self::never())->method('execute');

        $storeNoteAction = self::createMock(StoreNoteAction::class);
        $storeNoteAction->expects(self::never())->method('execute');

        $cancellationService = self::createMock(CancellationService::class);
        $cancellationService->expects(self::never())->method('cancel');

        $handler = new DebtCollectionStatusUpdatedHandler(
            self::resolve(SubscriptionRepository::class),
            self::createStub(SuspendSubscriptionService::class),
            $unsuspendSubscriptionAction,
            $storeNoteAction,
            self::createStub(LoggerInterface::class),
            $cancellationService,
        );

        $handler->handle($message);
    }

    #[Test]
    public function handleInArrearsSuspendsSubscriptionIfInEligibleState(): void
    {
        // Ineligible state
        $subscription1 = SubscriptionFactory::new()
            ->withCustomer()
            ->for(ProductFactory::new()->nlDomain())
            ->administrativeStatusExpired()
            ->createOne();

        // Eligible state
        $subscription2 = SubscriptionFactory::new()
            ->withCustomer()
            ->for(ProductFactory::new()->hostingBrons())
            ->administrativeStatusActive()
            ->createOne();

        $message = new DebtCollectionStatusUpdated(
            1337,
            DebtCollectionStatus::IN_ARREARS,
            20240000002,
            DebtCollectionStatus::IN_ARREARS,
            [
                [
                    'id' => $subscription1->id,
                    'debt_collection_status' => DebtCollectionStatus::IN_ARREARS,
                ],
                [
                    'id' => $subscription2->id,
                    'debt_collection_status' => DebtCollectionStatus::IN_ARREARS,
                ],
            ],
        );

        $suspendSubscriptionAction = self::createMock(SuspendSubscriptionService::class);
        $suspendSubscriptionAction
            ->expects(self::once())
            ->method('execute')
            ->with(self::callback(
                fn (Subscription $subscription) => $subscription2->id === $message->getSubscriptions()[1]['id'],
            ));

        $storeNoteAction = self::createMock(StoreNoteAction::class);
        $storeNoteAction->expects(self::once())->method('execute');

        $cancellationService = self::createMock(CancellationService::class);
        $cancellationService->expects(self::never())->method('cancel');

        $handler = new DebtCollectionStatusUpdatedHandler(
            self::resolve(SubscriptionRepository::class),
            $suspendSubscriptionAction,
            self::createStub(UnsuspendSubscriptionService::class),
            $storeNoteAction,
            self::createStub(LoggerInterface::class),
            $cancellationService,
        );

        $handler->handle($message);
    }

    #[Test]
    public function handleInArrearsDoesNotSuspendSubscriptionIfNotInEligibleStateForSuspension(): void
    {
        $subscription = SubscriptionFactory::new()
            ->withCustomer()
            ->for(ProductFactory::new()->nlDomain())
            ->administrativeStatusExpired()
            ->createOne();

        $message = new DebtCollectionStatusUpdated(
            1337,
            DebtCollectionStatus::IN_ARREARS,
            20240000002,
            DebtCollectionStatus::IN_ARREARS,
            [
                [
                    'id' => $subscription->id,
                    'debt_collection_status' => DebtCollectionStatus::IN_ARREARS,
                ],
            ],
        );

        $suspendSubscriptionAction = self::createMock(SuspendSubscriptionService::class);
        $suspendSubscriptionAction->expects(self::never())->method('execute');

        $storeNoteAction = self::createMock(StoreNoteAction::class);
        $storeNoteAction->expects(self::never())->method('execute');

        $cancellationService = self::createMock(CancellationService::class);
        $cancellationService->expects(self::never())->method('cancel');

        $handler = new DebtCollectionStatusUpdatedHandler(
            self::resolve(SubscriptionRepository::class),
            $suspendSubscriptionAction,
            self::createStub(UnsuspendSubscriptionService::class),
            $storeNoteAction,
            self::createStub(LoggerInterface::class),
            $cancellationService,
        );

        $handler->handle($message);
    }

    #[Test]
    public function handleBadDebtCancelsSuspendedSubscription(): void
    {
        $product = ProductFactory::new()->nlDomain()->createOne();
        $suspendedSubscription = SubscriptionFactory::new()
            ->withCustomer()
            ->for($product)
            ->administrativeStatusSuspended()
            ->createOne();
        $activeSubscription = SubscriptionFactory::new()
            ->withCustomer()
            ->for($product)
            ->administrativeStatusActive()
            ->createOne();

        $message = new DebtCollectionStatusUpdated(
            1337,
            DebtCollectionStatus::BAD_DEBT,
            20240000002,
            DebtCollectionStatus::BAD_DEBT,
            [
                [ // expected scenario.
                    'id' => $suspendedSubscription->id,
                    'debt_collection_status' => DebtCollectionStatus::BAD_DEBT,
                ],
                [ // "what if" scenario.
                    'id' => $activeSubscription->id,
                    'debt_collection_status' => DebtCollectionStatus::BAD_DEBT,
                ],
            ],
        );

        $suspendSubscriptionAction = self::createMock(SuspendSubscriptionService::class);
        $suspendSubscriptionAction->expects(self::never())->method('execute');

        $cancellationService = self::createMock(CancellationService::class);
        $cancellationService
            ->expects(self::once())
            ->method('cancel')
            ->with(self::callback(
                fn (Subscription $subscription): bool => $subscription->id === $suspendedSubscription->id,
            ));

        $handler = new DebtCollectionStatusUpdatedHandler(
            self::resolve(SubscriptionRepository::class),
            $suspendSubscriptionAction,
            self::createStub(UnsuspendSubscriptionService::class),
            self::createStub(StoreNoteAction::class),
            self::createStub(LoggerInterface::class),
            $cancellationService,
        );

        $handler->handle($message);
    }

    #[Test]
    public function handleInArrearsDoesNotSuspendSubscriptionIfSubscriptionIsCanceledForBadDebt(): void
    {
        $subscription = SubscriptionFactory::new(
            ['cancel_reason' => SubscriptionCancelReason::REASON_BAD_DEBT],
        )
            ->withCustomer()
            ->for(ProductFactory::new()->nlDomain())
            ->administrativeStatusCancelled()
            ->createOne();

        $message = new DebtCollectionStatusUpdated(
            1337,
            DebtCollectionStatus::IN_ARREARS,
            20240000002,
            DebtCollectionStatus::IN_ARREARS,
            [
                [
                    'id' => $subscription->id,
                    'debt_collection_status' => DebtCollectionStatus::IN_ARREARS,
                ],
            ],
        );

        $suspendSubscriptionAction = self::createMock(SuspendSubscriptionService::class);
        $suspendSubscriptionAction->expects(self::never())->method('execute');

        $storeNoteAction = self::createMock(StoreNoteAction::class);
        $storeNoteAction->expects(self::never())->method('execute');

        $cancellationService = self::createMock(CancellationService::class);
        $cancellationService->expects(self::never())->method('cancel');

        $handler = new DebtCollectionStatusUpdatedHandler(
            self::resolve(SubscriptionRepository::class),
            $suspendSubscriptionAction,
            self::createStub(UnsuspendSubscriptionService::class),
            $storeNoteAction,
            self::createStub(LoggerInterface::class),
            $cancellationService,
        );

        $handler->handle($message);
    }
}
