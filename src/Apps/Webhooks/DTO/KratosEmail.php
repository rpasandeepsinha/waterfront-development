<?php

declare(strict_types=1);

namespace Waterfront\Apps\Webhooks\DTO;

use Waterfront\Apps\Webhooks\Enums\KratosTemplates;

class KratosEmail
{
    /**
     * @param string[] $identity
     */
    public function __construct(
        public readonly string $recipient,
        public readonly KratosTemplates $templateType,
        public readonly ActivationTemplateData|RecoveryTemplateData|NewIdentityTemplateData $templateData,
        public readonly array $identity,
    ) {
    }
}
