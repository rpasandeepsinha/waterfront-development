<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\Enums;

enum RoleId: string
{
    case ROOT_ADMIN = 'root_admin';
    case PARTNER_ADMIN = 'partner_admin';
    case COMPANY_ADMIN = 'company_admin';
    case UNIT_ADMIN = 'unit_admin';
    case READONLY_ADMIN = 'readonly_admin';
    case PROTECTION_ADMIN = 'protection_admin';
    case PROTECTION_RO_ADMIN = 'protection_ro_admin';
    case RESTORE_OPERATOR = 'restore_operator';
    case BACKUP_USER = 'backup_user';
    case SYNC_SHARE_ADMIN = 'sync_share_admin';
    case SYNC_SHARE_USER = 'sync_share_user';
    case SYNC_SHARE_GUEST = 'sync_share_guest';
    case PDS_OPERATOR = 'pds_operator';
    case PDS_SUPPORT = 'pds_support';
    case NOTARY_ADMIN = 'notary_admin';
    case NOTARY_USER = 'notary_user';
    case HCI_ADMIN = 'hci_admin';
    case OMNIVOICE_ADMIN = 'omnivoice_admin';
    case OMNIVOICE_USER = 'omnivoice_user';
    case GREATHORN_ADMIN = 'greathorn_admin';
    case GREATHORN_USER = 'greathorn_user';
    case GREATHORN_ANALYST = 'greathorn_analyst';
    case GREATHORN_CLIENT_MANAGER = 'greathorn_client_manager';
}
