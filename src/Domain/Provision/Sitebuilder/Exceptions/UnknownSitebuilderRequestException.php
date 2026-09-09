<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Sitebuilder\Exceptions;

use Throwable;
use Waterfront\Domain\Provision\Exceptions\ProvisionException;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;

class UnknownSitebuilderRequestException extends ProvisionException
{
    public function __construct(
        ProvisionRequestInterface $request,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: sprintf('No implementation found in sitebuilder service for request [%s]', $request::class),
            previous: $previous,
        );
    }
}
