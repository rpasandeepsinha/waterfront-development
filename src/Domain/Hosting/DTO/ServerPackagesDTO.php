<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\DTO;

/**
 * @phpstan-type ServerPackagesError array{message: string, code: int|string}
 */
readonly class ServerPackagesDTO
{
    /**
     * @param array<int, string>              $packages
     * @param array<int, ServerPackagesError> $errors
     */
    public function __construct(
        public array $packages,
        public array $errors,
    ) {
    }
}
