<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Redirects\Exceptions;

use Ramsey\Uuid\UuidInterface;
use Throwable;

class CaddyIdNotFoundException extends CaddyException
{
    public function __construct(
        public readonly UuidInterface $deploymentUuid,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: sprintf('caddy_id not found on deployment %s', $deploymentUuid),
            previous: $previous,
        );
    }
}
