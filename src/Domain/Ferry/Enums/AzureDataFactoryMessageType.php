<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Enums;

enum AzureDataFactoryMessageType: string
{
    case SUBSCRIPTION_CREATED = 'subscription_created';
    case SUBSCRIPTION_CREATION_UNSUCCESSFUL = 'subscription_creation_unsuccessful';
    case MIGRATION_EXECUTED = 'migration_executed';
    case MIGRATION_EXECUTED_UNSUCCESSFUL = 'migration_executed_error';
    case VALIDATION_EXECUTED = 'validation_executed';
    case DIRECT_DEBIT_CREATION_UNSUCCESSFUL = 'direct_debit_creation_unsuccessful';
    case MIGRATION_BULK_CUSTOMER_REPORT = 'migration_bulk_customer_report';
}
