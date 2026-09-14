<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Exceptions;

use Exception;
use Throwable;
use Waterfront\Domain\Ferry\Dto\Hosting\HostingMigrationPayload;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class HostingSSONotResolvableException extends Exception
{
    public function __construct(
        HostingMigrationPayload $payload,
        Server $server,
        Subscription $subscription,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf(
                'Hosting SSO could not be generated on server %s for subscription %d (payload: %s)',
                $server->hostname,
                $subscription->id,
                json_encode($payload->toArray(), JSON_THROW_ON_ERROR),
            ),
            0,
            $previous,
        );
    }
}
