<?php

declare(strict_types=1);

namespace Waterfront\Infra\PowerDnsClient\Exceptions;

use Exception;
use Throwable;

class PdnsNotSOARecordException extends Exception
{
    public function __construct(string $recordContent, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(
            sprintf(
                'Expected a SOA content but got: %s',
                $recordContent,
            ),
            $code,
            $previous,
        );
    }
}
