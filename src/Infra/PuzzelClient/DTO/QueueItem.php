<?php

declare(strict_types=1);

namespace Waterfront\Infra\PuzzelClient\DTO;

class QueueItem
{
    public function __construct(
        public ?int $id = null,
        public ?string $key = null,
        public ?string $description = null,
        public ?int $serviceId = null,
    ) {
    }
}
