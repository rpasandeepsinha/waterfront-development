<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Sitebuilder\Exceptions;

use Throwable;

class BasekitDeletePackageForUserException extends BasekitException
{
    public function __construct(
        public readonly int $accountPackageReference,
        public readonly int $userReference,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: sprintf(
                'Failed to delete account package %d for user %d',
                $accountPackageReference,
                $userReference,
            ),
            previous: $previous,
        );
    }
}
