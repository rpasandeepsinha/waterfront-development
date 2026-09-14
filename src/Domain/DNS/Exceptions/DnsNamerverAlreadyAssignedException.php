<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Exceptions;

use Exception;

class DnsNamerverAlreadyAssignedException extends Exception
{
    public function __construct(int $dnsDeploymentId, string $domain)
    {
        parent::__construct(
            sprintf(
                'Name servers have already been assigned for DNS deployment id :%d with domain %s',
                $dnsDeploymentId,
                $domain,
            ),
        );
    }
}
