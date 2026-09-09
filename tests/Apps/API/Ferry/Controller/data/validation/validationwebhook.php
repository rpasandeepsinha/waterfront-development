<?php

declare(strict_types=1);

use Waterfront\Domain\Ferry\Enums\AzureDataFactoryMessageType;
use Waterfront\Domain\Ferry\Enums\MigrationValidation;
use Waterfront\Domain\Ferry\Enums\MigrationValidationPipes;

return[
    'type' => AzureDataFactoryMessageType::VALIDATION_EXECUTED->value,
    'data' => [
        'reference' => 'unique_reference_for_adf',
        'results' => [
            MigrationValidationPipes::CUSTOMER->value => [
                [
                    'id' => MigrationValidation::CUSTOMER_PIPE_PASSED->value,
                    'message' => 'customer reference: unique_reference_for_adf',
                ],
            ],
            MigrationValidationPipes::SUBSCRIPTION->value => [
                [
                    'id' => MigrationValidation::SUBSCRIPTION_PIPE_PASSED->value,
                    'message' => 'subscription reference: unique_reference_for_adf',
                ],
            ],
            MigrationValidationPipes::BACKUP->value => [
                [
                    'id' => MigrationValidation::BACKUP_PIPE_PASSED->value,
                    'message' => 'backup_migration reference: unique_reference_for_adf',
                ],
            ],
            MigrationValidationPipes::DOMAIN_MIGRATION->value => [
                [
                    'id' => MigrationValidation::DOMAIN_MIGRATION_PIPE_PASSED->value,
                    'message' => 'domain_migration reference: unique_reference_for_adf',
                ],
            ],
            MigrationValidationPipes::DNS_CONFIGURATION->value => [
                [
                    'id' => MigrationValidation::DNS_CONFIGURATION_PIPE_PASSED->value,
                    'message' => 'dns_configuration reference: unique_reference_for_adf',
                ],
            ],
            MigrationValidationPipes::NAMESERVER_MIGRATION->value => [
                [
                    'id' => MigrationValidation::NAMESERVER_PIPE_PASSED->value,
                    'message' => 'dns_nameserver_migration reference: unique_reference_for_adf',
                ],
            ],
            MigrationValidationPipes::DNSSEC_ENABLE->value => [
                [
                    'id' => MigrationValidation::DNSSEC_PIPE_PASSED->value,
                    'message' => 'dnssec_enable reference: unique_reference_for_adf',
                ],
            ],
            MigrationValidationPipes::HOSTING_MIGRATION->value => [
                [
                    'id' => MigrationValidation::HOSTING_MIGRATION_PACKAGE_STATE->value,
                    'message' => 'Hosting instance with driver "directadmin" on server "my_hostname.nl" with username "i_do_exist" attempting to migrate as a directadmin_basic offering.',
                    'reference_subscription_id' => '3535',
                    'data' => [
                        'user_config' => [
                            'max_amount_domains' => 10,
                            'max_amount_mail_accounts' => 10,
                            'max_amount_databases' => 10,
                            'max_network_traffic_in_MB' => 1024,
                            'max_disk_space_in_MB' => 1024,
                        ],
                        'user_stats' => [],
                        'package_config' => [
                            'max_amount_domains' => 2,
                            'max_amount_mail_accounts' => 5,
                            'max_amount_databases' => 3,
                            'max_network_traffic_in_MB' => 1024,
                            'max_disk_space_in_MB' => 1024,
                        ],
                    ],
                ],
                [
                    'id' => MigrationValidation::HOSTING_MIGRATION_DNS_MANAGEMENT_STATE->value,
                    'message' => 'Hosting migration for subscription reference ID: {3535}, internal nameservers: {1}, domain is present in extension subscriptions or is null {0}, results in DNS setting: {1}',
                    'reference_subscription_id' => '3535',
                ],
                [
                    'id' => MigrationValidation::HOSTING_MIGRATION_PACKAGE_STATE->value,
                    'message' => 'Hosting instance with driver "integratedservice" on server "plesk.server.test" with username "plesk_username_test" attempting to migrate as a plesk_basic offering.',
                    'reference_subscription_id' => '3536',
                    'data' => [
                        'user_config' => [
                            'max_amount_domains' => 10,
                            'max_amount_mail_accounts' => 10,
                            'max_amount_databases' => 10,
                            'max_network_traffic_in_MB' => 1024,
                            'max_disk_space_in_MB' => 1024,
                        ],
                        'user_stats' => [],
                        'package_config' => [
                            'max_amount_domains' => 3,
                            'max_amount_mail_accounts' => 6,
                            'max_amount_databases' => 2,
                            'max_network_traffic_in_MB' => 2048,
                            'max_disk_space_in_MB' => 2048,
                        ],
                    ],
                ],
                [
                    'id' => MigrationValidation::HOSTING_MIGRATION_DNS_MANAGEMENT_STATE->value,
                    'message' => 'Hosting migration for subscription reference ID: {3536}, internal nameservers: {1}, domain is present in extension subscriptions or is null {0}, results in DNS setting: {1}',
                    'reference_subscription_id' => '3536',
                ],
                [
                    'id' => MigrationValidation::HOSTING_MIGRATION_PACKAGE_NOT_IN_SYNC->value,
                    'message' => 'Hosting instance with driver "integratedservice" on server "plesk.server.test" with username "plesk_username_test" service plan remote "basic" is not in sync with the local product "plesk_basic"',
                    'reference_subscription_id' => '3536',
                    'data' => [
                        'remote_package' => 'basic',
                        'payload_package' => 'plesk_basic',
                        'username' => 'plesk_username_test',
                    ],
                ],
                [
                    'id' => MigrationValidation::HOSTING_MIGRATION_PIPE_PASSED->value,
                    'message' => 'hosting_migration reference: unique_reference_for_adf',
                ],
            ],
            MigrationValidationPipes::MAIL_ONLY_MIGRATION->value => [
                [
                    'id' => MigrationValidation::MAIL_ONLY_MIGRATION_PIPE_PASSED->value,
                    'message' => 'mail_only_migration reference: unique_reference_for_adf',
                ],
            ],
            MigrationValidationPipes::SSL_MIGRATION->value => [
                [
                    'id' => MigrationValidation::SSL_PIPE_PASSED->value,
                    'message' => 'ssl_migration reference: unique_reference_for_adf',
                ],
            ],
            MigrationValidationPipes::REDIRECT_MIGRATION->value => [
                [
                    'id' => MigrationValidation::REDIRECT_PIPE_PASSED->value,
                    'message' => 'redirect_migration reference: unique_reference_for_adf',
                ],
            ],
            MigrationValidationPipes::SITEBUILDER_MIGRATION->value => [
                [
                    'id' => MigrationValidation::SITEBUILDER_PIPE_PASSED->value,
                    'message' => 'sitebuilder_migration reference: unique_reference_for_adf',
                ],
            ],
            MigrationValidationPipes::RESELLER_HOSTING_MIGRATION->value => [
                [
                    'id' => MigrationValidation::RESELLER_HOSTING_MIGRATION_PACKAGE_STATE->value,
                    'message' => 'Reseller Hosting instance with driver "directadmin" on server "my_hostname.nl" with username "i_do_exist_for_reseller" attempting to migrate as a reseller-brons offering.',
                    'reference_subscription_id' => '84111',
                    'data' => [
                        'user_config' => [
                            'max_amount_domains' => 5,
                            'max_amount_mail_accounts' => 10,
                            'max_amount_databases' => 15,
                            'max_network_traffic_in_MB' => 2048,
                            'max_disk_space_in_MB' => 2048,
                        ],
                        'user_stats' => [],
                        'package_config' => [
                            'max_amount_domains' => 5,
                            'max_amount_mail_accounts' => 10,
                            'max_amount_databases' => 15,
                            'max_network_traffic_in_MB' => 2048,
                            'max_disk_space_in_MB' => 2048,
                        ],
                    ],
                ],
                [
                    'id' => MigrationValidation::RESELLER_HOSTING_PIPE_PASSED->value,
                    'message' => 'reseller_hosting_migration reference: unique_reference_for_adf',
                ],
            ],
        ],
    ],
];
