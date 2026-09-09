<?php

declare(strict_types=1);

namespace Tests\Domain\Transfers\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\TransferFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Transfers\Enums\TransferStatus;
use Waterfront\Domain\Transfers\Mailer\MailTransferCreatedReceiver;
use Waterfront\Domain\Transfers\Mailer\MailTransferCreatedSender;
use Waterfront\Domain\Transfers\Models\Transfer;
use Waterfront\Domain\Transfers\Services\TransferService;

#[CoversClass(TransferService::class)]
class TransferServiceTest extends IntegrationTestCase
{
    private TransferService $transfers;

    private Customer $receiver;

    private Customer $from;

    /**
     * @var Collection<int, Subscription>
     */
    private Collection $subscriptions;

    public function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        Model::preventLazyLoading(false);

        $this->transfers = self::resolve(TransferService::class);
        $this->receiver  = new CustomerFactory()->createOne();
        $this->from      = new CustomerFactory()->createOne();
        $this->subscriptions = $this->getTestingSubscriptions();
    }

    #[Test]
    public function transferCreate(): void
    {
        self::assertEmailsSend([
            MailTransferCreatedSender::class,
            MailTransferCreatedReceiver::class,
        ]);
        $transferService = self::resolve(TransferService::class);

        $collection = $this->subscriptions;
        $transfer   = $transferService->createTransfer($collection, $this->from, $this->receiver);

        self::assertSame(Transfer::class, $transfer::class);
        self::assertDatabaseHas(
            'transfers',
            [
                'from_customer_id' => $this->from->id,
                'to_customer_id'   => $this->receiver->id,
            ]
        );

        $firstSubscription = $collection->first();
        self::assertInstanceOf(Subscription::class, $firstSubscription);

        self::assertDatabaseHas(
            'subscription_transfer',
            [
                'subscription_id' => $firstSubscription->id,
                'transfer_id'     => $transfer->id,
            ]
        );

        self::assertSame(TransferStatus::REQUESTED, $transfer->getStatus());
    }

    #[Test]
    public function transferCreateAlreadyCreatedForSubscription(): void
    {
        self::assertEmailsSend([
            MailTransferCreatedSender::class,
            MailTransferCreatedReceiver::class,
        ]);
        $transferService = self::resolve(TransferService::class);

        $collection = $this->subscriptions;
        $transfer   = $transferService->createTransfer($collection, $this->from, $this->receiver);

        self::assertDatabaseHas(
            'transfers',
            [
                'from_customer_id' => $this->from->id,
                'to_customer_id'   => $this->receiver->id,
            ]
        );

        self::assertSame(TransferStatus::REQUESTED, $transfer->getStatus());

        $collection = $collection->map(function (Subscription $subscription): Subscription {
            $subscription = $subscription->fresh();
            self::assertInstanceOf(Subscription::class, $subscription);
            return $subscription;
        });
        try {
            $this->transfers->createTransfer($collection, $this->from, $this->receiver);
            Assert::fail('Able to duplicate Transfer while one is still open for a set of subscriptions');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('is not allowed to transfer.', $exception->getMessage());
        }
    }

    #[Test]
    public function validateSubscriptionsSuccessWithUnrelatedSubscriptions(): void
    {
        $collection = $this->subscriptions;
        $validated  = $this->transfers->validateSubscriptions($collection, $this->from);

        self::assertTrue(
            $validated,
            'validation for TransferService validateSubscriptions failed with valid payload.'
        );
    }

    #[Test]
    public function validateSubscriptionsFailed(): void
    {
        $collection = $this->subscriptions;
        $transfer = new TransferFactory()->createOne([
            'from_customer_id' => $this->from->id,
            'to_customer_id' => $this->receiver->id,
        ]);
        $subscription = $collection->first();
        self::assertInstanceOf(Subscription::class, $subscription);

        $subscription->transfers()->save($transfer);

        $validated  = $this->transfers->validateSubscriptions($collection, $this->from);

        self::assertFalse(
            $validated,
            'validation for TransferService validateSubscriptions failed with invalid payload. It still returned true. (Subscription check)'
        );
    }

    #[Test]
    public function validateDifferentCustomer(): void
    {
        $collection = $this->subscriptions;
        $differentCustomer = new CustomerFactory()->createOne();
        $subscription = $collection->first();
        self::assertInstanceOf(Subscription::class, $subscription);

        $subscription->customer_id = $differentCustomer->id;
        $subscription->save();

        $validated  = $this->transfers->validateSubscriptions($collection, $this->from);

        self::assertFalse(
            $validated,
            'validation for TransferService validateSubscriptions failed with invalid payload.It still returned true. (Customer check)'
        );
    }

    #[Test]
    public function transferMicrosoft365Subscription(): void
    {
        $group  = new ProductGroupFactory()->createOne(['slug' => ProductGroupType::MICROSOFT_365, 'name' => 'Microsoft 365']);

        $product = new ProductFactory()->createOne([
            'product_group_id' => $group->id,
            'name' => 'Business Basic',
            'slug' => 'microsoft-business-basic',
        ]);

        $subscription = new SubscriptionFactory()->withCustomer()->createOne([
            'product_uuid' => $product->uuid,
            'customer_id' => $this->from->id,
        ]);

        $collection = new Collection([$subscription]);

        $this->expectException(InvalidArgumentException::class);
        $this->transfers->createTransfer($collection, $this->from, $this->receiver);
    }

    /**
     * @return Collection<int, Subscription>
     */
    private function getTestingSubscriptions(): Collection
    {
        $group  = new ProductGroupFactory()->createOne(['slug' => 'extension', 'name' => 'Extension']);
        $group2 = new ProductGroupFactory()->createOne(['slug' => 'hosting', 'name' => 'Hosting']);

        $product = new ProductFactory()->createOne([
            'product_group_id' => $group->id,
            'name' => '.com',
            'slug' => 'extension_com',
        ]);
        $product2 = new ProductFactory()->createOne([
            'product_group_id' => $group2->id,
            'name' => 'Zilver',
            'slug' => 'hosting_zilver',
        ]);

        $subscription = new SubscriptionFactory()->withCustomer()->createOne([
            'product_uuid' => $product->uuid,
            'customer_id' => $this->from->id,
        ]);

        $subscription2 = new SubscriptionFactory()->withCustomer()->createOne([
            'product_uuid' => $product2->uuid,
            'customer_id' => $this->from->id,
        ]);

        return new Collection([$subscription, $subscription2]);
    }
}
