<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Exceptions;

use Exception;

class PleskLackOffResourceException extends Exception
{
    public function __construct(string $serviceplan, string $domain, string $pleskError)
    {
        $message = sprintf(
            'Serviceplan : %s | domain : %s | PleskError %s',
            $serviceplan,
            $domain,
            $pleskError,
        );

        parent::__construct($message);
    }
}
