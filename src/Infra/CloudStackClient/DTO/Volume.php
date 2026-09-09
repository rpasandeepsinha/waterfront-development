<?php

declare(strict_types=1);

namespace Waterfront\Infra\CloudStackClient\DTO;

class Volume
{
    public function __construct(
        public string $id,
        public string $name,
        public string $domainId,
        public string $account,
        public string $type,
        public string $state,
        public ?string $virtualMachineId = null,
        public ?string $diskOfferingId = null,
    ) {
    }
}
