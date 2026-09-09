<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\DNS\Enums;

/**
 * @see docs/Domain/DNS/README.md#Nameservers
 */
enum NameserverType: string
{
    case INTERNAL = 'internal';
    case EXTERNAL = 'external';
    case VANITY = 'vanity';
}
