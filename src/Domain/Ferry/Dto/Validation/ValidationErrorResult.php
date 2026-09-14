<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Dto\Validation;

use Waterfront\Domain\Ferry\Enums\MigrationValidation;

readonly class ValidationErrorResult implements ValidationResultInterface
{
    /**
     * @param array<string, array<int, string>> $messages
     */
    public function __construct(
        public MigrationValidation $id,
        public array $messages,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id->value,
            'message' => $this->messages,
        ];
    }
}
