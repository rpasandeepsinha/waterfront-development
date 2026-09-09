<?php

declare(strict_types=1);

namespace Tests\Domain\Admin\Integration\Actions;

use Illuminate\Bus\Dispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use SandwaveIo\HarborMessages\Message\MarkDebtorAsAbuse;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Admin\Actions\AnonymizeIdentitiesForCustomerAction;
use Waterfront\Domain\Admin\Actions\MarkCustomerAsAbuseAction;
use Waterfront\Domain\Notes\Actions\StoreNoteAction;
use Waterfront\Domain\Orders\Services\OrderService;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Jobs\CancelSubscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\Queue\HarborQueue;

#[CoversClass(CancelSubscription::class)]
#[CoversClass(MarkCustomerAsAbuseAction::class)]
class MarkCustomerAsAbuseActionTest extends IntegrationTestCase
{
    #[Test]
    public function anonymizeIdentitiesCustomerMarkedForAbuse(): void
    {
        $customer = new CustomerFactory()->withAddress()->createOne(['is_abuse' => false]);
        $anonymizeIdentitiesForCustomerAction = self::createMock(AnonymizeIdentitiesForCustomerAction::class);
        $orderService = self::createMock(OrderService::class);

        $anonymizeIdentitiesForCustomerAction
            ->expects(self::once())
            ->method('execute')
            ->with($customer->customer_number);

        $orderService
            ->expects(self::once())
            ->method('markNonProcessedAsAbuseForCustomer')
            ->with($customer);

        $harborQueue = self::createMock(HarborQueue::class);
        $harborQueue->expects(self::once())
            ->method('publish')
            ->with(
                self::callback(
                    static function (MarkDebtorAsAbuse $message) use ($customer) {
                        self::assertSame($customer->customer_number, $message->getCustomerNumber());
                        return true;
                    }
                )
            );

        $markCustomerAsAbuseAction = new MarkCustomerAsAbuseAction(
            self::resolve(Dispatcher::class),
            self::resolve(SubscriptionRepository::class),
            $anonymizeIdentitiesForCustomerAction,
            $orderService,
            $harborQueue,
            self::resolve(LoggerInterface::class),
            self::resolve(StoreNoteAction::class),
        );
        $markCustomerAsAbuseAction->execute($customer);
        $customer->refresh();
        self::assertTrue($customer->is_abuse);
    }

    #[Test]
    public function cancelsSubscriptions(): void
    {
        $action = new MarkCustomerAsAbuseAction(
            self::resolve(Dispatcher::class),
            self::resolve(SubscriptionRepository::class),
            self::createStub(AnonymizeIdentitiesForCustomerAction::class),
            self::resolve(OrderService::class),
            self::createStub(HarborQueue::class),
            self::resolve(LoggerInterface::class),
            self::resolve(StoreNoteAction::class),
        );
        $customer = CustomerFactory::new()->createOne();
        $product = ProductFactory::new()
            ->nlDomain()
            ->createOne();

        $subscriptionsToCancel = [
            $parentSubscription = SubscriptionFactory::new()
                ->for($customer)
                ->administrativeStatusActive()
                ->createOne([
                    'product_uuid' => $product->uuid,
                ]),
            SubscriptionFactory::new()
                ->for($customer)
                ->administrativeStatusActive()
                ->parentSubscription($parentSubscription)
                ->createOne([
                    'product_uuid' => $product->uuid,
                ]),
            SubscriptionFactory::new()
                ->for($customer)
                ->administrativeStatusInactive()
                ->createOne([
                    'product_uuid' => $product->uuid,
                ]),
        ];

        $subscriptionsToIgnore = [
            SubscriptionFactory::new()
                ->for($customer)
                ->administrativeStatusExpired()
                ->createOne([
                    'product_uuid' => $product->uuid,
                ]),
            SubscriptionFactory::new()
                ->for($customer)
                ->administrativeStatusArchived()
                ->createOne([
                    'product_uuid' => $product->uuid,
                ]),
            SubscriptionFactory::new()
                ->for($customer)
                ->administrativeStatusArchiving()
                ->createOne([
                    'product_uuid' => $product->uuid,
                ]),
        ];

        $action->execute($customer);

        foreach ($subscriptionsToCancel as $subscription) {
            $subscription->refresh();
            self::assertSame(AdministrativeStatus::CANCELED->value, $subscription->administrative_status);
        }

        foreach ($subscriptionsToIgnore as $subscription) {
            $oldStatus = $subscription->administrative_status;
            $subscription = $subscription->refresh();
            self::assertSame($oldStatus, $subscription->administrative_status);
        }
    }
}
