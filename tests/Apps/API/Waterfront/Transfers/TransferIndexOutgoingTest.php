<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Transfers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Testing\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\TransferFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\TransferController;
use Waterfront\Apps\API\Waterfront\Resources\ProductTransferPresenter;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Transfers\Enums\TransferStatus;
use Waterfront\Domain\Transfers\Mailer\MailTransferAcceptedReceiver;
use Waterfront\Domain\Transfers\Mailer\MailTransferAcceptedSender;
use Waterfront\Domain\Transfers\Mailer\MailTransferCompletedReceiver;
use Waterfront\Domain\Transfers\Mailer\MailTransferCompletedSender;
use Waterfront\Domain\Transfers\Mailer\MailTransferStartedReceiver;
use Waterfront\Domain\Transfers\Mailer\MailTransferStartedSender;
use Waterfront\Domain\Transfers\Models\Transfer;

#[CoversClass(TransferController::class)]
#[CoversClass(ProductTransferPresenter::class)]
class TransferIndexOutgoingTest extends IntegrationTestCase
{
    private Customer $receiver;

    private Customer $fromCustomer;

    /** @var Collection<int, Subscription> */
    private Collection $subscriptions;

    public function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading(false);

        CarbonImmutable::setTestNow();

        $this->receiver = new CustomerFactory()->createOne();
        $this->fromCustomer = new CustomerFactory()->createOne();

