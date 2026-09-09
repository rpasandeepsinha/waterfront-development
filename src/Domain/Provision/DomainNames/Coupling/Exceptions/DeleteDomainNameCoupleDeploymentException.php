<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\DomainNames\Coupling\Exceptions;

use Throwable;
use Waterfront\Domain\Provision\DomainNames\Coupling\Models\DomainNameCoupleDeployment;
use Waterfront\Domain\Provision\Exceptions\ProvisionException;

class DeleteDomainNameCoupleDeploymentException extends ProvisionException
{
    public function __construct(DomainNameCoupleDeployment $deployment, ?Throwable $previous = null)
    {
        parent::__construct(
            message: sprintf(
                'Could not delete domain name deployment for domain %s with %s uuid %s',
                $deployment->domain,
                $deployment->couple_type->value,
                $deployment->uuid
            ),
            previous: $previous
        );
    }
}
