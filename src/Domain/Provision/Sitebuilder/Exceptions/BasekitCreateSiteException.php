<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Sitebuilder\Exceptions;

use Throwable;

class BasekitCreateSiteException extends BasekitException
{
    public function __construct(
        public readonly string $domain,
        public readonly int $userReference,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: sprintf('Failed to create site "%s" for user %d', $domain, $userReference),
            previous: $previous,
        );
    }
}
