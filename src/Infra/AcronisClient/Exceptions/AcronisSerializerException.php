<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\Exceptions;

use Throwable;

class AcronisSerializerException extends AcronisException
{
    /**
     * @param class-string $class
     */
    public function __construct(string $class, string $data, ?Throwable $previous = null)
    {
        parent::__construct(
            message: sprintf('Failed to deserialize Acronis response to class "%s", data: "%s"', $class, $data),
            previous: $previous,
        );
    }
}
