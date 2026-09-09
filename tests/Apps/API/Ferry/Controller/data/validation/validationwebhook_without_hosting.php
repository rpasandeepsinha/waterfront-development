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
                    'id' => MigrationValidation::RESELLER_HOSTING_PIPE_PASSED->value,
                    'message' => 'reseller_hosting_migration reference: unique_reference_for_adf',
                ],
            ],
        ],
    ],
];
