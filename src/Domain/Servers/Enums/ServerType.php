<?php

declare(strict_types=1);

namespace Waterfront\Domain\Servers\Enums;

enum ServerType: string
{
    case DIRECTADMIN = 'directadmin';
    case DIRECTADMIN_MAIL = 'directadmin-mail';
    case PLESK = 'plesk';
    case SITEBUILDER = 'sitebuilder';
}
