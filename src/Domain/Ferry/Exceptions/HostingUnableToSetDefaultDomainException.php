<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Exceptions;

use Exception;
use Throwable;
use Waterfront\Domain\Ferry\Dto\Hosting\HostingMigrationPayload;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class HostingUnableToSetDefaultDomainException extends Exception
{
    public function __construct(
        Subscription $subscription,
        HostingMigrationPayload $payload,
        Server $server,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf(
                'Hosting default domain could not be set using server %s for subscription %d (payload: %s)',
                $server->hostname,
                $subscription->id,
                json_encode($payload->toArray(), JSON_THROW_ON_ERROR),
            ),
            0,
            $previous,
        );
    }
}
