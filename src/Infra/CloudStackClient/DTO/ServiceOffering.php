<?php

declare(strict_types=1);

namespace Waterfront\Infra\CloudStackClient\DTO;

class ServiceOffering
{
    public function __construct(
        public string $id,
        public string $name,
        public int $memory,
        public int $cpunumber,
        public string $domainid,
        public string $domain,
        public int $rootdisksize,
    ) {
    }
}
