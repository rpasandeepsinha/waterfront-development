<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Exceptions;

use Exception;
use Throwable;

class CloudstackNotFoundException extends Exception
{
    public static function domainIdNotFound(string $subscriptionUuid): self
    {
        return new CloudstackNotFoundException(
            sprintf(
                'The ManagerDomainDeployment with UUID "%s" doesn\'t have an external domain ID.',
                $subscriptionUuid
            )
        );
    }

    public static function environmentNotFound(int $osProductId, int $productId): self
    {
        return new CloudstackNotFoundException(
            sprintf(
                'Could not find compatible environment for OS template UUID "%s" and product ID "%d".',
                $osProductId,
                $productId
            )
        );
    }

    public static function vmNotFound(string $uuid, ?Throwable $trace = null): self
    {
        return new CloudstackNotFoundException(
            message: sprintf(
                'VM with UUID "%s" not found.',
                $uuid
            ),
            previous: $trace
        );
    }
}
