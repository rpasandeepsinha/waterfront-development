<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Sitebuilder\Exceptions;

use Ramsey\Uuid\UuidInterface;
use Throwable;

class BasekitSiteRefNotFoundException extends BasekitException
{
    public function __construct(
        public readonly UuidInterface $deploymentUuid,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: sprintf('Basekit site_ref not found for deployment %s', $deploymentUuid),
            previous: $previous,
        );
    }
}
