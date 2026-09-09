<?php

declare(strict_types=1);

namespace Waterfront\Infra\SaloonClient\Config;

use Webmozart\Assert\Assert;

readonly class RetryConfig
{
    public function __construct(
        public ?int $tries = 3,
        public ?int $intervalMs = 500,
        public ?bool $useExponentialBackoff = true,
    ) {
        if ($this->tries !== null) {
            Assert::greaterThanEq($this->tries, 1, 'retry tries must be at least 1');
        }

        if ($this->intervalMs !== null) {
            Assert::greaterThanEq($this->intervalMs, 0, 'retry interval must be zero or greater');
        }
    }
}
