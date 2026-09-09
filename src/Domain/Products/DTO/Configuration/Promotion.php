<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\DTO\Configuration;

use Carbon\CarbonImmutable;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Waterfront\Domain\Products\Enums\ProductPromotionPlatform;

readonly class Promotion
{
    public function __construct(
        public UuidInterface|null $uuid,
        public ProductPromotionPlatform $platform,
        #[SerializedName('placement_url')]
        public string $placementUrl,
        #[SerializedName('start_date')]
        public CarbonImmutable $startDate,
        #[SerializedName('end_date')]
        public CarbonImmutable $endDate,
        #[SerializedName('callToAction')]
        public PromotionCallToAction $callToAction,
        public int $weight,
    ) {
    }
}
