<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\DomainNames\Coupling\Exceptions;

use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Exceptions\ProvisionException;

class UnknownDomainNameCoupleProviderException extends ProvisionException
{
    public function __construct(ProvisionProvider $provider)
    {
        parent::__construct(message: sprintf(
            "Can't resolve domain name couple service from unknown provider [%s]",
            $provider->value,
        ));
    }
}
