<?php

declare(strict_types=1);

namespace Waterfront\Infra\CloudStackClient\DTO;

class Domain
{
    public function __construct(
        public string $id,
        public string $name,
        public string $parentDomainId
    ) {
    }
}
