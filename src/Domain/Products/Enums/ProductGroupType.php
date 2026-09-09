<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Enums;

enum ProductGroupType: string
{
    case EXTENSION = 'extension';
    case HOSTING = 'hosting';
    case REDIRECT = 'redirect';
    case SSL = 'ssl';
    case DNS = 'dns';
    case OTHER = 'other';
    case DOMAIN_EXPANSION = 'domein-uitbreiding';
    /** @deprecated This group is just here for historical purposes. Use the volume discount group */
    case RESELLER_DISCOUNT = 'reseller-discount';
    case RESELLER_HOSTING = 'reseller-hosting';
    case CLOUDSTACK_VIRTUAL_MACHINE = 'cloudstack-virtual-machine';
    case CLOUDSTACK_MANAGER_DOMAIN = 'cloudstack-manager-domain';
    case CLOUDSTACK_VOLUME = 'cloudstack-volume';
    case CLOUDSTACK_OS = 'cloudstack-os';
    case MICROSOFT_365 = 'microsoft-365';
    case MANUAL_SUBSCRIPTION = 'manual-subscription';
    case ADD_ON = 'add-on';
    case VPS = 'vps';
    case ONE_TIME_SERVICE = 'one-time-service';
    case VOLUME_DISCOUNT = 'volume-discount';
    case BACKUP = 'backup';
}
