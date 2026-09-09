<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Listeners;

use Waterfront\Domain\Ferry\Jobs\TechnicalDomainMigrationJob;

class ExtendedTechnicalDomainMigrationJob extends TechnicalDomainMigrationJob
{
    public function runMigration(): void
    {
    }
}
