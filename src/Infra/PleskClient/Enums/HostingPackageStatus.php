<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Enums;

enum HostingPackageStatus: int
{
    // See: https://docs.plesk.com/en-US/obsidian/api-rpc/about-xml-api/reference/managing-subscriptions/subscription-settings/general-subscription-information/node-gen_setup-type-setgensetuptype.33860/
    case ENABLED = 0;
    case DISABLED_BY_PLESK_ADMIN = 16;
    case DISABLED_BY_RESELLER = 32;
    case DISABLED_BY_CUSTOMER = 64;
}
