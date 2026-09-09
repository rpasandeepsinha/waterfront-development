<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\DTO;

use Carbon\CarbonImmutable;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;

readonly class ProvisionResultDTO
{
    public function __construct(
        public UuidInterface $uuid,
        public ?CarbonImmutable $createdAt,
        public string $response,
        public ProvisionStatus $status,
    ) {
    }
}
