<?php

declare(strict_types=1);

namespace Waterfront\Domain\Providers\Enums;

enum ProviderType: string
{
    case BACKUP = 'backup';
    case HOSTING = 'hosting';
    case DOMAIN = 'domain';
    case SSL = 'ssl';
    case SITEBUILDER = 'sitebuilder';
    case MAILONLY = 'mail-only';
}
