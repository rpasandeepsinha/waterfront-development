<?php

declare(strict_types=1);

namespace Waterfront\Domain\Backup\DTO;

class BackupUsage
{
    public function __construct(
        public readonly float $cloudStorageGbUsed,
        public readonly ?float $cloudStorageGbTotal
    ) {
    }
}
