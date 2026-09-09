<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Enums;

enum MigrationSource: string
{
    case MANUAL_MIGRATION = 'manual-migration';
    case AZURE_DATA_FACTORY = 'azure-data-factory';
}
