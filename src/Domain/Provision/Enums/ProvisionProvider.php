<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Enums;

enum ProvisionProvider: string
{
    case PLESK = 'plesk';
    case DIRECTADMIN = 'directadmin';
    case RTR = 'realtimeregister';
    case OPENPROVIDER = 'openprovider';
    case GANDI = 'gandi';
    case MICROSOFT_ONLINE = 'microsoft_online';
    case MICROSOFT_GRAPH = 'microsoft_graph';
    case MICROSOFT_IRMA = 'microsoft_irma';
    case CLOUDSTACK = 'cloudstack';
    case POWERDNS = 'powerdns';
    case BASEKIT = 'basekit';
    case ACRONIS = 'acronis';
    case CADDY = 'caddy';

    /**
     * There are cases where we are not using an external party
     * to configure certain logic. An example is the domain
     * couple logic which doesn't have a specific provider.
     */
    case INTERNAL = 'internal';
}
