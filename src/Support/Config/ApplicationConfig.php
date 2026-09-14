<?php

declare(strict_types=1);

namespace Waterfront\Support\Config;

class ApplicationConfig
{
    public function __construct(
        public readonly int $defaultTaxRate,
    ) {
    }
}
