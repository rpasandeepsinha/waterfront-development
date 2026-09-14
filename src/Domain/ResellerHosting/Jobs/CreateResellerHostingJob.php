<?php

declare(strict_types=1);

namespace Waterfront\Domain\ResellerHosting\Jobs;

use Psr\Log\LoggerInterface;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\ResellerHosting\Services\ResellerHostingService;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class CreateResellerHostingJob extends AbstractQueueableJob
{
    public function __construct(
        public readonly string $subscriptionUuid,
        public readonly ?string $technicalStatus,
        public readonly string $contactPersonName,
        public readonly string $contactEmail,
        public readonly ?int $serverId,
        public readonly Customer $customer,
        public readonly Product $product,
    ) {
        parent::__construct();
    }

    public function handle(ResellerHostingService $resellerHostingService, LoggerInterface $logger): void
    {
        $logger->debug(
            sprintf(
                'Subscription with UUID: {%s} for reseller hosting is triggered.',
                $this->subscriptionUuid,
            ),
        );

        $resellerHostingService->create(
            $this->subscriptionUuid,
            $this->contactPersonName,
            $this->contactEmail,
            $this->serverId,
            $this->product,
            $this->customer,
        );
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::HOSTING;
    }
}
