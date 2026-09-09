<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Sitebuilder\Exceptions;

use Throwable;

class BasekitGetPackagesForUserException extends BasekitException
{
    public function __construct(
        public readonly int $userReference,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: sprintf('Failed to retrieve user packages for user %d', $userReference),
            previous: $previous,
        );
    }
}
