<?php

declare(strict_types=1);

namespace Waterfront\Domain\Redirects\Enums;

enum DnsRedirectProvisionOption
{
    /**
     * OVERRIDE means that when we provision the records for redirect, we will remove any
     * conflicting record types that are currently present in the DNS zone and replace
     * them with the ALIAS / CNAME records needed for the redirect service to work.
     */
    case OVERRIDE;

    /**
     * IGNORE means that when we provision the records for redirect, we will ignore any
     * conflicting record types that are currently present in the DNS zone and leave
     * the DNS zone as is, meaning that the redirects will not work until removed.
     */
    case IGNORE;
}
