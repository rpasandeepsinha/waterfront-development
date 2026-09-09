<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Dto\ManualMigration;

use Waterfront\Domain\Ferry\Enums\ManualMigrationOption;

readonly class MigrationOption
{
    public function __construct(
        public ManualMigrationOption $title,
        public string $value,
        public bool $selected,
        public string $description,
        public bool $optionAvailable,
    ) {
    }
}
