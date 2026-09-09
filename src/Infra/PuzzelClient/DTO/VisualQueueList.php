<?php

declare(strict_types=1);

namespace Waterfront\Infra\PuzzelClient\DTO;

class VisualQueueList
{
    /**
     * @param VisualQueue[] $result
     */
    public function __construct(
        public array $result = [],
        public ?int $code = null,
        public ?string $id = null,
        public ?string $message = null,
    ) {
    }
}
