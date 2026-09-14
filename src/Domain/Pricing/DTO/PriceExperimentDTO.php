<?php

declare(strict_types=1);

namespace Waterfront\Domain\Pricing\DTO;

use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Experiment\Enums\ExperimentType;
use Waterfront\Domain\Products\DTO\Price;

class PriceExperimentDTO
{
    /**
     * @param Price[] $prices
     */
    public function __construct(
        public readonly UuidInterface $productUuid,
        public readonly string $productSlug,
        public readonly ExperimentType $experimentType,
        public readonly array $prices,
    ) {
    }
}
