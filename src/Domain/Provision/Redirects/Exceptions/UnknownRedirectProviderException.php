<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Redirects\Exceptions;

use Throwable;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Exceptions\ProvisionException;

class UnknownRedirectProviderException extends ProvisionException
{
    public function __construct(
        ProvisionProvider $provider,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: sprintf("Can't resolve redirect service from unknown provider [%s]", $provider->value),
            previous: $previous,
        );
    }
}
