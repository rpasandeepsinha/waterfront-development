<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Dto\ManualMigration;

readonly class ValidatedDomain
{
    /**
     * @param array<string>                         $nameservers
     * @param array<string, string>                 $errors
     * @param array<string, array<MigrationOption>> $options
     */
    public function __construct(
        public array $nameservers,
        public bool $zoneInPowerDns,
        public bool $zoneIsNative,
        public bool $dnsSecEnabled,
        public array $errors,
        public array $options,
    ) {
    }
}
