<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Redirects\Exceptions;

use Throwable;
use Waterfront\Domain\Provision\Exceptions\ProvisionException;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;

class UnknownRedirectRequestException extends ProvisionException
{
    public function __construct(
        ProvisionRequestInterface $request,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: sprintf('No implementation found in redirect service for request [%s]', $request::class),
            previous: $previous,
        );
    }
}
