<?php

declare(strict_types=1);

namespace Waterfront\Infra\PuzzelClient\DTO;

class QueueList
{
    /**
     * @param array<int, Queue>|null $result
     */
    public function __construct(
        public ?array $result = [],
        public ?int $code = null,
        public ?string $id = null,
        public ?string $message = null,
    ) {
    }
}
