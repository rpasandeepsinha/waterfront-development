<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Dto\ADF;

use Waterfront\Domain\Ferry\Enums\MigrationStep;

readonly class MigratableBulkSubscriptionState implements MigrationTypeADFPayload
{
    /**
     * @param array<int<0, max>, array{waterfront_subscription_id: int, reference_subscription_id: string|null}> $subscriptions
     */
    public function __construct(
        public MigrationStep $migrationStep,
        public string $referenceName,
        public int|null $waterfrontCustomerId,
        public array $subscriptions,
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
            'subscriptions' => $this->subscriptions,
        ];
    }
}
