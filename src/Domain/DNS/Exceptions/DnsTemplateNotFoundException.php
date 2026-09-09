<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Exceptions;

use RuntimeException;

/**
 * This exception should be thrown if a dns template could not be found.
 */
class DnsTemplateNotFoundException extends RuntimeException
{
    public function __construct(string $templateName)
    {
        parent::__construct('DNS template ' . $templateName . ' not found!');
    }
}
