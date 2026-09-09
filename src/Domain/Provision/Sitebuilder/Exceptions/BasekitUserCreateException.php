<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Sitebuilder\Exceptions;

use Ramsey\Uuid\UuidInterface;
use Throwable;

class BasekitUserCreateException extends BasekitException
{
    public function __construct(
        public readonly UuidInterface $context,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: sprintf('Failed to create sitebuilder user for context %s', $context->toString()),
            previous: $previous,
        );
    }
}
