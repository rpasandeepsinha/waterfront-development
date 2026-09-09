<?php

declare(strict_types=1);

namespace Waterfront\Infra\MollieClient\DTO\Customers;

use DateTimeImmutable;

readonly class MollieCustomerResponseDTO
{
    public function __construct(
        public string $id,
        public string $mode,
        public string $name,
        public string $email,
        public string $locale,
        public MollieCustomerMetadataDTO|null $metadata,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
