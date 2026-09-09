<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Jobs;

use Waterfront\Domain\Invoices\Repositories\InvoiceRepository;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\SubscriptionService;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class CreateTrusteeSubscriptionJob extends AbstractQueueableJob
{
    public function __construct(private readonly Subscription $subscription, private readonly Product $product)
    {
        parent::__construct();
    }

    public function handle(SubscriptionService $subscriptionService, InvoiceRepository $invoiceRepository): void
    {
        $childSubscription = $subscriptionService->createChildSubscription($this->subscription, $this->product);
        $invoiceRepository->create($childSubscription);
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::SUBSCRIPTIONS;
    }
}
