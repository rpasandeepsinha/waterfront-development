<?php

declare(strict_types=1);

namespace Tests\Domain\Transfers\Observers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Transfers\Mailer\MailTransferAcceptedReceiver;
use Waterfront\Domain\Transfers\Mailer\MailTransferAcceptedSender;
use Waterfront\Domain\Transfers\Mailer\MailTransferCancelledReceiver;
use Waterfront\Domain\Transfers\Mailer\MailTransferCancelledSender;
use Waterfront\Domain\Transfers\Mailer\MailTransferCompletedReceiver;
use Waterfront\Domain\Transfers\Mailer\MailTransferCompletedSender;
use Waterfront\Domain\Transfers\Mailer\MailTransferFailedReceiver;
use Waterfront\Domain\Transfers\Mailer\MailTransferFailedSender;
use Waterfront\Domain\Transfers\Mailer\MailTransferRejectedReceiver;
use Waterfront\Domain\Transfers\Mailer\MailTransferRejectedSender;
use Waterfront\Domain\Transfers\Mailer\MailTransferStartedReceiver;
use Waterfront\Domain\Transfers\Mailer\MailTransferStartedSender;
use Waterfront\Domain\Transfers\Models\Transfer;
use Waterfront\Domain\Transfers\Observers\TransferObserver;

#[CoversClass(TransferObserver::class)]
class TransferObserverTest extends IntegrationTestCase
{
    private Customer $customer1;

    private Customer $customer2;

    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer1 = new CustomerFactory()->createOne();
        $this->customer2 = new CustomerFactory()->createOne();

        $group = new ProductGroupFactory()->createOne(['slug' => 'extension', 'name' => 'Extension']);
        $product = new ProductFactory()->createOne([
            'product_group_id' => $group->id,
            'name' => '.com',
            'slug' => 'extension_com',
        ]);
        $this->subscription = new SubscriptionFactory()
            ->withCustomer()
            ->createOne([
                'product_uuid' => $product->uuid,
                'customer_id' => $this->customer1->id,
            ]);
    }

    #[Test]
    public function acceptedTransferObserver(): void
    {
        self::assertEmailsSend([
            MailTransferAcceptedSender::class,
            MailTransferAcceptedReceiver::class,
        ]);

        $transfer = Transfer::create([
            'from_customer_id' => $this->customer1->id,
            'to_customer_id' => $this->customer2->id,
        ]);

        self::assertTrue($transfer->accept());
    }

    #[Test]
    public function startedTransferObserver(): void
    {
        self::assertEmailsSend([
            MailTransferAcceptedSender::class,
            MailTransferAcceptedReceiver::class,
            MailTransferStartedSender::class,
            MailTransferStartedReceiver::class,
        ]);
        $transfer = Transfer::create([
            'from_customer_id' => $this->customer1->id,
            'to_customer_id' => $this->customer2->id,
        ]);

        self::assertTrue($transfer->accept(), "The transfer was not accepted! ID: {$transfer->id}");
        self::assertTrue($transfer->start(), "The transfer was not started! ID: {$transfer->id}");
    }

    #[Test]
    public function canceledTransferObserver(): void
    {
        self::assertEmailsSend([
            MailTransferCancelledSender::class,
            MailTransferCancelledReceiver::class,
        ]);
        $transfer = Transfer::create([
            'from_customer_id' => $this->customer1->id,
            'to_customer_id' => $this->customer2->id,
        ]);

        self::assertTrue($transfer->cancel());
    }

    #[Test]
    public function completedTransferObserver(): void
    {
        self::assertEmailsSend([
            MailTransferAcceptedSender::class,
            MailTransferAcceptedReceiver::class,
            MailTransferStartedSender::class,
            MailTransferStartedReceiver::class,
            MailTransferCompletedSender::class,
            MailTransferCompletedReceiver::class,
        ]);
        $transfer = Transfer::create([
            'from_customer_id' => $this->customer1->id,
            'to_customer_id' => $this->customer2->id,
        ]);

        self::assertTrue($transfer->accept());
        self::assertTrue($transfer->start());
        self::assertTrue($transfer->complete());
    }

    #[Test]
    public function rejectedTransferObserver(): void
    {
        self::assertEmailsSend([
            MailTransferRejectedSender::class,
            MailTransferRejectedReceiver::class,
        ]);
        $transfer = Transfer::create([
            'from_customer_id' => $this->customer1->id,
            'to_customer_id' => $this->customer2->id,
        ]);

        self::assertTrue($transfer->reject());
    }

    #[Test]
    public function failedTransferObserver(): void
    {
        self::assertEmailsSend([
            MailTransferAcceptedSender::class,
            MailTransferAcceptedReceiver::class,
            MailTransferStartedSender::class,
            MailTransferStartedReceiver::class,
            MailTransferFailedSender::class,
            MailTransferFailedReceiver::class,
        ]);
        $transfer = Transfer::create([
            'from_customer_id' => $this->customer1->id,
            'to_customer_id' => $this->customer2->id,
        ]);

        self::assertTrue($transfer->accept());
        self::assertTrue($transfer->start());

        DB::table('subscription_transfer')->insert([
            'transfer_id' => $transfer->id,
            'subscription_id' => $this->subscription->id,
            'failed_at' => CarbonImmutable::now(),
        ]);

        self::assertTrue($transfer->complete());
    }
}
