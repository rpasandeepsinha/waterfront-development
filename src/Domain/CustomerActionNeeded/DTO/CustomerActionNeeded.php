<?php

declare(strict_types=1);

namespace Waterfront\Domain\CustomerActionNeeded\DTO;

use Waterfront\Domain\CustomerActionNeeded\Enums\CustomerActionSlug;
use Waterfront\Domain\Products\Enums\ProductGroupType;

readonly class CustomerActionNeeded
{
    public function __construct(
        public string $title,
        public string $message,
        public CustomerActionSlug $slug,
        public ?ProductGroupType $productGroupSlug = null,
        public ?string $productSlug = null,
    ) {
    }
}
