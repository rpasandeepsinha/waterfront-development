<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Exceptions;

use RuntimeException;

class DnsTemplateNotConvertibleException extends RuntimeException
{
    public function __construct(string $type, string $name, string $reason)
    {
        parent::__construct("DNS customer template record with name $name not convertible to: $type because of: $reason");
    }
}
