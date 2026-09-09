<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Exceptions;

use Exception;

class AssignNameserversWithoutNameserversException extends Exception
{
    public function __construct(string $domain)
    {
        parent::__construct(
            sprintf('Cannot assign nameservers without nameservers on domain [%s]', $domain)
        );
    }
}
