<?php

declare(strict_types=1);

namespace Waterfront\Apps\OneOffScripts\MigrateSitebuilderSubscriptions;

use Waterfront\Domain\Sitebuilder\Enums\BasekitMigrationEligibility;

class BasekitMigrationEligibilityCounters
{
    /** @var array<string,int> */
    private array $counts = [];

    public function __construct()
    {
        foreach (BasekitMigrationEligibility::cases() as $counter) {
            $this->counts[$counter->value] = 0;
        }
    }

    public function increment(BasekitMigrationEligibility $key): void
    {
        $this->counts[$key->value]++;
    }

    public function getCounter(BasekitMigrationEligibility $key): int
    {
        return $this->counts[$key->value];
    }

    /** @return array<string,int> */
    public function logContext(): array
    {
        return $this->counts;
    }
}
