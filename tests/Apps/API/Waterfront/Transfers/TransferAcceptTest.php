<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Transfers;

use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\TransferFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\TransferController;
use Waterfront\Apps\API\Waterfront\Resources\ProductTransferPresenter;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Subscriptions\Jobs\TransferSubscriptions;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Transfers\Mailer\MailTransferAcceptedReceiver;
use Waterfront\Domain\Transfers\Mailer\MailTransferAcceptedSender;
use Waterfront\Domain\Transfers\Models\Transfer;

#[CoversClass(TransferController::class)]
#[CoversClass(ProductTransferPresenter::class)]
class TransferAcceptTest extends IntegrationTestCase
{
    private Subscription $subscription;

    private Customer $randomCustomer;

    private Customer $receiverOwner;

    private Customer $fromOwner;

    public function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->receiverOwner = new CustomerFactory()->createOne();
        $this->fromOwner = new CustomerFactory()->createOne();

        $product = new ProductFactory()->for(new ProductGroupFactory()->extension()->createOne())->createOne();

        $this->subscription = new SubscriptionFactory()
            ->for($product)
            ->for($this->fromOwner)
            ->createOne();
        $this->randomCustomer = new CustomerFactory()->createOne();
    }

    #[Test]
    public function acceptTransferSuccessful(): void
    {
        self::assertEmailsSend([
            MailTransferAcceptedSender::class,
            MailTransferAcceptedReceiver::class,
        ]);

        $transfer = $this->populateCoupledTransfer();

        $this->actingAsCustomer($this->receiverOwner)
            ->postJson(
                $this->generateRoute('partners.transfers.accept', ['transfer' => $transfer->uuid]),
            )
            ->assertOk();

        $transfer->refresh();
        self::assertNotNull($transfer->accepted_at, 'Transfer is accepted so accepted_at should be set.');
        Queue::assertPushed(TransferSubscriptions::class);
    }

    #[Test]
    public function transferAlreadyAccepted(): void
    {
        self::assertEmailsSend([
            MailTransferAcceptedSender::class,
            MailTransferAcceptedReceiver::class,
        ]);

        $transfer = $this->populateCoupledTransfer();
        $transfer->accept();

        $response = $this->actingAsCustomer($this->receiverOwner)
            ->postJson(
                $this->generateRoute('partners.transfers.accept', ['transfer' => $transfer->uuid]),
            )
            ->assertForbidden();

        self::assertSame(
            'This action is unauthorized.',
            $response->json('message'),
            'Transfer was already accept and thus request could not be done',
        );
        self::assertNotNull($transfer->accepted_at, 'Transfer is accepted so accepted_at should be set.');
    }

    #[Test]
    public function transferAcceptNotExists(): void
    {
        $randomUUid = Uuid::uuid4();

        $this->actingAsCustomer($this->fromOwner)
            ->postJson(
                $this->generateRoute('partners.transfers.accept', ['transfer' => $randomUUid]),
            )
            ->assertNotFound();
    }

    #[Test]
    public function transferIsNotFromReceiver(): void
    {
        $transfer = $this->populateCoupledTransfer();
        $transfer->to_customer_id = $this->randomCustomer->id;
        $transfer->save();

        $this->actingAsCustomer($this->fromOwner)
            ->postJson(
                $this->generateRoute('partners.transfers.accept', ['transfer' => $transfer->uuid]),
            )
            ->assertForbidden();
    }

    private function populateCoupledTransfer(): Transfer
    {
        $transfer = new TransferFactory()->createOne([
            'from_customer_id' => $this->fromOwner->id,
            'to_customer_id' => $this->receiverOwner->id,
        ]);

        $this->subscription->transfers()->save($transfer);

        return $transfer;
    }
}
