<?php

declare(strict_types=1);

namespace Waterfront\Infra\MollieClient\DTO\Customers;

use Symfony\Component\Serializer\Attribute\SerializedName;

readonly class MollieCustomerMetadataDTO
{
    public function __construct(
        #[SerializedName('debtor_id')]
        public int $debtorId
    ) {
    }
}
