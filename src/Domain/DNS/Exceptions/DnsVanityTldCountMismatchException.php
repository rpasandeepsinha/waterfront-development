<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Exceptions;

use Exception;

class DnsVanityTldCountMismatchException extends Exception
{
    public function __construct(int $tldsGiven, int $tldsRequired)
    {
        parent::__construct(sprintf(
            'There are %d vanitytlds required - %d given',
            $tldsRequired,
            $tldsGiven,
        ));
    }
}
