<?php

declare(strict_types=1);

namespace Waterfront\Domain\Redirects\Exceptions;

use Exception;
use Throwable;

class DnsNeedsRootDomain extends Exception
{
    public function __construct(string $domain, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(
            message: "The domain [{$domain}] is not a root domain, we can only provision redirects on root domains.",
            code: $code,
            previous: $previous,
        );
    }
}
