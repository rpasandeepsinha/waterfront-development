<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Enums;

enum HostingRetryType: string
{
    case BASIC = 'basic';
    case SITEBUILDER = 'sitebuilder';
    case MAIL_ONLY = 'mail_only';
    case RESELLER_HOSTING = 'reseller-hosting';
}
