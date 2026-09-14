<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Redirects\Exceptions;

use Ramsey\Uuid\UuidInterface;
use Throwable;

class ListRedirectsException extends RedirectException
{
    public function __construct(
        public readonly UuidInterface $contextUuid,
        ?string $message = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: $message ?? sprintf(
                'List redirects could not be generated for context_uuid %s',
                $contextUuid->toString(),
            ),
            previous: $previous,
        );
    }
}
