<?php

declare(strict_types=1);

namespace Tests\Domain\Payt\Services;

use Illuminate\Support\Facades\Bus;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Waterfront\Apps\Webhooks\DTO\Payt\PaytCreditCase;
use Waterfront\Apps\Webhooks\DTO\Payt\PaytDebtor;
use Waterfront\Apps\Webhooks\DTO\Payt\PaytInvoice;
use Waterfront\Apps\Webhooks\DTO\Payt\PaytInvoiceCommentEvent;
use Waterfront\Apps\Webhooks\DTO\Payt\PaytWebhookEvent;
use Waterfront\Apps\Webhooks\DTO\Payt\PaytWebhookPayload;
use Waterfront\Domain\Payt\Jobs\HandleCaseNewCommentJob;
use Waterfront\Domain\Payt\Jobs\HandleDebtorNewCommentJob;
use Waterfront\Domain\Payt\Jobs\HandleInvoiceNewCommentJob;
use Waterfront\Domain\Payt\Services\PaytWebhookEventHandler;

#[CoversClass(PaytWebhookEventHandler::class)]
class PaytWebhookEventHandlerTest extends TestCase
{
    private PaytWebhookEventHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();

        $this->handler = $this->app->make(PaytWebhookEventHandler::class);
    }

    #[Test]
    public function handlesInvoiceNewCommentDispatchesJob(): void
    {
        $this->handler->handleEvent($this->makeInvoiceCommentPayload(), 'versio-2');

        Bus::assertDispatched(HandleInvoiceNewCommentJob::class);
    }

    #[Test]
    public function handlesCaseNewCommentDispatchesJob(): void
    {
        $this->handler->handleEvent($this->makeCreditCommentPayload(), 'versio-2');

        Bus::assertDispatched(HandleCaseNewCommentJob::class);
    }

    #[Test]
    public function handlesDebtorCommentDispatchesJob(): void
    {
        $this->handler->handleEvent($this->makeDebtorPayload(), 'versio-2');

        Bus::assertDispatched(HandleDebtorNewCommentJob::class);
    }

    #[Test]
    public function handleEventThrowsForUnsupportedBusinessUnit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Business unit not supported');

        $this->handler->handleEvent($this->makeInvoiceCommentPayload(), 'unknown-bu');
    }

    #[Test]
    public function handleEventIgnoresUnknownEventName(): void
    {
        $this->handler->handleEvent(
            new PaytWebhookPayload(
                event: new PaytInvoiceCommentEvent(
                    eventName: 'some_unknown_event',
                    eventTime: '2018-06-05T10:00:11.314159Z',
                    context: new PaytInvoice(
                        resourceType: 'invoice',
                        id: 1,
                        invoiceNumber: null,
                        invoiceDate: null,
                        dueDate: null,
                        amountTotal: null,
                        amountOpen: null,
                        currencyCode: null,
                        orderNumber: null,
                        debtor: null,
                        administration: null,
                    ),
                ),
            ),
            'versio-2',
        );

        Bus::assertNothingDispatched();
    }

    private function makeInvoiceCommentPayload(): PaytWebhookPayload
    {
        $invoice = new PaytInvoice(
            resourceType: 'invoice',
            id: 1,
            invoiceNumber: null,
            invoiceDate: null,
            dueDate: null,
            amountTotal: null,
            amountOpen: null,
            currencyCode: null,
            orderNumber: null,
            debtor: null,
            administration: null,
        );

        return new PaytWebhookPayload(
            event: new PaytInvoiceCommentEvent(
                eventName: 'invoice_new_comment',
                eventTime: '2018-06-05T10:00:11.314159Z',
                context: $invoice,
            ),
        );
    }

    private function makeCreditCommentPayload(): PaytWebhookPayload
    {
        $case = new PaytCreditCase(
            id: 2,
            resourceType: 'credit_case',
            creditCaseNumber: '123 456 789',
            interest: null,
            collectionCosts: null,
            openInterestAndCollectionCosts: null,
            link: null,
            publicLink: null,
            invoices: [],
            debtor: null,
            administration: null,
        );

        return new PaytWebhookPayload(
            event: new PaytInvoiceCommentEvent(
                eventName: PaytWebhookEvent::CASE_NEW_COMMENT,
                eventTime: '2018-06-05T10:00:11.314159Z',
                context: $case,
            ),
        );
    }

    private function makeDebtorPayload(): PaytWebhookPayload
    {
        $paytDebtor = new PaytDebtor(
            resourceType: null,
            id: 2002,
            companyName: null,
            name: null,
            debtorCode: '3001244',
            administration: null,
        );

        return new PaytWebhookPayload(
            event: new PaytInvoiceCommentEvent(
                eventName: PaytWebhookEvent::DEBTOR_NEW_COMMENT,
                eventTime: '2018-06-05T10:00:11.314159Z',
                context: $paytDebtor,
            ),
        );
    }
}
