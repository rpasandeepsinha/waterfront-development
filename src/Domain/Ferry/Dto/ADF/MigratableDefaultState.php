<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Dto\ADF;

use Waterfront\Domain\Ferry\Enums\MigrationStep;

readonly class MigratableDefaultState implements MigrationTypeADFPayload
{
    public function __construct(
        public MigrationStep $migrationStep,
        public string $referenceName,
        public string|null $domain,
        public string $migrationSubscriptionReferenceId,
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
        ];
    }
}
