<?php

declare(strict_types=1);

namespace Waterfront\Apps\Webhooks\Enums;

enum KratosTemplates: string
{
    case VERIFICATION_CODE_VALID = 'verification_code_valid';
    case VERIFICATION_NEW_IDENTITY = 'verification_new_identity';
    case RECOVERY_CODE_VALID = 'recovery_code_valid';
}
