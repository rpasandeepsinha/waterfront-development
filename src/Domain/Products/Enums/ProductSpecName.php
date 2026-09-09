<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Enums;

enum ProductSpecName: string
{
    case ACRONIS_CLOUD_STORAGE_GB = 'acronis.cloud_storage_gb';
    case ACRONIS_ENABLE_GOOGLE_WORKSPACE_DRIVE = 'acronis.enable_google_workspace_drive';
    case ACRONIS_GOOGLE_WORKSPACE_SEATS = 'acronis.google_workspace_seats';
    case ACRONIS_HAS_GOOGLE_WORKSPACE_DRIVE = 'acronis.has_google_workspace_drive';
    case ACRONIS_HOSTING_SERVERS = 'acronis.hosting_servers';
    case ACRONIS_LOCAL_STORAGE_GB = 'acronis.local_storage_gb';
    case ACRONIS_M365_SEATS = 'acronis.m365_seats';
    case ACRONIS_M365_SHAREPOINT_SITES = 'acronis.m365_sharepoint_sites';
    case ACRONIS_M365_TEAMS = 'acronis.m365_teams';
    case ACRONIS_MOBILE_DEVICES = 'acronis.mobile_devices';
    case ACRONIS_SERVERS = 'acronis.servers';
    case ACRONIS_VMS = 'acronis.virtual_machines';
    case ACRONIS_WEBSITES = 'acronis.websites';
    case ACRONIS_WORKSTATIONS = 'acronis.workstations';
    case BASEKIT_PACKAGE_REFERENCE = 'basekit.package-reference';
    case CANCEL_WITH_PARENT = 'product.cancel_with_parent';
    case COMES_WITH_FREE_PRODUCT_SLUG = 'product.comes_with_free_product_slug';

    case DNS_CAN_COUPLE_HOSTING_OR_REDIRECT = 'dns.can_couple_hosting_or_redirect';
    case DNS_CAN_EDIT_RECORDS = 'dns.can_edit_records';
    case DNS_IS_PREMIUM = 'dns.is_premium';
    case DNS_VISIBLE_LOG_LINES = 'dns.visible_log_lines';

    case DOMAIN_ALLOW_WHOIS = 'domain.allow_whois';
    case DOMAIN_ALLOW_WHOIS_PRIVATE = 'domain.allow_whois_private';
    case DOMAIN_DNSSEC_ENABLED = 'domain.dnssec_enabled';
    case DOMAIN_TLD_HAS_ZONECHECK = 'domain.tld-has-zonecheck';

    case HAS_SERVICE_PLUS = 'product.has_service_plus';

    case HOSTING_HAS_MAIL = 'hosting.has-mail';

    /**
     * If enabled, customers can manage their email accounts within Coast (instead of a SSO login to Plesk/DA)
     * Value should be 1 or 0.
     */
    case HOSTING_HAS_MAIL_MANAGEMENT = 'hosting.has-mail-management';

    case HOSTING_HAS_WEBSITE = 'hosting.has-website';
    case HOSTING_HAS_WP = 'hosting.has-wp';

    /**
     * Is used for different behavior in Coast and selecting server / provider during hosting.
     * Value should be 1 or 0.
     */
    case HOSTING_LEGACY_MAIL_ONLY = 'hosting.legacy-mail-only';

