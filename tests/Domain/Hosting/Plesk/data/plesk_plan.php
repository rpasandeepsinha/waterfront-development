<?php

declare(strict_types=1);

return [
    'status' => 'ok',
    'id' => '6',
    'name' => 'web-start',
    'guid' => 'a6cd87f6-b3fa-4867-8e2c-69cde9e2d1aa',
    'external-id' => [],
    'owner-login' => 'admin',
    'mail' => [
        'nonexistent-user' => [
            'reject' => [],
        ],
        'webmail' => 'yourhosting',
        'outgoing-messages-mbox-limit' => '100',
        'outgoing-messages-domain-limit' => '500',
        'outgoing-messages-subscription-limit' => '500',
        'outgoing-messages-enable-sendmail' => 'default',
    ],
    'limits' => [
        'overuse' => 'block',
        'limit' => [
            [
                'name' => 'max_site',
                'value' => '1',
            ],
            [
                'name' => 'max_subdom',
                'value' => '-1',
            ],
            [
                'name' => 'max_dom_aliases',
                'value' => '-1',
            ],
            [
                'name' => 'disk_space',
                'value' => '161061273600',
            ],
            [
                'name' => 'disk_space_soft',
                'value' => '90',
            ],
            [
                'name' => 'max_traffic',
                'value' => '-1',
            ],
            [
                'name' => 'max_traffic_soft',
                'value' => '-1',
            ],
            [
                'name' => 'max_wu',
                'value' => '-1',
            ],
            [
                'name' => 'max_subftp_users',
                'value' => '-1',
            ],
            [
                'name' => 'max_db',
                'value' => '100',
            ],
            [
                'name' => 'max_box',
                'value' => '250',
            ],
            [
                'name' => 'mbox_quota',
                'value' => '5368709120',
            ],
            [
                'name' => 'max_maillists',
                'value' => '0',
            ],
            [
                'name' => 'expiration',
                'value' => '-1',
            ],
            [
                'name' => 'ext_limit_wp_toolkit_wp_instances',
                'value' => '-1',
            ],
            [
                'name' => 'ext_limit_wp_toolkit_wp_backups',
                'value' => '-1',
            ],
            [
                'name' => 'ext_limit_wp_toolkit_smart_update_instances',
                'value' => '-1',
            ],
            [
                'name' => 'ext_limit_wp_toolkit_virtual_patches_instances',
                'value' => '0',
            ],
            [
                'name' => 'ext_limit_xovi_max_keywords',
                'value' => '0',
            ],
            [
                'name' => 'ext_limit_acronis_backup_recovery_points',
                'value' => '14',
            ],
            [
                'name' => 'upsell_site_builder',
                'value' => '0',
            ],
        ],
    ],
    'log-rotation' => [
        'on' => [
            'log-condition' => [
                'log-bytime' => 'Daily',
            ],
            'log-max-num-files' => '5',
            'log-compress' => 'true',
        ],
    ],
    'preferences' => [
        'stat' => '15',
        'maillists' => 'false',
        'mailservice' => 'true',
        'dns_zone_type' => 'master',
    ],
    'hosting' => [
        'vrt_hst' => [
            'property' => [
                [
                    'name' => 'ssl',
                    'value' => 'true',
                ],
                [
                    'name' => 'ssl-redirect',
                    'value' => 'false',
                ],
                [
                    'name' => 'webstat',
                    'value' => 'awstats',
                ],
                [
                    'name' => 'webstat_protected',
                    'value' => 'true',
                ],
                [
                    'name' => 'errdocs',
                    'value' => 'true',
                ],
                [
                    'name' => 'wu_script',
                    'value' => 'false',
                ],
                [
                    'name' => 'shell',
                    'value' => '/bin/false',
                ],
                [
                    'name' => 'ftp_quota',
                    'value' => '161061273600',
                ],
                [
                    'name' => 'php_handler_id',
                    'value' => 'x-httpd-lsphp-82',
                ],
                [
                    'name' => 'php_served_by_nginx',
                    'value' => 'false',
                ],
                [
                    'name' => 'unpaid_website_status',
                    'value' => 'suspended',
                ],
                [
                    'name' => 'ssi',
                    'value' => 'false',
                ],
                [
                    'name' => 'php',
                    'value' => 'true',
                ],
                [
                    'name' => 'cgi',
                    'value' => 'false',
                ],
                [
                    'name' => 'perl',
                    'value' => 'false',
                ],
                [
                    'name' => 'python',
                    'value' => 'false',
                ],
                [
                    'name' => 'fastcgi',
                    'value' => 'true',
                ],
            ],
        ],
    ],
    'performance' => [
        'bandwidth' => '-1',
        'max_connections' => '-1',
    ],
    'permissions' => [
        'permission' => [
            [
                'name' => 'manage_phosting_ssi',
                'value' => 'true',
            ],
            [
                'name' => 'manage_phosting_php',
                'value' => 'true',
            ],
            [
                'name' => 'manage_phosting_cgi',
                'value' => 'true',
            ],
            [
                'name' => 'manage_phosting_perl',
                'value' => 'true',
            ],
            [
                'name' => 'manage_phosting_python',
                'value' => 'true',
            ],
            [
                'name' => 'manage_phosting_fastcgi',
                'value' => 'true',
            ],
            [
                'name' => 'manage_sh_access',
                'value' => 'false',
            ],
            [
                'name' => 'manage_phosting_errdocs',
                'value' => 'true',
            ],
            [
                'name' => 'manage_phosting_ssl',
                'value' => 'true',
            ],
            [
                'name' => 'manage_phosting_webdeploy',
                'value' => 'true',
            ],
            [
                'name' => 'manage_dns',
                'value' => 'false',
            ],
            [
                'name' => 'manage_phosting',
                'value' => 'true',
            ],
            [
                'name' => 'manage_php_settings',
                'value' => 'true',
            ],
            [
                'name' => 'manage_php_version',
                'value' => 'true',
            ],
            [
                'name' => 'allow_insecure_sites',
                'value' => 'true',
            ],
            [
                'name' => 'manage_not_chroot_shell',
                'value' => 'false',
            ],
            [
                'name' => 'manage_anonftp',
                'value' => 'false',
            ],
            [
                'name' => 'manage_crontab',
                'value' => 'true',
            ],
            [
                'name' => 'manage_spamfilter',
                'value' => 'false',
            ],
            [
                'name' => 'manage_virusfilter',
                'value' => 'false',
            ],
            [
                'name' => 'allow_local_backups',
                'value' => 'false',
            ],
            [
                'name' => 'allow_ftp_backups',
                'value' => 'false',
            ],
            [
                'name' => 'allow_account_local_backups',
                'value' => 'false',
            ],
            [
                'name' => 'allow_account_ftp_backups',
                'value' => 'true',
            ],
            [
                'name' => 'manage_webstat',
                'value' => 'true',
            ],
            [
                'name' => 'manage_log',
                'value' => 'false',
            ],
            [
                'name' => 'access_appcatalog',
                'value' => 'false',
            ],
            [
                'name' => 'create_domains',
                'value' => 'true',
            ],
            [
                'name' => 'manage_subdomains',
                'value' => 'true',
            ],
            [
                'name' => 'manage_domain_aliases',
                'value' => 'true',
            ],
            [
                'name' => 'manage_subftp',
                'value' => 'true',
            ],
            [
                'name' => 'manage_mail_settings',
                'value' => 'true',
            ],
            [
                'name' => 'manage_maillists',
                'value' => 'false',
            ],
            [
                'name' => 'manage_mail_autodiscover',
                'value' => 'true',
            ],
            [
                'name' => 'manage_performance',
                'value' => 'false',
            ],
            [
                'name' => 'manage_quota',
                'value' => 'false',
            ],
            [
                'name' => 'select_db_server',
                'value' => 'false',
            ],
            [
                'name' => 'remote_db_connection',
                'value' => 'false',
            ],
            [
                'name' => 'manage_website_maintenance',
                'value' => 'true',
            ],
            [
                'name' => 'manage_protected_dirs',
                'value' => 'true',
            ],
            [
                'name' => 'access_service_users',
                'value' => 'true',
            ],
            [
                'name' => 'ext_permission_wp_toolkit_manage_wordpress_toolkit',
                'value' => 'true',
            ],
            [
                'name' => 'ext_permission_wp_toolkit_manage_security_wordpress_toolkit',
                'value' => 'true',
            ],
            [
                'name' => 'ext_permission_wp_toolkit_manage_cloning',
                'value' => 'false',
            ],
            [
                'name' => 'ext_permission_wp_toolkit_manage_syncing',
                'value' => 'true',
            ],
            [
                'name' => 'ext_permission_wp_toolkit_manage_autoupdates',
                'value' => 'true',
            ],
            [
                'name' => 'ext_permission_wp_toolkit_manage_smart_php_update',
                'value' => 'true',
            ],
            [
                'name' => 'ext_permission_git_manage_git',
                'value' => 'true',
            ],
            [
                'name' => 'ext_permission_nodejs_support_management',
                'value' => 'true',
            ],
            [
                'name' => 'ext_permission_nodejs_state_management',
                'value' => 'true',
            ],
            [
                'name' => 'ext_permission_nodejs_version_management',
                'value' => 'true',
            ],
            [
                'name' => 'ext_permission_siteprobuilder_siteprobuilder_main',
                'value' => 'true',
            ],
            [
                'name' => 'ext_permission_acronis_backup_acronis_backup',
                'value' => 'true',
            ],
        ],
    ],
    'plan-items' => [],
    'aps-filter' => 'false',
    'packages' => [],
    'php-settings' => [
        'setting' => [
            [
                'name' => 'safe_mode',
                'value' => 'off',
            ],
            [
                'name' => 'open_basedir',
                'value' => 'none',
            ],
            [
                'name' => 'error_reporting',
                'value' => 'E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED',
            ],
            [
                'name' => 'memory_limit',
                'value' => '256M',
            ],
            [
                'name' => 'max_execution_time',
                'value' => '900',
            ],
            [
                'name' => 'max_input_time',
                'value' => '900',
            ],
            [
                'name' => 'post_max_size',
                'value' => '1024M',
            ],
            [
                'name' => 'upload_max_filesize',
                'value' => '1024M',
            ],
            [
                'name' => 'disable_functions',
                'value' => 'exec, system, passthru, shell_exec, proc_close, proc_open, dl, popen, show_source, posix_kill, posix_mkfifo, posix_getpwuid, posix_setpgid, posix_setsid, posix_setuid, posix_setgid, posix_uname, pcntl_exec, expect_popen, opcache_get_status,',
            ],
            [
                'name' => 'pm.max_children',
                'value' => '10',
            ],
            [
                'name' => 'pm',
                'value' => 'ondemand',
            ],
            [
                'name' => 'pm.start_servers',
                'value' => '1',
            ],
            [
                'name' => 'pm.min_spare_servers',
                'value' => '1',
            ],
            [
                'name' => 'pm.max_spare_servers',
                'value' => '1',
            ],
            [
                'name' => 'additional-directives',
                'value' => [],
            ],
        ],
    ],
    'web-server-settings' => [
        'restrict-follow-sym-links' => [],
        'nginx-proxy-mode' => [],
    ],
];
