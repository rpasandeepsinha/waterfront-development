<?php

declare(strict_types=1);

namespace Waterfront\Infra\PuzzelClient\DTO;

use Carbon\CarbonImmutable;
use Propaganistas\LaravelPhone\PhoneNumber;

class Callback
{
    public function __construct(
        public readonly string $description,
        public readonly string $category,
        public readonly PhoneNumber $phoneNumber,
        public readonly CarbonImmutable $scheduledDateTime,
    ) {
    }
}