    case HOSTING_LIMITS_ADVISED_BOX_SIZE = 'hosting.limits.advised_box_size';
    case HOSTING_LIMITS_DISK_SPACE = 'hosting.limits.disk_space';
    case HOSTING_LIMITS_DISK_SPACE_SOFT = 'hosting.limits.disk_space_soft';
    case HOSTING_LIMITS_EXPIRATION = 'hosting.limits.expiration';
    case HOSTING_LIMITS_MAX_BOX = 'hosting.limits.max_box';
    case HOSTING_LIMITS_MAX_BOX_SIZE = 'hosting.limits.max_box_size';
    case HOSTING_LIMITS_MAX_DB = 'hosting.limits.max_db';
    case HOSTING_LIMITS_MAX_DOM_ALIASES = 'hosting.limits.max_dom_aliases';
    case HOSTING_LIMITS_MAX_MAILLISTS = 'hosting.limits.max_maillists';
    case HOSTING_LIMITS_MAX_SITE = 'hosting.limits.max_site';
    case HOSTING_LIMITS_MAX_SUBFTP_USERS = 'hosting.limits.max_subftp_users';
    case HOSTING_LIMITS_MAX_SUBDOM = 'hosting.limits.max_subdom';
    case HOSTING_LIMITS_MAX_TRAFFIC = 'hosting.limits.max_traffic';
    case HOSTING_LIMITS_MAX_TRAFFIC_SOFT = 'hosting.limits.max_traffic_soft';
    case HOSTING_LIMITS_MAX_UNITY_MOBILE_SITES = 'hosting.limits.max_unity_mobile_sites';
    case HOSTING_LIMITS_MAX_WEBAPPS = 'hosting.limits.max_webapps';
    case HOSTING_LIMITS_MAX_WU = 'hosting.limits.max_wu';
    case HOSTING_LIMITS_MBOX_QUOTA = 'hosting.limits.mbox_quota';
    case HOSTING_LIMITS_VIRTUAL_HOSTS = 'hosting.limits.virtual_hosts';

    case HOSTING_PERMISSIONS_ACCESS_APPCATALOG = 'hosting.permissions.access_appcatalog';
    case HOSTING_PERMISSIONS_ACCESS_SERVICE_USERS = 'hosting.permissions.access_service_users';
    case HOSTING_PERMISSIONS_ALLOW_FTP_BACKUPS = 'hosting.permissions.allow_ftp_backups';
    case HOSTING_PERMISSIONS_ALLOW_INSECURE_SITES = 'hosting.permissions.allow_insecure_sites';
    case HOSTING_PERMISSIONS_ALLOW_LOCAL_BACKUPS = 'hosting.permissions.allow_local_backups';
    case HOSTING_PERMISSIONS_CREATE_DOMAINS = 'hosting.permissions.create_domains';
    case HOSTING_PERMISSIONS_MANAGE_ANONFTP = 'hosting.permissions.manage_anonftp';
    case HOSTING_PERMISSIONS_MANAGE_CRONTAB = 'hosting.permissions.manage_crontab';
    case HOSTING_PERMISSIONS_MANAGE_DOMAIN_ALIASES = 'hosting.permissions.manage_domain_aliases';
    case HOSTING_PERMISSIONS_MANAGE_LOG = 'hosting.permissions.manage_log';
    case HOSTING_PERMISSIONS_MANAGE_MAIL_SETTINGS = 'hosting.permissions.manage_mail_settings';
    case HOSTING_PERMISSIONS_MANAGE_MAILLISTS = 'hosting.permissions.manage_maillists';
    case HOSTING_PERMISSIONS_MANAGE_NOT_CHROOT_SHELL = 'hosting.permissions.manage_not_chroot_shell';
    case HOSTING_PERMISSIONS_MANAGE_PERFORMANCE = 'hosting.permissions.manage_performance';
    case HOSTING_PERMISSIONS_MANAGE_PHOSTING = 'hosting.permissions.manage_phosting';
    case HOSTING_PERMISSIONS_MANAGE_PHP_SETTINGS = 'hosting.permissions.manage_php_settings';
    case HOSTING_PERMISSIONS_MANAGE_PHP_VERSION = 'hosting.permissions.manage_php_version';
    case HOSTING_PERMISSIONS_MANAGE_PROTECTED_DIRS = 'hosting.permissions.manage_protected_dirs';
    case HOSTING_PERMISSIONS_MANAGE_QUOTA = 'hosting.permissions.manage_quota';
    case HOSTING_PERMISSIONS_MANAGE_SH_ACCESS = 'hosting.permissions.manage_sh_access';
    case HOSTING_PERMISSIONS_MANAGE_SPAMFILTER = 'hosting.permissions.manage_spamfilter';
    case HOSTING_PERMISSIONS_MANAGE_SUBDOMAINS = 'hosting.permissions.manage_subdomains';
    case HOSTING_PERMISSIONS_MANAGE_SUBFTP = 'hosting.permissions.manage_subftp';
    case HOSTING_PERMISSIONS_MANAGE_VIRUSFILTER = 'hosting.permissions.manage_virusfilter';
    case HOSTING_PERMISSIONS_MANAGE_WEBSTAT = 'hosting.permissions.manage_webstat';
    case HOSTING_PERMISSIONS_MANAGE_WEBSITE_MAINTENANCE = 'hosting.permissions.manage_website_maintenance';

