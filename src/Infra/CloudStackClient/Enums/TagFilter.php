<?php

declare(strict_types=1);

namespace Waterfront\Infra\CloudStackClient\Enums;

/**
 * Enum representing different tag filters in the CloudStack API that
 * we have agreed on with operations (CLDIN). These will be used to
 * filter for specific resources such as Templates or Networks.
 */
enum TagFilter: string
{
    case VPS_NETWORK = 'vps_network';
    case VPS_NETWORK_KEY = 'type';
    case TEMPLATE_SLUG = 'template_slug';
}
