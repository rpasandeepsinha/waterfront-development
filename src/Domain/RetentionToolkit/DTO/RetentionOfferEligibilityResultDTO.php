<?php

declare(strict_types=1);

namespace Waterfront\Domain\RetentionToolkit\DTO;

use Waterfront\Domain\RetentionToolkit\Enums\RetentionOfferEligibilityCode;

readonly class RetentionOfferEligibilityResultDTO
{
    public function __construct(
        public RetentionOfferEligibilityCode $code,
        public ?string $reason,
    ) {
    }
}
