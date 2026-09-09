<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Exceptions;

use Exception;

class DomainWithoutDnsDeploymentException extends Exception
{
    public function __construct(string $domain)
    {
        parent::__construct(sprintf('Domain [%s] is missing a DNS deployment', $domain));
    }
}
