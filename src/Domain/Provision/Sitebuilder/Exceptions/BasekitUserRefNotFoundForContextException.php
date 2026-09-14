<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Sitebuilder\Exceptions;

use Ramsey\Uuid\UuidInterface;
use Throwable;

class BasekitUserRefNotFoundForContextException extends BasekitException
{
    public function __construct(
        UuidInterface $contextUuid,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: sprintf('No Basekit user_ref mapping found for context_uuid %s.', $contextUuid),
            previous: $previous,
        );
    }
}
