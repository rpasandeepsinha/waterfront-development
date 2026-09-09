<?php

declare(strict_types=1);

namespace Waterfront\Domain\RetentionToolkit\DTO;

use Carbon\CarbonImmutable;
use Waterfront\Domain\RetentionToolkit\Enums\RetentionOfferCalculationStatus;
use Waterfront\Domain\RetentionToolkit\Enums\SelectedAction;
use Waterfront\Domain\Subscriptions\Models\Subscription;

readonly class RetentionOfferItemCalculationDTO
{
    public function __construct(
        public Subscription $subscription,
        public SelectedAction $selectedAction,
        public RetentionOfferCalculationStatus $status,
        public ?string $reason,
        public ?RetentionOfferPriceDTO $price,
        public ?CarbonImmutable $effectiveDate,
        public ?CarbonImmutable $oldContractStartDate,
        public ?CarbonImmutable $oldContractEndDate,
        public ?CarbonImmutable $newContractStartDate,
        public ?CarbonImmutable $newContractEndDate,
        public ?CarbonImmutable $cancellationDate,
        public ?int $creditTotal,
        public ?int $payableAfterCredits,
        public bool $requiresNewInvoice,
        public bool $replacesFutureInvoice,
    ) {
    }
}
