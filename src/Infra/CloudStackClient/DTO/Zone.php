<?php

declare(strict_types=1);

namespace Waterfront\Infra\CloudStackClient\DTO;

class Zone
{
    public function __construct(
        public string $id,
        public string $name,
        public string $networktype,
        public bool $securitygroupsenabled,
        public string $allocationstate,
        public string $zonetoken,
        public string $dhcpprovider,
        public bool $localstorageenabled,
    ) {
    }
}
