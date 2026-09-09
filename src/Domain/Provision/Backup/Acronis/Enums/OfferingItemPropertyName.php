<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Backup\Acronis\Enums;

/**
 * For Acronis provider.
 */
enum OfferingItemPropertyName: string
{
    case CLOUD_STORAGE = 'pg_base_storage';
    case VMS = 'pg_base_vms';
    case SERVERS = 'pg_base_servers';
    case WORKSTATIONS = 'pg_base_workstations';
    case MOBILES = 'pg_base_mobiles';
    case LOCAL_STORAGE = 'local_storage';
    case HOSTING_SERVERS = 'pg_base_web_hosting_servers';
    case M365_SEATS = 'pg_base_m365_seats';
    case M365_SHAREPOINT_SITES = 'pg_base_m365_sharepoint_sites';
    case M365_TEAMS = 'pg_base_m365_teams';
    case GOOGLE_WORKSPACE_SEATS = 'pg_base_gworkspace_seats';
    case GOOGLE_TEAM_DRIVE = 'pg_base_google_team_drive';
    case WEBSITES = 'pg_base_websites';
}
