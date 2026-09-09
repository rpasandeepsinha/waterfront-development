<?php

declare(strict_types=1);

namespace Waterfront\Domain\RetentionToolkit\DTO;

use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\RetentionToolkit\Enums\ExecutionDate;
use Waterfront\Domain\RetentionToolkit\Enums\SelectedAction;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;
use Waterfront\Domain\Subscriptions\Models\Subscription;

readonly class RetentionOfferItemDTO
{
    /**
     * @param positive-int $contractPeriod
     * @param positive-int $billingPeriod
     */
    public function __construct(
        public Subscription $subscription,
        public SelectedAction $selectedAction,
        public ExecutionDate $executionDate,
        public int $contractPeriod,
        public int $billingPeriod,
        public ?Product $targetProduct,
        public ?SubscriptionCancelReason $cancelReason,
        public ?string $cancelReasonOther,
    ) {
    }
}
