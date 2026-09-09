<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Repositories;

use Waterfront\Domain\Hosting\Models\SpamExpertsCluster;

class SpamExpertsMigrationRepository
{
    public function getSpamExpertsClusterByMigratedCustomerBuName(string $buName): ?SpamExpertsCluster
    {
        // case-insensitive!
        return SpamExpertsCluster::where('business_unit', 'ilike', $buName)
            ->first();
    }
}
