<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\DTO\Configuration;

readonly class Specifications
{
    public function __construct(
        public string $name,
        public string|int|bool $value,
    ) {
    }
}
