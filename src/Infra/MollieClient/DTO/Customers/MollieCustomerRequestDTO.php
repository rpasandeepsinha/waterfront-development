<?php

declare(strict_types=1);

namespace Waterfront\Infra\MollieClient\DTO\Customers;

readonly class MollieCustomerRequestDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public string $locale,
        public ?MollieCustomerMetadataDTO $metadata,
    ) {
    }
}
