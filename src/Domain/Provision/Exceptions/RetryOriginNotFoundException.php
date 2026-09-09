<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Exceptions;

use Ramsey\Uuid\UuidInterface;
use Throwable;

class RetryOriginNotFoundException extends ProvisionException
{
    public function __construct(
        public readonly UuidInterface $requestUuid,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: sprintf(
                'Provision request [%s] could not be found.',
                $requestUuid->toString(),
            ),
            previous: $previous,
        );
    }
}
