<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Microsoft365\Exceptions;

use Waterfront\Domain\Provision\Enums\ProvisionProvider;

class UnknownMicrosoft365ProviderException extends Microsoft365Exception
{
    public function __construct(ProvisionProvider $provider)
    {
        parent::__construct(message: sprintf(
            "Can't resolve Microsoft365 service from unknown provider [%s]",
            $provider->value,
        ));
    }
}
