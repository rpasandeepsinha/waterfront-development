<?php

declare(strict_types=1);

namespace Waterfront\Apps\Webhooks\DTO;

readonly class RecoveryTemplateData
{
    public function __construct(
        public string $recoveryCode,
        public string $recoveryLink,
        public string $identity,
    ) {
    }
}
