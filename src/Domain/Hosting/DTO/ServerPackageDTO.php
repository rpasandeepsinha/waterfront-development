<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\DTO;

/**
 * @phpstan-type ServerPackageError array{message: string, code: int|string}
 */
readonly class ServerPackageDTO
{
    /**
     * @param array<string, mixed>           $details
     * @param array<int, ServerPackageError> $errors
     */
    public function __construct(
        public array $details,
        public array $errors,
    ) {
    }
}
