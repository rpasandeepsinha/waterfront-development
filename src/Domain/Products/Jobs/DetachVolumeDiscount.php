<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Jobs;

use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\VolumeDiscountService;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class DetachVolumeDiscount extends AbstractQueueableJob
{
    public function __construct(private readonly Customer $customer, private readonly Product $product)
    {
        parent::__construct();
    }

    public function handle(VolumeDiscountService $service): void
    {
        $service->detach($this->customer, $this->product);
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::SUBSCRIPTIONS;
    }
}
