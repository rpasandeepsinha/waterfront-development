<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Dto\ManualMigration;

readonly class ValidatedHosting
{
    /**
     * @param array<string, string>                $errors
     * @param array<string, array<string, string>> $options
     */
    public function __construct(
        public array $errors,
        public array $options,
    ) {
    }
}
