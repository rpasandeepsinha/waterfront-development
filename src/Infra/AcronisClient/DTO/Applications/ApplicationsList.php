<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\DTO\Applications;

class ApplicationsList
{
    /**
     * @param array<Application> $items
     */
    public function __construct(
        public array $items,
    ) {
    }
}
