<?php

declare(strict_types=1);

namespace Waterfront\Infra\PuzzelClient\DTO;

class VisualQueue
{
    /**
     * @param QueueItem[] $queues
     */
    public function __construct(
        public ?int $id = null,
        public ?string $description = null,
        public array $queues = [],
    ) {
    }
}
