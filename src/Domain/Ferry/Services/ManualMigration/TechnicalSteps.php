<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Services\ManualMigration;

use Waterfront\Domain\Ferry\Enums\ManualMigrationOption;
use Waterfront\Domain\Ferry\Enums\MigrationStep;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Support\Exceptions\NotImplementedException;

class TechnicalSteps
{
    /**
     * @param ?array<ManualMigrationOption> $options
     *
     * @return array<MigrationStep>
     */
    public function getSteps(
        ProductGroupType $productGroup,
        ?array $options,
        ?bool $domainInSupportedRegistry = null,
    ): array {
        switch ($productGroup) {
            case ProductGroupType::EXTENSION:
                assert($options !== null);
                assert($domainInSupportedRegistry !== null);
                $steps = $this->calculateDomainSteps($options, $domainInSupportedRegistry);
                break;
            case ProductGroupType::HOSTING:
                assert($options === null);
                $steps = $this->calculateHostingSteps();
                break;
            default:
                throw new NotImplementedException();
        }

        return $this->sortSteps($steps);
    }

    /**
     * @return MigrationStep[]
     */
    private function calculateHostingSteps(): array
    {
        return [MigrationStep::HOSTING_MIGRATION];
    }

    /**
     * @param array<ManualMigrationOption> $options
     *
     * @return MigrationStep[]
     */
    private function calculateDomainSteps(array $options, bool $domainInSupportedRegistry): array
    {
        $steps = [];

        if ($domainInSupportedRegistry) {
            $steps[] = MigrationStep::DOMAIN_MIGRATION;
        }

        foreach ($options as $option) {
            $steps[] = match ($option) {
                ManualMigrationOption::DNSSEC_DO_NOTHING => null,
                ManualMigrationOption::DNSSEC_ENABLE => MigrationStep::ENABLE_DNSSEC,
                ManualMigrationOption::NAMESERVERS_DO_NOTHING => MigrationStep::NAMESERVER_SET_CURRENT,
                ManualMigrationOption::NAMESERVERS_UPDATE_NEW => MigrationStep::NAMESERVER_SET_DEFAULT,
                ManualMigrationOption::DNS_DO_NOTHING => null,
                ManualMigrationOption::DNS_NEW_EMPTY => MigrationStep::CONFIGURE_DNS_EMPTY_ZONE,
                ManualMigrationOption::DNS_UPDATE_NATIVE => MigrationStep::CONFIGURE_DNS_ZONE_PROMOTION,
                ManualMigrationOption::DNS_DEFAULT_TEMPLATE => MigrationStep::CONFIGURE_DNS_DEFAULT_ZONE,
            };
        }

        return array_values(array_filter($steps, fn ($step) => $step !== null));
    }

    /**
     * @param MigrationStep[] $migrationSteps
     *
     * @return MigrationStep[]
     */
    private function sortSteps(array $migrationSteps): array
    {
        $defaultOrder = [
            MigrationStep::DOMAIN_MIGRATION,
            MigrationStep::CONFIGURE_DNS_EMPTY_ZONE,
            MigrationStep::CONFIGURE_DNS_DEFAULT_ZONE,
            MigrationStep::CONFIGURE_DNS_ZONE_PROMOTION,
            MigrationStep::NAMESERVER_SET_DEFAULT,
            MigrationStep::NAMESERVER_SET_CURRENT,
            MigrationStep::ENABLE_DNSSEC,
            MigrationStep::HOSTING_MIGRATION,
        ];

        return array_values(array_uintersect($defaultOrder, $migrationSteps, fn ($a, $b) => $a->value <=> $b->value));
    }
}
