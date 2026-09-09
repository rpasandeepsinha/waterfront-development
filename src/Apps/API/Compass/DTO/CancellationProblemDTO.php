<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\DTO;

readonly class CancellationProblemDTO
{
    public function __construct(
        public ?int $subscriptionId,
        public string $message,
    ) {
    }
}
