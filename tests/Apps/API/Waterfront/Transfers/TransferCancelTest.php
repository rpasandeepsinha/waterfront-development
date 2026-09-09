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
use Waterfront\Domain\Mailer\Mailer;
use Waterfront\Domain\Transfers\Enums\TransferStatus;
use Waterfront\Domain\Transfers\Mailer\MailTransferCancelledReceiver;
use Waterfront\Domain\Transfers\Mailer\MailTransferCancelledSender;
use Waterfront\Domain\Transfers\Models\Transfer;

#[CoversClass(TransferController::class)]
#[CoversClass(ProductTransferPresenter::class)]
class TransferCancelTest extends IntegrationTestCase
{
    private Customer $fromCustomer;

    private Transfer $transfer;

    public function setUp(): void
    {
        parent::setUp();

        $this->fromCustomer = new CustomerFactory()->createOne();
        $receiver = new CustomerFactory()->createOne();

        $product = new ProductFactory()->for(new ProductGroupFactory()->extension()->createOne())->createOne();

        $subscription = new SubscriptionFactory()->for($product)->for($this->fromCustomer)->createOne();
        $this->transfer = new TransferFactory()->createOne([
            'from_customer_id' => $this->fromCustomer->id,
            'to_customer_id' => $receiver->id,
        ]);
        $this->transfer->subscriptions()->save($subscription);
    }

    #[Test]
    public function cancel(): void
    {
        self::assertEmailsSend([
            MailTransferCancelledSender::class,
            MailTransferCancelledReceiver::class,
        ]);

        $this->actingAsCustomer($this->fromCustomer)->postJson(
            $this->generateRoute('partners.transfers.cancel', ['transfer' => $this->transfer->uuid])
        )->assertOk();

        $transfer = $this->transfer->refresh();

        self::assertNotNull($transfer['canceled_at']);
        self::assertSame(TransferStatus::CANCELED, $transfer->getStatus());
    }

    #[Test]
    public function cancelFailsOnCompleted(): void
    {
        $this->transfer->accept();
        $this->transfer->start();
        $this->transfer->complete();

        $mailer = self::createMock(Mailer::class);
        $mailer->expects(self::never())
            ->method('send');
        $this->app->bind(Mailer::class, fn () => $mailer);

        $this->actingAsCustomer($this->fromCustomer)->postJson(
            $this->generateRoute('partners.transfers.cancel', ['transfer' => $this->transfer->uuid])
        )->assertUnprocessable();

        $this->transfer->refresh();

        self::assertSame(TransferStatus::COMPLETED, $this->transfer->getStatus());
    }
}
