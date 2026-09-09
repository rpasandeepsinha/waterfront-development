<?php

declare(strict_types=1);

namespace Waterfront\Domain\OneTimeServices\Repositories;

use Illuminate\Support\Collection;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Invoices\DTO\OneTimeServiceContext;
use Waterfront\Domain\OneTimeServices\Models\OneTimeService;

class OneTimeServiceRepository
{
    public function create(OneTimeServiceContext $context, int $grossPrice): OneTimeService
    {
        $oneTimeService = new OneTimeService();
        $oneTimeService->uuid = Uuid::uuid4();
        $oneTimeService->customer_id = $context->subscription->customer->id;
        $oneTimeService->subscription_id = $context->subscription->id;
        $oneTimeService->product_id = $context->product->id;
        $oneTimeService->amount = $context->amount;
        $oneTimeService->discount_percentage = $context->discountPercentage;
        $oneTimeService->gross_price = $grossPrice;
        $oneTimeService->execution_date = $context->executionDate;
        $oneTimeService->status = $context->status;
        $oneTimeService->save();

        return $oneTimeService;
    }

    /**
     * @return Collection<int, OneTimeService>
     */
    public function findAllByCustomerId(int $customerId): Collection
    {
        return OneTimeService::query()->where('customer_id', $customerId)->get();
    }

    public function getBySubscriptionIdAndProductId(int $subscriptionId, int $productId): ?OneTimeService
    {
        return OneTimeService::query()->where('subscription_id', $subscriptionId)->where('product_id', $productId)->first();
    }
}
