<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\Enums\OfferingItems;

enum UsageName: string
{
    case STORAGE = 'storage';
    case VMS = 'vms';
    case VM_STORAGE = 'vm_storage';
    case LOCAL_STORAGE = 'local_storage';
    case TOTAL_STORAGE = 'total_storage';
    case WORKSTATION_STORAGE = 'workstation_storage';
    case SERVER_STORAGE = 'server_storage';
    case MOBILE_STORAGE = 'mobile_storage';
    case MAILBOX_STORAGE = 'mailbox_storage';
    case WEBSITE_STORAGE = 'website_storage';
    case DR_STORAGE = 'dr_storage';
    case SEARCH_INDEX_STORAGE = 'search_index_storage';
}
