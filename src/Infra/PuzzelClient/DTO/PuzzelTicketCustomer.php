<?php

declare(strict_types=1);

namespace Waterfront\Infra\PuzzelClient\DTO;

readonly class PuzzelTicketCustomer
{
    public function __construct(
        public string $email,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $phoneNumber = null,
    ) {
    }
}
