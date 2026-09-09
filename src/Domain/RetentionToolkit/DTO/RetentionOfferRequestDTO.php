<?php

declare(strict_types=1);

namespace Waterfront\Domain\RetentionToolkit\DTO;

use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\RetentionToolkit\Enums\CustomerType;

readonly class RetentionOfferRequestDTO
{
    /**
     * @param list<RetentionOfferItemDTO> $items
     */
    public function __construct(
        public Customer $customer,
        public CustomerType $customerType,
        public string $puzzelTicketId,
        public array $items,
    ) {
    }
}
