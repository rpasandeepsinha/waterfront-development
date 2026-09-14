<?php

declare(strict_types=1);

namespace Waterfront\Domain\ResellerHosting\Exceptions;

use Exception;

class ResellerHostingException extends Exception
{
    public static function noResellerHostingPackagesFound(int $userId): self
    {
        return new ResellerHostingException(
            sprintf(
                'There where no packages found for the user with id : %d.',
                $userId,
            ),
        );
    }

    public static function noDirectadminCompatibleServerFound(): self
    {
        return new ResellerHostingException(
            'No compatible server was found to deploy reseller hosting packages in directadmin!',
        );
    }

    public static function noDirectAdminUserNameForReseller(string $uuid): self
    {
        return new ResellerHostingException(
            sprintf(
                'Directadmin username was not found for subscription with uuid: %s',
                $uuid,
            ),
        );
    }

    public static function noDriverFound(string $slug): self
    {
        return new ResellerHostingException(
            sprintf(
                'There was no driver found for the provided slug in the reseller hosting deployment given slug: %s',
                $slug,
            ),
        );
    }

    public static function noDirectadminUsernameFound(string $username, string $command): self
    {
        return new ResellerHostingException(
            sprintf(
                'The username %s for directadmin was not found, can not execute the %s command',
                $username,
                $command,
            ),
            1,
        );
    }

    public static function noRelevantUsernameFound(string $uuid): self
    {
        return new ResellerHostingException(
            sprintf(
                'No username found in the database for reseller hosting deployment uuid: {%s}',
                $uuid,
            ),
        );
    }

    public static function directadminCommandFailed(string $command, int $code = 0, ?Exception $exception = null): self
    {
        return new ResellerHostingException(
            sprintf(
                'The Directadmin command %s has failed during execution ',
                $command,
            ),
            $code,
            $exception,
        );
    }

    public static function noCompatibleServerFound(string $driver): self
    {
        return new ResellerHostingException(
            sprintf(
                'There was not a compatible server provided for the driver %s',
                $driver,
            ),
        );
    }

    public static function noDomainSubscriptionFound(string $domain): self
    {
        return new ResellerHostingException(
            sprintf(
                'There was no subscription found for the provided domain: %s',
                $domain,
            ),
        );
    }
}
