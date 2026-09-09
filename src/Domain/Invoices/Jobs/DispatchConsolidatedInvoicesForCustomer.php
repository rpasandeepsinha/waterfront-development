<?php

declare(strict_types=1);

namespace Waterfront\Domain\Invoices\Jobs;

use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Harbor\Exceptions\InvoiceLineToHarborException;
use Waterfront\Domain\Harbor\Services\Message\MessageService;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Infra\Queue\Exceptions\HarborClientException;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class DispatchConsolidatedInvoicesForCustomer extends AbstractQueueableJob
{
    /* 15 minutes */
    public int $timeout = 900;

    public int $tries = 8;

    /** @var array<int> */
    public array $backoff = [60, 2 * 60, 10 * 60, 30 * 60, 60 * 60, 5 * 60 * 60];

    /**
     * @param array<int,Invoice> $invoices
     */
    public function __construct(
        private readonly Customer $customer,
        private readonly array $invoices,
        private readonly bool $createInvoiceInstantly = false,
    ) {
        parent::__construct();
    }

    /**
     * @throws InvoiceLineToHarborException
     * @throws HarborClientException
     */
    public function handle(MessageService $service): void
    {
        $service->queue($this->customer, $this->invoices, createInvoiceInstantly: $this->createInvoiceInstantly);
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::SUBSCRIPTIONS;
    }
}
