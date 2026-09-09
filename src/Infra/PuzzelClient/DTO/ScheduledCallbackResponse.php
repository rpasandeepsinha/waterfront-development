<?php

declare(strict_types=1);

namespace Waterfront\Infra\PuzzelClient\DTO;

use Waterfront\Infra\PuzzelClient\Enums\Result;

class ScheduledCallbackResponse
{
    public function __construct(
        public Result $status,
        public ?string $message,
    ) {
    }
}
