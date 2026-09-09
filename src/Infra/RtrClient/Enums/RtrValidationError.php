<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\Enums;

enum RtrValidationError: string
{
    case ADDRESS_INCORRECT = 'The first addressline should not contain a P.O.Box';
    case AUTH_CODE_INCORRECT = 'Incorrect authorization code';
    case AUTH_CODE_INVALID = 'The authorization code should be between 6 and 12 characters';
    case AUTH_CODE_REQUIRED = 'The authorization code is required';
    case CONTACT_INFO_MISSING = 'Contact requires extra information';
    case OBJECT_STATUS = 'Object status prohibits operation';
    case PRIVACY_PROTECT_NOT_SUPPORTED = 'Privacy protect is not supported';
    case TRANSFER_BLOCKED = 'Transfer is not possible for a domain with statuses';
    case TRANSFER_TOO_EARLY = 'Transfer is not possible for domains that have been registered in the past 60 days';
    case VAT_NUMBER_CONTACT_REQUIRED = 'The additional contact property \'vatno\' is mandatory';
    case VAT_NUMBER_REQUIRED = 'The property "VAT-NUMBER" is mandatory';
    case REGISTRY_REQUIREMENTS_NOT_MET = 'does not meet the requirements set by the registry';
}
