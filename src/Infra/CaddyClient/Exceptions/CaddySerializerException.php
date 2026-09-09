<?php

declare(strict_types=1);

namespace Waterfront\Infra\CaddyClient\Exceptions;

use Throwable;

class CaddySerializerException extends CaddyException
{
    /**
     * @param class-string $class
     */
    public function __construct(string $class, string $data, ?Throwable $previous = null)
    {
        parent::__construct(
            message: sprintf('Failed to deserialize Caddy response to class "%s", data: "%s"', $class, $data),
            previous: $previous
        );
    }
}
