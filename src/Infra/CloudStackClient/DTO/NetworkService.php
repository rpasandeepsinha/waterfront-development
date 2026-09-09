<?php

declare(strict_types=1);

namespace Waterfront\Infra\CloudStackClient\DTO;

class NetworkService
{
    /**
     * @param NetworkCapability[] $capability An array of capabilities for the service.
     * @param NetworkProvider[]   $provider   An array of providers for the service.
     */
    public function __construct(
        public array $capability,
        public string $name,
        public array $provider,
    ) {
    }
}
