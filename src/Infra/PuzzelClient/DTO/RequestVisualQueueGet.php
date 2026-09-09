<?php

declare(strict_types=1);

namespace Waterfront\Infra\PuzzelClient\DTO;

class RequestVisualQueueGet
{
    /**
     * @param RequestInQueue[] $result
     */
    public function __construct(
        public readonly ?array $result,
        public readonly ?int $code,
        public readonly ?string $id,
        public readonly ?string $message,
    ) {
    }
}
