<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\DTO\Configuration;

use Symfony\Component\Serializer\Attribute\SerializedName;

readonly class ShopConfig
{
    public function __construct(
        #[SerializedName('orderable')]
        public bool $orderable,
    ) {
    }
}
