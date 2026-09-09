<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\DomainNames\Coupling\Exceptions;

use Waterfront\Domain\Provision\DomainNames\Coupling\Interfaces\DomainNameCoupleInterface;
use Waterfront\Domain\Provision\Exceptions\ProvisionException;
use Waterfront\Domain\Provision\Interfaces\ProvisionServiceInterface;

class ServiceNotInstanceOfDomainNameCoupleInterfaceException extends ProvisionException
{
    public function __construct(ProvisionServiceInterface $service)
    {
        parent::__construct(sprintf(
            "The service [%s] can't couple domain names, it should implement the [%s]",
            $service::class,
            DomainNameCoupleInterface::class
        ));
    }
}
