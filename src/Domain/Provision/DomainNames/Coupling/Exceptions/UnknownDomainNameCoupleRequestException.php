<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\DomainNames\Coupling\Exceptions;

use Waterfront\Domain\Provision\Exceptions\ProvisionException;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;

class UnknownDomainNameCoupleRequestException extends ProvisionException
{
    public function __construct(ProvisionRequestInterface $request)
    {
        parent::__construct(message: sprintf('No implementation found in domain name couple service for request [%s]', $request::class));
    }
}
