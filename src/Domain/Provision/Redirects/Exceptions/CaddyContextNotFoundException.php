<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Redirects\Exceptions;

use Ramsey\Uuid\UuidInterface;
use Throwable;

class CaddyContextNotFoundException extends CaddyException
{
    public function __construct(
        public readonly UuidInterface $contextUuid,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: sprintf('Caddy context not found for context_uuid %s', $contextUuid->toString()),
            previous: $previous,
        );
    }
}
