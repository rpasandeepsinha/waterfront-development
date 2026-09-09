<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Sitebuilder\Exceptions;

use Throwable;

class BasekitAddPackageException extends BasekitException
{
    public function __construct(
        public readonly int $packageReference,
        public readonly int $userReference,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: sprintf('Failed to add package %d for user %d', $packageReference, $userReference),
            previous: $previous,
        );
    }
}
