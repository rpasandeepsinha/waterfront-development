<?php

declare(strict_types=1);

namespace Waterfront\Domain\Payt\Services;

use Illuminate\Contracts\Bus\Dispatcher;
use InvalidArgumentException;
use ValueError;
use Waterfront\Apps\Webhooks\DTO\Payt\PaytCreditCase;
use Waterfront\Apps\Webhooks\DTO\Payt\PaytDebtor;
use Waterfront\Apps\Webhooks\DTO\Payt\PaytInvoice;
use Waterfront\Apps\Webhooks\DTO\Payt\PaytInvoiceCommentEvent;
use Waterfront\Apps\Webhooks\DTO\Payt\PaytWebhookEvent;
use Waterfront\Apps\Webhooks\DTO\Payt\PaytWebhookPayload;
use Waterfront\Domain\Payt\Jobs\HandleCaseNewCommentJob;
use Waterfront\Domain\Payt\Jobs\HandleDebtorNewCommentJob;
use Waterfront\Domain\Payt\Jobs\HandleInvoiceNewCommentJob;
use Waterfront\Infra\PaytClient\Enums\PaytSupportedBusinessUnit;
use Webmozart\Assert\Assert;

readonly class PaytWebhookEventHandler
{
    public function __construct(private Dispatcher $dispatcher)
    {
    }

    public function handleEvent(PaytWebhookPayload $payload, string $businessUnit): void
    {
        match ($payload->event->eventName) {
            PaytWebhookEvent::INVOICE_NEW_COMMENT,
            PaytWebhookEvent::CASE_NEW_COMMENT,
            PaytWebhookEvent::DEBTOR_NEW_COMMENT => $this->handleNewComment($payload, $businessUnit),
            default => new InvalidArgumentException(sprintf('Event: %s not supported', $payload->event->eventName))
        };
    }

    private function handleNewComment(PaytWebhookPayload $payload, string $businessUnit): void
    {
        try {
            $supportedBusinessUnit = PaytSupportedBusinessUnit::from($businessUnit);
        } catch (ValueError) {
            throw new InvalidArgumentException('Business unit not supported');
        }

        Assert::isInstanceOf($payload->event, PaytInvoiceCommentEvent::class);
        $paytWebhookContext = $payload->event->context;

        if ($paytWebhookContext instanceof PaytInvoice) {
            $this->dispatcher->dispatch(new HandleInvoiceNewCommentJob(
                invoice: $paytWebhookContext,
                businessUnit: $supportedBusinessUnit,
            ));
        } elseif ($paytWebhookContext instanceof PaytCreditCase) {
            $this->dispatcher->dispatch(new HandleCaseNewCommentJob(
                creditCase: $paytWebhookContext,
                businessUnit: $supportedBusinessUnit,
            ));
        } elseif ($paytWebhookContext instanceof PaytDebtor) {
            $this->dispatcher->dispatch(new HandleDebtorNewCommentJob(
                debtor: $paytWebhookContext,
                businessUnit: $supportedBusinessUnit,
            ));
        }
    }
}
