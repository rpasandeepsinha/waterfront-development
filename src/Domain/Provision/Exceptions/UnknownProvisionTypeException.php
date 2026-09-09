<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Exceptions;

use Waterfront\Domain\Provision\Enums\ProvisionType;

class UnknownProvisionTypeException extends ProvisionException
{
    public function __construct(ProvisionType $type)
    {
        parent::__construct(message: sprintf("Can't resolve service from unknown type [%s]", $type->value));
    }
}
