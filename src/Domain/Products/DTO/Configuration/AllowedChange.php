<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\DTO\Configuration;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;

readonly class AllowedChange
{
    public function __construct(
        public string $product_name,
        #[SerializedName('toProductId')]
        public int $toProductId,
        public ProductChangeType $type,
        public int $order,
        #[SerializedName('availableForCustomer')]
        public bool $availabeForCustomer,
    ) {
    }
}
