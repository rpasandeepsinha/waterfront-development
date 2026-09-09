<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Enums;

enum MigrationValidationPipes: string
{
    case CUSTOMER = 'customer';
    case SUBSCRIPTION = 'subscription';
    case BACKUP = 'backup_migration';
    case DNS_CONFIGURATION = 'dns_configuration';
    case NAMESERVER_MIGRATION = 'dns_nameserver_migration';
    case DNSSEC_ENABLE = 'dnssec_enable';
    case DOMAIN_MIGRATION = 'domain_migration';
    case HOSTING_MIGRATION = 'hosting_migration';
    case MAIL_ONLY_MIGRATION = 'mail_only_migration';
    case SSL_MIGRATION = 'ssl_migration';
    case REDIRECT_MIGRATION = 'redirect_migration';
    case SITEBUILDER_MIGRATION = 'sitebuilder_migration';
    case RESELLER_HOSTING_MIGRATION = 'reseller_hosting_migration';
}
