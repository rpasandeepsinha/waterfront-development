<?php

declare(strict_types=1);

namespace Waterfront\Domain\Harbor\DTO\Message;

use Waterfront\Domain\Harbor\Services\Message\MessageService;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Models\Subscription;

/**
 * Used by the MessageBuilder service.
 *
 * Used for configuring the parameters of each individual Invoice when building a message,
 * where each config instance represents the parameters for each InvoiceLine that is to be created
 * using the MessageBuilder.
 *
 * @see MessageService
 */
class InvoiceLineMessageConfig
{
    public function __construct(
        private readonly Invoice $invoice,
        private readonly Product $product,
        private readonly ?Subscription $subscription = null,
        private readonly ?int $creditedInvoiceId = null,
        private readonly ?string $prepaidReference = null,
    ) {
    }

    public function getInvoice(): Invoice
    {
        return $this->invoice;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getSubscription(): ?Subscription
    {
        return $this->subscription;
    }

    public function getCreditedInvoiceId(): ?int
    {
        return $this->creditedInvoiceId;
    }

    public function getPrepaidReference(): ?string
    {
        return $this->prepaidReference;
    }
}
