<?php

declare(strict_types=1);

namespace Waterfront\Apps\Webhooks\DTO;

class ActivationTemplateData
{
    public function __construct(
        public readonly string $activationCode,
        public readonly string $activationUrl,
        public readonly string $identity,
    ) {
    }
}
