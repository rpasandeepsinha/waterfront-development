<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\DTO;

use Illuminate\Support\Collection;
use Waterfront\Apps\API\Atlantis\Resources\Products\PriceResource;
use Waterfront\Domain\Products\Enums\ProductGroupType;

readonly class Product
{
    /**
     * @param Collection<int,Price> $prices
     */
    public function __construct(
        public string $uuid,
        public ProductGroupType $type,
        public string $name,
        public string $slug,
        public string $description,
        public int $weight,
        public bool $orderable,
        public Collection $prices,
        public SpecificationMap $specifications,
        public ?PriceResource $default_price = null,
    ) {
    }
}
