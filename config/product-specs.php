<?php

declare(strict_types=1);

return [
    'services' => [
        'technical_grace_period' => [
            'type' => 'integer',
            'show-in-form' => true,
        ],
    ],
    'hosting' => [
        'has-website' => [
            'type' => 'boolean',
            'show-in-form' => true,
        ],
        'has-mail' => [
            'type' => 'boolean',
            'show-in-form' => true,
        ],
        'has-wp' => [
            'type' => 'boolean',
            'show-in-form' => true,
        ],
        'has-mail-management' => [
            'type' => 'boolean',
            'show-in-form' => true,
        ],
        'legacy-mail-only' => [
            'type' => 'boolean',
            'show-in-form' => true,
        ],
        'uses_mail_only_server' => [
            'type' => 'boolean',
            'show-in-form' => true,
        ],
        'show_hosting_sso' => [
            'type' => 'boolean',
            'show-in-form' => true,
        ],
        'ssl-sold-separately' => [
            'type' => 'boolean',
            'show-in-form' => true,
        ],
        'services' => [
            'redirecting' => [
                'type' => 'boolean',
                'show-in-form' => true,
            ],
            'spam_filter' => [
                'type' => 'boolean',
                'show-in-form' => true,
            ],
            'outgoing_email' => [
                'type' => 'boolean',
                'show-in-form' => true,
            ],
            'vps' => [
                'type' => 'boolean',
                'show-in-form' => false,
            ],
        ],
        'properties' => [
            'ftp_login' => [
                'type' => 'string',
            ],
            'ftp_password' => [
                'type' => 'string',
            ],
            'certificate_name' => [
                'type' => 'string',
            ],
            'shell' => [
                'type' => 'string',
                'default' => '/bin/bash',
            ],
            'ssl' => [
                'type' => 'boolean',
                'default' => 1,
            ],
            'asp' => [
                'type' => 'boolean',
                'default' => 0,
            ],
            'ssi' => [
                'type' => 'boolean',
                'default' => 0,
            ],
            'php' => [
                'type' => 'boolean',
                'default' => 1,
            ],
            'cgi' => [
                'type' => 'boolean',
                'default' => 0,
            ],
            'fastcgi' => [
                'type' => 'boolean',
                'default' => 0,
            ],
            'perl' => [
                'type' => 'boolean',
                'default' => 0,
            ],
            'python' => [
                'type' => 'boolean',
                'default' => 0,
            ],
            'errdocs' => [
                'type' => 'boolean',
                'default' => 0,
            ],
            'webstat' => [
                'type' => 'string',
                'default' => 'awstats',
            ],
            'webstat_protected' => [
                'type' => 'boolean',
                'default' => 1,
            ],
        ],
        'limits' => [
            'max_site' => [
                'type' => 'integer',
                'default' => 1,
            ],
            'max_subdom' => [
                'type' => 'integer',
                'default' => -1,
            ],
            'max_dom_aliases' => [
                'type' => 'integer',
                'default' => -1,
            ],
            'disk_space' => [
                'type' => 'bytes',
                'show-in-form' => true,
                'default' => null,
            ],
            'virtual_hosts' => [
                'type' => 'integer',
                'show-in-form' => true,
                'default' => 0,
            ],
            'disk_space_soft' => [
                'type' => 'bytes',
                'default' => -1,
            ],
            'max_traffic' => [
                'type' => 'bytes',
                'show-in-form' => true,
                'default' => null,
            ],
            'max_traffic_soft' => [
                'type' => 'bytes',
                'default' => -1,
            ],
            'max_wu' => [
                'type' => 'integer',
                'default' => 0,
            ],
            'max_subftp_users' => [
                'type' => 'integer',
                'default' => 10,
            ],
            'max_db' => [
                'type' => 'integer',
                'show-in-form' => true,
                'default' => null,
            ],
            'max_box' => [
                'type' => 'integer',
                'show-in-form' => true,
                'default' => null,
            ],
            'advised_box_size' => [
                'type' => 'bytes',
                'show-in-form' => true,
                'default' => null,
            ],
            'max_box_size' => [
                'type' => 'bytes',
                'show-in-form' => true,
                'default' => null,
            ],
            'mbox_quota' => [
                'type' => 'bytes',
                'default' => -1,
            ],
            'max_maillists' => [
                'type' => 'integer',
                'default' => -1,
            ],
            'max_unity_mobile_sites' => [
                'type' => 'integer',
                'default' => 0,
            ],
            'max_webapps' => [
                'type' => 'integer',
                'default' => 0,
            ],
            'expiration' => [
                'type' => 'timestamp',
                'default' => -1,
            ],
        ],
        'permissions' => [
            'access_appcatalog' => [
                'type' => 'boolean',
                'default' => 0,
            ],
            'access_service_users' => [
                'type' => 'boolean',
                'default' => 1,
            ],
            'allow_local_backups' => [
                'type' => 'boolean',
                'default' => 1,
            ],
            'allow_ftp_backups' => [
                'type' => 'boolean',
                'default' => 1,
            ],
            'allow_insecure_sites' => [
                'type' => 'boolean',
                'default' => 1,
            ],
            'create_domains' => [
                'type' => 'boolean',
                'default' => 1,
            ],
            'manage_anonftp' => [
                'type' => 'boolean',
                'default' => 0,
            ],
            'manage_crontab' => [
                'type' => 'boolean',
                'show-in-form' => true,
                'default' => 0,
            ],
            'manage_domain_aliases' => [
                'type' => 'boolean',
                'default' => 1,
            ],
            'manage_log' => [
                'type' => 'boolean',
                'default' => 0,
            ],
            'manage_mail_settings' => [
                'type' => 'boolean',
                'default' => 1,
            ],
            'manage_maillists' => [
                'type' => 'boolean',
                'default' => 0,
            ],
            'manage_not_chroot_shell' => [
                'type' => 'boolean',
                'default' => 0,
            ],
            'manage_performance' => [
                'type' => 'boolean',
                'default' => 0,
            ],
            'manage_phosting' => [
                'type' => 'boolean',
                'default' => 1,
            ],
            'manage_php_version' => [
                'type' => 'boolean',
                'default' => 1,
            ],
            'manage_php_settings' => [
                'type' => 'boolean',
                'default' => 0,
            ],
            'manage_protected_dirs' => [
                'type' => 'boolean',
                'default' => 1,
            ],
            'manage_quota' => [
                'type' => 'boolean',
                'default' => 0,
            ],
            'manage_sh_access' => [
                'type' => 'boolean',
                'default' => 0,
            ],
            'manage_spamfilter' => [
                'type' => 'boolean',
                'default' => 0,
            ],
            'manage_subdomains' => [
                'type' => 'boolean',
                'default' => 1,
            ],
            'manage_subftp' => [
                'type' => 'boolean',
                'default' => 1,
            ],
            'manage_virusfilter' => [
                'type' => 'boolean',
                'default' => 0,
            ],
            'manage_webstat' => [
                'type' => 'boolean',
                'default' => 0,
            ],
            'manage_website_maintenance' => [
                'type' => 'boolean',
                'default' => 1,
            ],
        ],
        'php-settings' => [
            'memory_limit' => [
                'type' => 'string',
                'show-in-form' => true,
                'default' => null,
            ],
            'safe_mode' => [
                'type' => 'boolean',
                'default' => 0,
            ],
            'safe_mode_include_dir' => [
                'type' => 'string',
            ],
            'safe_mode_exec_dir' => [
                'type' => 'string',
            ],
            'register_globals' => [
                'type' => 'boolean',
                'default' => 0,
            ],
            'error_reporting' => [
                'type' => 'string',
                'default' => 'E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED',
            ],
            'display_errors' => [
                'type' => 'boolean',
                'default' => 0,
            ],
            'log_errors' => [
                'type' => 'boolean',
                'default' => 1,
            ],
            'allow_url_fopen' => [
                'type' => 'boolean',
                'default' => 1,
            ],
            'file_uploads' => [
                'type' => 'boolean',
                'default' => 1,
            ],
            'short_open_tag' => [
                'type' => 'boolean',
                'default' => 1,
            ],
            'magic_quotes_gpc' => [
                'type' => 'boolean',
                'default' => 0,
            ],
            'additional-directives' => [
                'type' => 'string',
            ],
        ],
    ],
    'domain' => [
        'allow_whois' => [
            'type' => 'boolean',
            'show-in-form' => true,
        ],
        'allow_whois_private' => [
            'type' => 'string',
            'show-in-form' => true,
        ],
        'dnssec_enabled' => [
            'type' => 'boolean',
            'show-in-form' => true,
        ],
        'tld-has-zonecheck' => [
            'type' => 'boolean',
            'show-in-form' => true,
        ],
    ],
    'dns' => [
        'is_premium' => [
            'type' => 'boolean',
            'show-in-form' => true,
        ],
        'visible_log_lines' => [
            'type' => 'integer',
            'show-in-form' => true,
        ],
        'can_edit_records' => [
            'type' => 'boolean',
            'show-in-form' => true,
        ],
        'can_couple_hosting_or_redirect' => [
            'type' => 'boolean',
            'show-in-form' => true,
        ],
    ],
    'product' => [
        'downgrade_when_cancelled' => [
            'type' => 'string',
            'show-in-form' => true,
        ],
        'product-should-be-hidden-in-shoppingcart' => [
            'type' => 'boolean',
            'show-in-form' => true,
        ],
        'allow_cancel_as_child' => [
            'type' => 'boolean',
            'show-in-form' => true,
        ],
        'merge_invoice_into_parent' => [
            'type' => 'boolean',
            'show-in-form' => true,
        ],
        'wait_for_wp_toolkit' => [
            'type' => 'boolean',
            'show-in-form' => true,
        ],
        'comparison-badge' => [
            'type' => 'string',
            'show-in-form' => true,
        ],
        'comes_with_free_product_slug' => [
            'type' => 'string',
            'show-in-form' => true,
        ],
        'has_service_plus' => [
            'type' => 'boolean',
            'show-in-form' => true,
        ],
    ],
    'ots' => [
        'requires-parent-webhosting' => [
            'type' => 'boolean',
            'show-in-form' => true,
        ],
    ],
    'shop' => [
        'is-mailhosting-product' => [
            'type' => 'boolean',
            'show-in-form' => true,
        ],
        'is-webhosting-product' => [
            'type' => 'boolean',
            'show-in-form' => true,
        ],
    ],
    'ssl' => [
        'product_id' => [
            'type' => 'string',
            'show-in-form' => true,
        ],
    ],
    'microsoft365' => [
        'allow_copilot' => [
            'type' => 'boolean',
            'show-in-form' => true,
        ],
    ],
    'vps' => [
        'cloudstack_template_slug' => [
            'type' => 'string',
            'show-in-form' => true,
            'default' => null,
        ],
        'memory' => [
            'type' => 'bytes',
            'show-in-form' => true,
            'default' => null,
        ],
        'storage' => [
            'type' => 'bytes',
            'show-in-form' => true,
            'default' => null,
        ],
        'cpu' => [
            'type' => 'bytes',
            'show-in-form' => true,
            'default' => null,
        ],
        'ssh_key_required' => [
            'type' => 'boolean',
            'show-in-form' => true,
            'default' => 0,
        ],
    ],
    'basekit' => [
        'package-reference' => [
            'type' => 'integer',
            'show-in-form' => true,
        ],
    ],
    'redirect' => [
        'uses_legacy_redirect_database' => [
            'type' => 'boolean',
            'show-in-form' => true,
        ],
    ],
    'acronis' => [
        'cloud_storage_gb' => [
            'type' => 'integer',
            'show-in-form' => true,
            'default' => null,
        ],
        'local_storage_gb' => [
            'type' => 'integer',
            'show-in-form' => true,
            'default' => null,
        ],
        'mobile_devices' => [
            'type' => 'integer',
            'show-in-form' => true,
            'default' => null,
        ],
        'workstations' => [
            'type' => 'integer',
            'show-in-form' => true,
            'default' => null,
        ],
        'virtual_machines' => [
            'type' => 'integer',
            'show-in-form' => true,
            'default' => null,
        ],
        'servers' => [
            'type' => 'integer',
            'show-in-form' => true,
            'default' => null,
        ],
        'hosting_servers' => [
            'type' => 'integer',
            'show-in-form' => true,
            'default' => null,
        ],
        'm365_seats' => [
            'type' => 'integer',
            'show-in-form' => true,
            'default' => null,
        ],
        'm365_sharepoint_sites' => [
            'type' => 'integer',
            'show-in-form' => true,
            'default' => null,
        ],
        'm365_teams' => [
            'type' => 'integer',
            'show-in-form' => true,
            'default' => null,
        ],
        'google_workspace_seats' => [
            'type' => 'integer',
            'show-in-form' => true,
            'default' => null,
        ],
        'enable_google_workspace_drive' => [
            'type' => 'boolean',
            'show-in-form' => true,
            'default' => null,
        ],
        'websites' => [
            'type' => 'integer',
            'show-in-form' => true,
            'default' => null,
        ],
    ],
];
