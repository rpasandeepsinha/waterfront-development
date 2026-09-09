<?php

declare(strict_types=1);

namespace Waterfront\Infra\CloudStackClient\DTO;

class Tag
{
    public function __construct(
        public string $key,
        public string $value,
    ) {
    }
}
