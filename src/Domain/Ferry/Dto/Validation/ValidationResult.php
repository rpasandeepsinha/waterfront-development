<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Dto\Validation;

use Waterfront\Domain\Ferry\Enums\MigrationValidation;

readonly class ValidationResult implements ValidationResultInterface
{
    /**
     * @param array<mixed> $data
     */
    public function __construct(
        public MigrationValidation $id,
        public string $message,
        public string|null $referenceSubscriptionId = null,
        public array $data = [],
    ) {
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        $base = [
            'id' => $this->id->value,
            'message' => $this->message,
        ];

        if ($this->referenceSubscriptionId !== null) {
            $base['reference_subscription_id'] = $this->referenceSubscriptionId;
        }

        if ($this->data !== []) {
            $base['data'] = $this->data;
        }

        return $base;
    }
}
