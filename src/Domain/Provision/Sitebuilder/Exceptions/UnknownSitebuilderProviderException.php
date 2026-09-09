<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Sitebuilder\Exceptions;

use Throwable;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Exceptions\ProvisionException;

class UnknownSitebuilderProviderException extends ProvisionException
{
    public function __construct(
        ProvisionProvider $provider,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: sprintf("Can't resolve sitebuilder service from unknown provider [%s]", $provider->value),
            previous: $previous,
        );
    }
}
