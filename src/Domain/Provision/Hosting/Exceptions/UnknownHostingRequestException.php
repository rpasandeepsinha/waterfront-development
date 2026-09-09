<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Hosting\Exceptions;

use Waterfront\Domain\Provision\Exceptions\ProvisionException;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;

class UnknownHostingRequestException extends ProvisionException
{
    public function __construct(ProvisionRequestInterface $request)
    {
        $message = sprintf('No implementation found in hosting service for request [%s]', $request::class);
        parent::__construct(
            $message
        );
    }
}
