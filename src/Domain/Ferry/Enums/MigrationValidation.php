<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Enums;

enum MigrationValidation: string
{
    case PIPE_NOT_IMPLEMENTED = 'not_implemented';
    case DEFAULT_VALIDATION = 'laravel_validation';

    case CUSTOMER_PIPE_VALIDATION_UNKNOWN_EXCEPTION = 'customer_validation_unknown_exception';
    case CUSTOMER_PIPE_PASSED = 'customer_passed';

    case SUBSCRIPTION_PIPE_PASSED = 'subscription_passed';
    case SUBSCRIPTION_VOLUME_DISCOUNT_PRODUCT_INCORRECT = 'subscription_volume_discount_product_incorrect';
    case SUBSCRIPTION_ALREADY_MIGRATED = 'subscription_already_exists';

    case BACKUP_PIPE_PASSED = 'backup_passed';
    case BACKUP_MIGRATION_CUSTOMER_TENANT_ERROR = 'backup_migration_customer_tenant_error';
    case BACKUP_MIGRATION_BU_TENANT_ERROR = 'backup_migration_bu_tenant_error';
    case BACKUP_MIGRATION_SSO_ERROR = 'backup_migration_sso_error';

    case DOMAIN_MIGRATION_PIPE_PASSED = 'domain_migration_passed';
    case DOMAIN_MIGRATION_FETCH_NOT_FOUND = 'domain_not_found';
    case DOMAIN_MIGRATION_FETCH_FORBIDDEN = 'domain_forbidden';
    case DOMAIN_MIGRATION_ABNORMAL_STATUS = 'domain_abnormal_status';
    case DOMAIN_MIGRATION_DRIVER_CREDENTIALS_FAILED = 'domain_driver_credentials_not_found';
    case DOMAIN_MIGRATION_BUSINESS_UNIT_FAILED = 'domain_business_unit_not_found';
    case DOMAIN_MIGRATION_FETCH_HANDLE_FAILED = 'contact_handle_unavailable';
    case DOMAIN_MIGRATION_INVALID_PHONE = 'invalid_phone_number';

    case HOSTING_MIGRATION_PIPE_PASSED = 'hosting_migration_passed';
    case HOSTING_MIGRATION_PAYLOAD_INVALID = 'hosting_migration_payload_invalid';
    case HOSTING_MIGRATION_SERVER_INVALID = 'hosting_migration_server_invalid';
    case HOSTING_MIGRATION_USER_FETCH_FAILED = 'hosting_migration_fetch_failed';
    case HOSTING_MIGRATION_USER_SSO_FAILED = 'hosting_migration_sso_failed';
    case HOSTING_MIGRATION_DNS_MANAGEMENT_STATE = 'hosting_migration_dns_management_state';
    case HOSTING_MIGRATION_IS_RESELLER = 'hosting_migration_is_reseller';
    case HOSTING_MIGRATION_PACKAGE_STATE = 'hosting_migration_package_state';
    case HOSTING_MIGRATION_PACKAGE_NOT_IN_SYNC = 'hosting_migration_package_not_in_sync';
    case HOSTING_MIGRATION_PACKAGE_STATE_FAILED = 'hosting_migration_package_state_failed';

    case MAIL_ONLY_MIGRATION_PIPE_PASSED = 'mail_only_migration_passed';
    case MAIL_ONLY_MIGRATION_PAYLOAD_INVALID = 'mail_only_migration_payload_invalid';
    case MAIL_ONLY_MIGRATION_SERVER_INVALID = 'mail_only_migration_server_invalid';
    case MAIL_ONLY_MIGRATION_USER_FETCH_FAILED = 'mail_only_migration_fetch_failed';
    case MAIL_ONLY_MIGRATION_IS_RESELLER = 'mail_only_migration_is_reseller';
    case MAIL_ONLY_MIGRATION_USER_SSO_FAILED = 'mail_only_migration_sso_failed';
    case MAIL_ONLY_NO_LEGACY_SPAMEXPERTS_SERVERS_CONFIGURED = 'mail_only_no_legacy_spamexperts_servers_configured';

    case DNS_CONFIGURATION_PIPE_PASSED = 'dns_configuration_passed';
    case DNS_CONFIGURATION_ZONE_DOESNT_EXIST = 'dns_configuration_zone_doesnt_exist';
    case DNS_CONFIGURATION_ZONE_UNEXPECTED_EXCEPTION = 'dns_configuration_zone_unexpected_exception';
    case DNS_CONFIGURATION_ZONE_NO_RECORDS = 'dns_configuration_zone_no_records';
    case DNS_CONFIGURATION_ZONE_ALREADY_MASTER = 'dns_configuration_zone_already_master';
    case DNS_CONFIGURATION_ZONE_DOES_NOT_CONTAIN_SOA_RECORD = 'dns_configuration_zone_does_not_contain_soa_record';
    case DNS_CONFIGURATION_ZONE_DNSKEY_RRSIG_ALREADY_REMOVED = 'dns_configuration_dnskey_rrsig_already_removed';
    case DNS_CONFIGURATION_SOA_RECORDS_NOT_IN_SYNC = 'dns_configuration_soa_records_not_in_sync';
    case DNS_CONFIGURATION_DOMAIN_NAMESERVERS_INTERNAL = 'dns_configuration_domain_nameservers_internal';
    case DNS_CONFIGURATION_DOMAIN_NAMESERVERS_EXTERNAL = 'dns_configuration_domain_nameservers_external';
    case DNS_CONFIGURATION_UNABLE_TO_FETCH_SOA_RECORD_FROM_DOMAIN_NAMESERVER = 'dns_configuration_unable_to_fetch_soa_records_from_domain_nameserver';
    case DNS_CONFIGURATION_UNABLE_TO_FETCH_NS_RECORDS_FROM_DOMAIN_NAMESERVER = 'dns_configuration_unable_to_fetch_ns_records_from_domain_nameserver';

