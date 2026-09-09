<?php

declare(strict_types=1);

namespace Waterfront\Infra\CloudStackClient\DTO;

use Symfony\Component\Serializer\Attribute\SerializedName;

class NetworkCapability
{
    public function __construct(
        #[SerializedName('canchooseservicecapability')]
        public bool $canChooseServiceCapability,
        public string $name,
        public string $value,
    ) {
    }
}