    case HOSTING_PHP_SETTINGS_ADDITIONAL_DIRECTIVES = 'hosting.php-settings.additional-directives';
    case HOSTING_PHP_SETTINGS_ALLOW_URL_FOPEN = 'hosting.php-settings.allow_url_fopen';
    case HOSTING_PHP_SETTINGS_DISPLAY_ERRORS = 'hosting.php-settings.display_errors';
    case HOSTING_PHP_SETTINGS_ERROR_REPORTING = 'hosting.php-settings.error_reporting';
    case HOSTING_PHP_SETTINGS_FILE_UPLOADS = 'hosting.php-settings.file_uploads';
    case HOSTING_PHP_SETTINGS_LOG_ERRORS = 'hosting.php-settings.log_errors';
    case HOSTING_PHP_SETTINGS_MAGIC_QUOTES_GPC = 'hosting.php-settings.magic_quotes_gpc';
    case HOSTING_PHP_SETTINGS_MEMORY_LIMIT = 'hosting.php-settings.memory_limit';
    case HOSTING_PHP_SETTINGS_REGISTER_GLOBALS = 'hosting.php-settings.register_globals';
    case HOSTING_PHP_SETTINGS_SAFE_MODE = 'hosting.php-settings.safe_mode';
    case HOSTING_PHP_SETTINGS_SAFE_MODE_EXEC_DIR = 'hosting.php-settings.safe_mode_exec_dir';
    case HOSTING_PHP_SETTINGS_SAFE_MODE_INCLUDE_DIR = 'hosting.php-settings.safe_mode_include_dir';
    case HOSTING_PHP_SETTINGS_SHORT_OPEN_TAG = 'hosting.php-settings.short_open_tag';

    case HOSTING_PROPERTIES_ASP = 'hosting.properties.asp';
    case HOSTING_PROPERTIES_CERTIFICATE_NAME = 'hosting.properties.certificate_name';
    case HOSTING_PROPERTIES_CGI = 'hosting.properties.cgi';
    case HOSTING_PROPERTIES_ERRDOCS = 'hosting.properties.errdocs';
    case HOSTING_PROPERTIES_FASTCGI = 'hosting.properties.fastcgi';
    case HOSTING_PROPERTIES_FTP_LOGIN = 'hosting.properties.ftp_login';
    case HOSTING_PROPERTIES_FTP_PASSWORD = 'hosting.properties.ftp_password';
    case HOSTING_PROPERTIES_PERL = 'hosting.properties.perl';
    case HOSTING_PROPERTIES_PHP = 'hosting.properties.php';
    case HOSTING_PROPERTIES_PYTHON = 'hosting.properties.python';
    case HOSTING_PROPERTIES_SHELL = 'hosting.properties.shell';
    case HOSTING_PROPERTIES_SSI = 'hosting.properties.ssi';
    case HOSTING_PROPERTIES_SSL = 'hosting.properties.ssl';
    case HOSTING_PROPERTIES_WEBSTAT = 'hosting.properties.webstat';
    case HOSTING_PROPERTIES_WEBSTAT_PROTECTED = 'hosting.properties.webstat_protected';

    case HOSTING_SERVICES_OUTGOING_EMAIL = 'hosting.services.outgoing_email';
    case HOSTING_SERVICES_REDIRECTING = 'hosting.services.redirecting';