    case NAMESERVER_PIPE_PASSED = 'nameserver_migration_passed';
    case NAMESERVER_FETCH_DOMAIN_FAILED = 'nameserver_fetch_domain_failed';
    case NAMESERVER_HAS_NON_MIGRATEABLE_NAMESERVER = 'nameserver_has_non_migrateable_namseserver';
    case NAMESERVER_HAS_WHITELABEL_NAMESERVER = 'nameserver_has_whitelabel_namseserver';
    case NAMESERVER_HOSTNAME_NOT_STRING = 'nameserver_hostname_not_string';

    case DNSSEC_PIPE_PASSED = 'dnssec_migration_passed';
    case DNSSEC_TLD_NOT_SUPPORTED = 'dnssec_tld_not_supported';
    case DNSSEC_ZONE_UNEXPECTED_EXCEPTION = 'dnssec_zone_unexpected_exception';
    case DNSSEC_PIPE_FAILED = 'dnssec_tld_not_supported_failed';
    case DNSSEC_TLD_ZONE_DOES_NOT_EXIST = 'dnssec_zone_does_not_exist';
    case DNSSEC_ZONE_NOT_MASTER = 'dnssec_zone_not_master';

    case SSL_PIPE_PASSED = 'ssl_migration_passed';
    case SSL_MIGRATION_UNABLE_TO_PARSE_BASE_DOMAIN = 'ssl_unable_to_parse_base_domain';
    case SSL_MIGRATION_RTR_FETCH_DOMAIN_FAILED = 'ssl_domain_not_found_at_rtr';
    case SSL_MIGRATION_UNABLE_TO_FETCH_NS_RECORDS = 'ssl_domain_unable_to_fetch_ns_records';
    case SSL_MIGRATION_ZONE_DOES_NOT_EXIST = 'ssl_dns_zone_doesnt_exist';
    case SSL_MIGRATION_DOMAIN_ALREADY_PRESENT_AT_RTR = 'ssl_domain_already_present_at_rtr';
    case SSL_MIGRATION_EXTERNAL_NAMESERVER = 'ssl_domain_uses_external_nameservers';
    case SSL_MIGRATION_ZONE_NOT_MASTER = 'ssl_dns_zone_not_master';
    case SSL_MIGRATION_ZONE_UNEXPECTED_EXCEPTION = 'ssl_zone_unexpected_exception';
    case SSL_MIGRATION_HOSTING_SERVER_DOES_NOT_EXIST = 'ssl_hosting_server_does_not_exist';
    case SSL_MIGRATION_HOSTING_USER_FETCH_FAILED = 'ssl_hosting_user_fetch_failed';
    case SSL_MIGRATION_HOSTING_SITE_SSL_IS_DISABLED = 'ssl_hosting_user_ssl_is_disabled';

    case REDIRECT_PIPE_PASSED = 'redirect_migration_passed';
    case REDIRECT_EMPTY_REDIRECT_DATA = 'redirect_empty_redirect_data';
    case REDIRECT_DNS_ZONE_NOT_FOUND = 'redirect_dns_zone_does_not_exist';
    case REDIRECT_DNS_ZONE_UNEXPECTED_EXCEPTION = 'redirect_dns_zone_unexpected_exception';
    case REDIRECT_ALREADY_CREATED = 'redirect_already_created_in_redirect_database';
    case REDIRECT_NO_LEGACY_SERVERS_CONFIGURED = 'redirect_no_legacy_servers_configured';

    case SITEBUILDER_PIPE_PASSED = 'sitebuilder_migration_passed';
    case SITEBUILDER_PAYLOAD_INVALID = 'sitebuilder_payload_invalid';
    case SITEBUILDER_SERVER_INVALID = 'sitebuilder_server_invalid';
    case SITEBUILDER_FETCH_FAILED = 'sitebuilder_fetch_failed';
    case SITEBUILDER_USER_SSO_FAILED = 'sitebuilder_sso_failed';
    case SITEBUILDER_MAIL_ONLY_IS_RESELLER = 'sitebuilder_mail_only_is_reseller';
    case SITEBUILDER_MAIL_ONLY_SSO_FAILED = 'sitebuilder_mail_only_sso_failed';

    case RESELLER_HOSTING_PIPE_PASSED = 'reseller_hosting_migration_passed';
    case RESELLER_HOSTING_MIGRATION_IS_NOT_RESELLER = 'reseller_hosting_is_not_reseller';
    case RESELLER_HOSTING_MIGRATION_USER_FETCH_FAILED = 'reseller_hosting_user_fetch_failed';
    case RESELLER_HOSTING_MIGRATION_PACKAGE_STATE = 'reseller_hosting_package_state';
    case RESELLER_HOSTING_MIGRATION_PACKAGE_STATE_FAILED = 'reseller_hosting_package_state_failed';
    case RESELLER_HOSTING_MIGRATION_USER_SSO_FAILED = 'reseller_hosting_user_sso_failed';
    case RESELLER_HOSTING_MIGRATION_SERVER_INVALID = 'reseller_hosting_server_invalid';
}
