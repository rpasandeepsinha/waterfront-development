<?php

declare(strict_types=1);

namespace Waterfront\Infra\CaddyClient\Exceptions;

use Throwable;

class InvalidRedirectSchemeException extends CaddyException
{
    public function __construct(string $targetUrl, ?Throwable $previous = null)
    {
        parent::__construct(
            message: sprintf(
                'Frame redirects only support http and https URLs, got "%s".',
                $targetUrl,
            ),
            previous: $previous,
        );
    }
}
