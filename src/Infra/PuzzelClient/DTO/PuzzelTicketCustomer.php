<?php

declare(strict_types=1);

namespace Waterfront\Infra\PuzzelClient\DTO;

readonly class PuzzelTicketCustomer
{
    public function __construct(
        public string $email,
        public string|null $firstName = null,
        public string|null $lastName = null,
        public string|null $phoneNumber = null,
    ) {
    }
}
