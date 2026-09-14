<?php

declare(strict_types=1);

namespace Waterfront\Apps\Webhooks\DTO\Payt;

class PaytAdministration
{
    public function __construct(
        public int $id,
        public ?string $name,
        public ?string $originId,
    ) {
    }
}
