<?php

declare(strict_types=1);

namespace Waterfront\Apps\Webhooks\DTO\Payt;

class PaytAdministration
{
    public function __construct(
        public int $id,
        public string|null $name,
        public string|null $originId,
    ) {
    }
}
