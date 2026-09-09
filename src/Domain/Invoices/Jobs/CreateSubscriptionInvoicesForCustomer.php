<?php

declare(strict_types=1);

namespace Waterfront\Domain\Invoices\Jobs;

use DateTimeInterface;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Invoices\Services\ConsolidatedInvoiceCreator;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class CreateSubscriptionInvoicesForCustomer extends AbstractQueueableJob
{
    /* 15 minutes */
    public int $timeout = 900;

    public function __construct(private readonly Customer $customer, private readonly DateTimeInterface $billingDate)
    {
        parent::__construct();
    }

    public function handle(ConsolidatedInvoiceCreator $service): void
    {
        $service->invoiceCustomer($this->customer, $this->billingDate);
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::SUBSCRIPTIONS;
    }
}
