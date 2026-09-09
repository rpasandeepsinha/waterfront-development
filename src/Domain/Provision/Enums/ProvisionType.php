<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Enums;

enum ProvisionType: string
{
    case HOSTING = 'hosting';
    case RESELLER_HOSTING = 'reseller_hosting';
    case REDIRECT = 'redirect';
    case VPS = 'vps';
    case DNS = 'dns';
    case M365 = 'microsoft365';
    case SSL = 'ssl';
    case DOMAIN_NAME = 'domain_name';
    case DOMAIN_NAME_COUPLING = 'domain_name_coupling';
    case SITEBUILDER = 'sitebuilder';
    case BACKUP = 'backup';
}
