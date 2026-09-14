<?php

declare(strict_types=1);

namespace Waterfront\Infra\PuzzelClient\DTO;

class AccessPoint
{
    public function __construct(
        public string $number,
        public string $countryCode,
    ) {
    }
}
