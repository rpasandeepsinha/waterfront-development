<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\DTO\Applications;

class Application
{
    /**
     * @param string[] $usages
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $type,
        public array $usages,
        public string $apiBaseUrl,
    ) {
    }
}
