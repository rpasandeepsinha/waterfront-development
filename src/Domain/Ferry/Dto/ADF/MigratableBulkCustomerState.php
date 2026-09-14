<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Dto\ADF;

use Waterfront\Domain\Ferry\Enums\MigrationStep;

readonly class MigratableBulkCustomerState implements MigrationTypeADFPayload
{
    public function __construct(
        public MigrationStep $migrationStep,
        public string $referenceName,
        public ?int $waterfrontCustomerId,
    ) {
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        return [
            'step' => $this->migrationStep->value,
            'reference_name' => $this->referenceName,
            'waterfront_customer_id' => $this->waterfrontCustomerId,
        ];
    }
}
