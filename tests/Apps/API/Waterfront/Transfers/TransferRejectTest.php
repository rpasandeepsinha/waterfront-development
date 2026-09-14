<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Transfers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\TransferFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\TransferController;
use Waterfront\Apps\API\Waterfront\Resources\ProductTransferPresenter;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Transfers\Enums\TransferStatus;
use Waterfront\Domain\Transfers\Mailer\MailTransferAcceptedReceiver;
use Waterfront\Domain\Transfers\Mailer\MailTransferAcceptedSender;
use Waterfront\Domain\Transfers\Mailer\MailTransferRejectedReceiver;
use Waterfront\Domain\Transfers\Mailer\MailTransferRejectedSender;
use Waterfront\Domain\Transfers\Models\Transfer;

#[CoversClass(TransferController::class)]
#[CoversClass(ProductTransferPresenter::class)]
class TransferRejectTest extends IntegrationTestCase
{
    private Customer $fromCustomer;

    private Transfer $transfer;

    public function setUp(): void
    {
        parent::setUp();

        $toCustomer = new CustomerFactory()->createOne();
        $this->fromCustomer = new CustomerFactory()->createOne();

        $product = new ProductFactory()->for(new ProductGroupFactory()->extension()->createOne())->createOne();
        $subscription = new SubscriptionFactory()
            ->for($this->fromCustomer)
            ->for($product)
            ->createOne();

        $this->transfer = new TransferFactory()->createOne([
            'from_customer_id' => $this->fromCustomer->id,
            'to_customer_id' => $toCustomer->id,
        ]);
        $this->transfer->subscriptions()->save($subscription);
    }

    #[Test]
    public function reject(): void
    {
        self::assertEmailsSend([
            MailTransferRejectedSender::class,
            MailTransferRejectedReceiver::class,
        ]);

        $this->actingAsCustomer($this->fromCustomer)
            ->postJson(
                $this->generateRoute('partners.transfers.reject', ['transfer' => $this->transfer->uuid]),
            )
            ->assertOk();

        $transfer = $this->transfer->refresh();

        self::assertNotNull($transfer['rejected_at']);
        self::assertSame(TransferStatus::REJECTED, $transfer->getStatus());
    }

    #[Test]
    public function rejectFailsOnAccepted(): void
    {
        self::assertEmailsSend([
            MailTransferAcceptedSender::class,
            MailTransferAcceptedReceiver::class,
        ]);

        $this->transfer->accept();

        $this->actingAsCustomer($this->fromCustomer)
            ->postJson(
                $this->generateRoute('partners.transfers.reject', ['transfer' => $this->transfer->uuid]),
            )
            ->assertUnprocessable();

        $transfer = $this->transfer->refresh();

        self::assertSame(TransferStatus::ACCEPTED, $transfer->getStatus());
    }
}
