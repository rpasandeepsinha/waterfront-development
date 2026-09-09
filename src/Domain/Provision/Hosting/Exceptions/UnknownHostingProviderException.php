<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Hosting\Exceptions;

use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Exceptions\ProvisionException;

class UnknownHostingProviderException extends ProvisionException
{
    public function __construct(ProvisionProvider $provider)
    {
        parent::__construct(message: sprintf("Can't resolve hosting service from unknown provider [%s]", $provider->value));
    }
}
