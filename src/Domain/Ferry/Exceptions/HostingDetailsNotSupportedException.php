<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Exceptions;

use Exception;
use Throwable;
use Waterfront\Domain\Ferry\Dto\Hosting\HostingDetailsInterface;

class HostingDetailsNotSupportedException extends Exception
{
    public function __construct(HostingDetailsInterface $hostingDetails, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(
            sprintf(
                'Hosting details "%s" not supported: %s',
                $hostingDetails::class,
                json_encode($hostingDetails->toArray(), JSON_THROW_ON_ERROR),
            ),
            $code,
            $previous,
        );
    }
}
