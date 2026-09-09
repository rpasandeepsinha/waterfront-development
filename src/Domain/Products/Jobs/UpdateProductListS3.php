<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Jobs;

use Waterfront\Domain\Products\ProductListUpdater;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class UpdateProductListS3 extends AbstractQueueableJob
{
    public function handle(ProductListUpdater $service): void
    {
        $service->update();
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::DEFAULT;
    }
}