    /**
     * Indicates whether the given product includes email spam filtering. Value
     * should be 'yes' instead of 1.
     */
    case HOSTING_SERVICES_SPAM_FILTER = 'hosting.services.spam_filter';

    case HOSTING_SERVICES_VPS = 'hosting.services.vps';
    case HOSTING_SHOW_HOSTING_SSO = 'hosting.show_hosting_sso';
    case HOSTING_SHOW_SSO = 'hosting.show_sso';
    case HOSTING_SSL_SOLD_SEPARATELY = 'hosting.ssl-sold-separately';
    case HOSTING_USES_MAIL_ONLY_SERVER = 'hosting.uses_mail_only_server';

    case MICROSOFT365_ALLOW_COPILOT = 'microsoft365.allow_copilot';

    case OTS_REQUIRES_PARENT_WEBHOSTING = 'ots.requires-parent-webhosting';

    /**
     * By default, we don't allow direct cancellation of a product if the subscription
     * is a child subscription. But for some products it makes sense to allow this,
     * such as PremiumDNS. This spec allows us to override the default behavior.
     */
    case PRODUCT_ALLOW_CANCEL_AS_CHILD = 'product.allow_cancel_as_child';
    case PRODUCT_AUTOMATICALLY_ADD_TO_CART = 'product.automatically-add-to-cart';
    case PRODUCT_COMPARISON_BADGE = 'product.comparison-badge';
    case PRODUCT_DOWNGRADE_WHEN_CANCELED = 'product.downgrade_when_cancelled';

    /**
     * Indicates that rather than subscriptions on this product producing their
     * own invoice lines, the price of this product should instead be added to
     * the parent item on the invoice. E.g. a legacy DNS subscription should not
     * be listed with a € 10 price on the invoice separately but instead the
     * € 10 should be added to the price of the parent domain invoice line.
     */
    case PRODUCT_MERGE_INVOICE_INTO_PARENT = 'product.merge_invoice_into_parent';

    case PRODUCT_SHOULD_BE_HIDDEN_IN_SHOPPINGCART = 'product.product-should-be-hidden-in-shoppingcart';

    case SERVICES_TECHNICAL_GRACE_PERIOD = 'services.technical_grace_period';

    /**
     * Set to false for mail-only products outside a HostingProductComposition.
     */
    case SHOP_IS_MAILHOSTING_PRODUCT = 'shop.is-mailhosting-product';

    /**
     * Set to false for mail-only products outside a HostingProductComposition.
     */
    case SHOP_IS_WEBHOSTING_PRODUCT = 'shop.is-webhosting-product';

    /**
     * Indicates that the OS template requires SSH key authentication
     * rather than password-based logins.
     */
    case SSH_KEY_REQUIRED = 'vps.ssh_key_required';

    case SSL_PRODUCT_ID = 'ssl.product_id';

    /**
     * Idicates that a redirect uses the legacy redirect database
     * (instead of delegating to an external control panel like Plesk or DirectAdmin).
     */
    case USES_LEGACY_REDIRECT_DATABASE = 'redirect.uses_legacy_redirect_database';

    /**
     * This product spec is used to indicate what template slug should be used
     * to look for templates at Cloudstack. This is used for the VPS product
     * when provisioning a VPS. Examples are Ubuntu-22.04 or Almalinux-9.
     */
    case VPS_CLOUDSTACK_TEMPLATE_SLUG = 'vps.cloudstack_template_slug';

    case VPS_CPU = 'vps.cpu';
    case VPS_MEMORY = 'vps.memory';
    case VPS_STORAGE = 'vps.storage';
    case VPS_LIMITS_CPU = 'vps.limits.cpu';
    case VPS_LIMITS_RAM = 'vps.limits.ram';
    case VPS_LIMITS_SSD = 'vps.limits.ssd';
    case WAIT_FOR_WP_TOOLKIT = 'product.wait_for_wp_toolkit';
}
