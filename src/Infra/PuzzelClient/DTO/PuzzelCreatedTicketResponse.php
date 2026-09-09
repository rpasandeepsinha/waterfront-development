<?php

declare(strict_types=1);

namespace Waterfront\Infra\PuzzelClient\DTO;

readonly class PuzzelCreatedTicketResponse
{
    public function __construct(
        public string $id,
    ) {
    }
}
