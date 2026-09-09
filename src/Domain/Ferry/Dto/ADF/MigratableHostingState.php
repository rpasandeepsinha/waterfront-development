<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Dto\ADF;

use Waterfront\Domain\Ferry\Enums\MigrationStep;

readonly class MigratableHostingState implements MigrationTypeADFPayload
{
    public function __construct(
        public MigrationStep $migrationStep,
        public string $referenceName,
        public string|null $domain,
        public string $migrationSubscriptionReferenceId,
        public string|null $hostname,
        public string $username,
        public string $driver
    ) {
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        return [
            'step' => $this->migrationStep->value,
            'domain' => $this->domain,
            'reference_subscription_id' => $this->migrationSubscriptionReferenceId,
            'reference_name' => $this->referenceName,
            'hostname' => $this->hostname,
            'username' => $this->username,
            'driver' => $this->driver,
        ];
    }
}