        $this->subscriptions = $this->getTestingSubscriptions();
    }

    #[Test]
    public function indexOutgoing(): void
    {
        self::assertEmailsSend([
            MailTransferAcceptedSender::class,
            MailTransferAcceptedReceiver::class,
            MailTransferStartedSender::class,
            MailTransferStartedReceiver::class,
            MailTransferCompletedSender::class,
            MailTransferCompletedReceiver::class,
        ]);

        $subscriptions = $this->subscriptions;
        $executedAt = CarbonImmutable::now()->toDateTimeString();
        [$outgoing1, $outgoing2, $timestamped] = $this->prepareIndexTransfers($subscriptions, $executedAt);

        $response = $this->actingAsCustomer($this->fromCustomer)->getJson(
            $this->generateRoute('partners.transfers.index')
        )->assertOk();

        $transfers = $response->json('data');
        assert(is_array($transfers));

        self::assertCount(3, $transfers, 'A unequal number of transfers returned from the index route test call.');

        Assert::assertArraySubset([
            'from_customer_number' => 2,
            'to_customer_number' => 1,
            'type' => 'OUTGOING',
            'accepted_at' => null,
            'completed_at' => null,
            'canceled_at' => null,
            'rejected_at' => null,
            'started_at' => null,
            'created_at' => $outgoing1->created_at?->toW3cString(),
            'updated_at' => $outgoing1->updated_at?->toW3cString(),
        ], $transfers[0]);

        Assert::assertArraySubset([
            'from_customer_number' => 2,
            'to_customer_number' => 1,
            'type' => 'OUTGOING',
            'accepted_at' => null,
            'completed_at' => null,
            'canceled_at' => null,
            'rejected_at' => null,
            'started_at' => null,
            'created_at' => $outgoing2->created_at?->toW3cString(),
            'updated_at' => $outgoing2->updated_at?->toW3cString(),
        ], $transfers[1]);

        Assert::assertArraySubset([
            'from_customer_number' => 2,
            'to_customer_number' => 1,
            'type' => 'OUTGOING',
            'accepted_at' => $timestamped->accepted_at?->toW3cString(),
            'completed_at' => $timestamped->completed_at?->toW3cString(),
            'canceled_at' => null,
            'rejected_at' => null,
            'started_at' => $timestamped->started_at?->toW3cString(),
            'created_at' => $timestamped->created_at?->toW3cString(),
            'updated_at' => $timestamped->updated_at?->toW3cString(),
        ], $transfers[2]);

        $completedTransfer = Transfer::where('uuid', 'cccc-dddd-eeee-ffff')->first();

        self::assertInstanceOf(Transfer::class, $completedTransfer);
        self::assertInstanceOf(Subscription::class, $completedTransfer->subscriptions->first());
        self::assertSame(
            TransferStatus::COMPLETED,
            $completedTransfer->getStatus(),
            'The status was not completed. as we attempted to configure.'
        );
        self::assertSame(
            $completedTransfer->subscriptions->first()->pivot->executed_at,
            $executedAt,
            'Executed at differs'
        );
    }

    #[Test]
    public function indexOutgoingReverse(): void
    {
        self::assertEmailsSend([
            MailTransferAcceptedSender::class,
            MailTransferAcceptedReceiver::class,
            MailTransferStartedSender::class,
            MailTransferStartedReceiver::class,
            MailTransferCompletedSender::class,
            MailTransferCompletedReceiver::class,
        ]);

        $subscriptions = $this->subscriptions;
        $executedAt = CarbonImmutable::now()->toDateTimeString();
        [$outgoing1, $outgoing2, $timestamped] = $this->prepareIndexTransfers($subscriptions, $executedAt);

        $response = $this->actingAsCustomer($this->receiver)->getJson(
            $this->generateRoute('partners.transfers.index')
        )->assertOk();

        $transfers = $response->json('data');
        assert(is_array($transfers));

        self::assertCount(3, $transfers, 'A unequal number of transfers returned from the index route test call.');

        Assert::assertArraySubset([
                'from_customer_number' => 2,
                'to_customer_number' => 1,
                'type' => 'INCOMING',
                'accepted_at' => null,
                'completed_at' => null,
                'canceled_at' => null,
                'rejected_at' => null,
                'started_at' => null,
                'created_at' => $outgoing1->created_at?->toW3cString(),
                'updated_at' => $outgoing1->updated_at?->toW3cString(),
            ], $transfers[0]);

        Assert::assertArraySubset([
                'from_customer_number' => 2,
                'to_customer_number' => 1,
                'type' => 'INCOMING',
                'accepted_at' => null,
                'completed_at' => null,
                'canceled_at' => null,
                'rejected_at' => null,
                'started_at' => null,
                'created_at' => $outgoing2->created_at?->toW3cString(),
                'updated_at' => $outgoing2->updated_at?->toW3cString(),
            ], $transfers[1]);

        Assert::assertArraySubset([
                'from_customer_number' => 2,
                'to_customer_number' => 1,
                'type' => 'INCOMING',
                'accepted_at' => $timestamped->accepted_at?->toW3cString(),
                'completed_at' => $timestamped->completed_at?->toW3cString(),
                'canceled_at' => null,
                'rejected_at' => null,
                'started_at' => $timestamped->started_at?->toW3cString(),
                'created_at' => $timestamped->created_at?->toW3cString(),
                'updated_at' => $timestamped->updated_at?->toW3cString(),
            ], $transfers[2]);

        $completedTransfer = Transfer::where('uuid', 'cccc-dddd-eeee-ffff')->first();
        self::assertInstanceOf(Transfer::class, $completedTransfer);
        self::assertInstanceOf(Subscription::class, $completedTransfer->subscriptions->first());
        self::assertSame(
            TransferStatus::COMPLETED,
            $completedTransfer->getStatus(),
            'The status was not completed. as we attempted to configure.'
        );
        self::assertSame(
            $completedTransfer->subscriptions->first()->pivot->executed_at,
            $executedAt,
            'Executed at differs'
        );
    }

    #[Test]
    public function indexEmptyResult(): void
    {
        $response = $this->actingAsCustomer($this->fromCustomer)->getJson(
            $this->generateRoute('partners.transfers.index')
        )->assertOk();

        $data = $response->json('data.*');
        assert(is_array($data));

        self::assertCount(0, $data);
    }

    /**
     * @return Collection<int, Subscription>
     */
    private function getTestingSubscriptions(): Collection
    {
        $product = new ProductFactory()->for(new ProductGroupFactory()->extension()->createOne())->createOne();
        $product2 = new ProductFactory()->for(new ProductGroupFactory()->hosting()->createOne())->createOne();
        $product3 = new ProductFactory()->for(new ProductGroupFactory()->ssl()->createOne())->createOne();

        new ProductPriceComponentFactory()->for($product)->prolongation()->createOne(['billing_period' => 12, 'contract_period' => 12]);
        new ProductPriceComponentFactory()->for($product2)->prolongation()->createOne(['billing_period' => 12, 'contract_period' => 12]);
        new ProductPriceComponentFactory()->for($product3)->prolongation()->createOne(['billing_period' => 12, 'contract_period' => 12]);

        $subscription = new SubscriptionFactory()->for($product)->for($this->fromCustomer)->createOne();
        $subscription2 = new SubscriptionFactory()->for($product2)->for($this->fromCustomer)->createOne();
        $subscription3 = new SubscriptionFactory()->for($product3)->for($this->fromCustomer)->createOne();

        return new Collection([$subscription, $subscription2, $subscription3]);
    }

    /**
     * @param Collection<int, Subscription> $subscriptions
     *
     * @return Transfer[]
     */
    private function prepareIndexTransfers(Collection $subscriptions, string $executedAt): array
    {
        self::assertInstanceOf(Subscription::class, $subscriptions[0]);
        $outgoing1 = new TransferFactory()->createOne([
            'from_customer_id' => $this->fromCustomer->id,
            'to_customer_id' => $this->receiver->id,
            'uuid'           => 'aaaa-bbbb-cccc-dddd',
        ]);
        $outgoing1->subscriptions()->save($subscriptions[0]);

        self::assertInstanceOf(Subscription::class, $subscriptions[1]);
        $outgoing2 = new TransferFactory()->createOne([
            'from_customer_id' => $this->fromCustomer->id,
            'to_customer_id' => $this->receiver->id,
            'uuid'           => 'bbbb-cccc-dddd-eeee',
        ]);
        $outgoing2->subscriptions()->save($subscriptions[1]);

        self::assertInstanceOf(Subscription::class, $subscriptions[2]);
        $timestamped = new TransferFactory()->createOne([
            'from_customer_id' => $this->fromCustomer->id,
            'to_customer_id' => $this->receiver->id,
            'uuid'           => 'cccc-dddd-eeee-ffff',
        ]);

        $timestamped->subscriptions()->save($subscriptions[2], ['executed_at' => $executedAt]);
        $timestamped->accept();
        $timestamped->start();
        $timestamped->complete();

        return [$outgoing1, $outgoing2, $timestamped];
    }
}
