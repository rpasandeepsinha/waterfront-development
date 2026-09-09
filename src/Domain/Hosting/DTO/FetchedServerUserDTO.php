<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\DTO;

/**
 * Any part may be missing: a failure to reach one endpoint does not hide what
 * the others returned. Whatever failed is described in $errors.
 *
 * @phpstan-type FetchedServerUserError array{message: string, previous: string|null, code: int|string}
 * @phpstan-type FetchedServerUserMailForward array{source: string, destinations: array<int, string>}
 */
readonly class FetchedServerUserDTO
{
    /**
     * @param array<string, mixed>                     $userData
     * @param array<int, FetchedServerUserMailForward> $mailForwards
     * @param array<int, string>                       $mailUsers
     * @param array<int, FetchedServerUserError>       $errors
     */
    public function __construct(
        public array $userData,
        public ?string $ssoUrl,
        public array $mailForwards,
        public array $mailUsers,
        public array $errors,
    ) {
    }
}
