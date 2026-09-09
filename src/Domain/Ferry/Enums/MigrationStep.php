<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Enums;

enum MigrationStep: string
{
    case BACKUP_MIGRATION = 'backup_migration';
    case CONFIGURE_DNS = 'configure_dns';
    case CONFIGURE_DNS_DEFAULT_ZONE = 'configure_dns_default_zone';
    case CONFIGURE_DNS_EMPTY_ZONE = 'configure_dns_empty_zone';
    case CONFIGURE_DNS_ZONE_PROMOTION = 'configure_dns_zone_promotion';
    case CUSTOMER = 'customer';
    case DOMAIN_MIGRATION = 'domain_migration';
    case ENABLE_DNSSEC = 'enable_dnssec';
    case HOSTING_MIGRATION = 'hosting_migration';
    case MAIL_ONLY_MIGRATION = 'mail_only_migration';
    case NAMESERVER = 'nameserver';
    case NAMESERVER_SET_CURRENT = 'nameserver_set_current';
    case NAMESERVER_SET_DEFAULT = 'nameserver_set_default';
    case REDIRECT_MIGRATION = 'redirect_migration';
    case RESELLER_HOSTING_MIGRATION = 'reseller_hosting_migration';
    case SITEBUILDER_MIGRATION = 'sitebuilder_migration';
    case SSL_MIGRATION = 'ssl_migration';
    case SUBSCRIPTION = 'subscription';
}
