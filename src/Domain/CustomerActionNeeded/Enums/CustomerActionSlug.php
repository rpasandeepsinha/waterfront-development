<?php

declare(strict_types=1);

namespace Waterfront\Domain\CustomerActionNeeded\Enums;

enum CustomerActionSlug: string
{
    case ACCOUNT_VERIFICATION = 'account_verification';
    case DOMAIN_CONTACT_VERIFICATION = 'domain_verification';
    case DOMAIN_DEFERRED_TRANSFER = 'domain_deferred_transfer';
    case M365_MCA_SIGNED = 'm365_mca_signed';
    case ACRONIS_BACKUP = 'acronis_backup';
}
