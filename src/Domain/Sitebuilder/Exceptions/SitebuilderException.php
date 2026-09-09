<?php

declare(strict_types=1);

namespace Waterfront\Domain\Sitebuilder\Exceptions;

use RuntimeException;
use Throwable;

class SitebuilderException extends RuntimeException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        public ?string $domain = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
