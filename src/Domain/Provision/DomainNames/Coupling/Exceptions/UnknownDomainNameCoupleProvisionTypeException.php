<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\DomainNames\Coupling\Exceptions;

use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Exceptions\ProvisionException;

class UnknownDomainNameCoupleProvisionTypeException extends ProvisionException
{
    public function __construct(ProvisionType $type)
    {
        parent::__construct(message: sprintf("Can't couple domain name to unknown service type [%s]", $type->value));
    }
}
