<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\DirectAdmin\Exceptions;

use Exception;
use Throwable;
use Waterfront\Domain\Servers\Models\Server;

class EmailForwardException extends Exception
{
    /**
     * @param array<mixed> $payload
     */
    public function __construct(
        Server $server,
        string $domain,
        string $identifier,
        array $payload = [],
        ?Throwable $previous = null
    ) {
        $message = sprintf(
            'Email forwards error on server with ID: {%d} for domain {%s} with identifier {%s} using the following payload in request: {%s}',
            $server->id,
            $domain,
            $identifier,
            json_encode($payload, JSON_THROW_ON_ERROR)
        );

        parent::__construct(message: $message, previous: $previous);
    }
}
