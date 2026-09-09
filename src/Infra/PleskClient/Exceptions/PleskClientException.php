<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;
use Waterfront\Support\Enums\LoggingContextKeys;

class PleskClientException extends Exception
{
    public static function serverSsoException(): PleskClientException
    {
        return new PleskClientException('You cannot login with sso into a Plesk server.');
    }

    public static function pleskApiException(int $errorCode, string $ErrorMessage): PleskClientException
    {
        return new PleskClientException(
            sprintf(
                '[Error code : %d]:: Api message : %s',
                $errorCode,
                $ErrorMessage,
            ),
            $errorCode
        );
    }

    public static function noPleskClientSessionTokenFound(string $username, string $ipAddress, string $statusMessage, int $responseCode): self
    {
        $message = sprintf(
            'There was no session token retrieved for the username : %s coming from ip address : %s.',
            $username,
            $ipAddress
        );

        Log::info(
            $message,
            [
                LoggingContextKeys::META => [
                    'result' => sprintf(
                        'Received the status message %s with the response code %s',
                        $statusMessage,
                        $responseCode
                    ),
                ],
            ]
        );

        return new PleskClientException(
            $message,
        );
    }

    public static function installingCertificateFailed(string $domain, int $code = 0): self
    {
        return new PleskClientException(
            sprintf(
                'The certificate installation failed for domain: %s.',
                $domain
            ),
            $code,
        );
    }

    public static function selectCertificateFailed(string $domain, int $code = 0): self
    {
        return new PleskClientException(
            sprintf(
                'Can not select the certificate for domain: %s.',
                $domain
            ),
            $code,
        );
    }

    public static function missingApiUrl(): self
    {
        return new PleskClientException('The API url is missing.');
    }

    public static function invalidApiUrl(string $apiUrl): self
    {
        return new PleskClientException(
            sprintf(
                'The API url %s is invalid.',
                $apiUrl
            ),
        );
    }

    public static function InvalidArgumentException(string $argument): self
    {
        return new PleskClientException(
            sprintf(
                '%s',
                $argument
            ),
        );
    }

    public static function ServicePlanNotExistsException(string $servicePlanName): self
    {
        return new PleskClientException(
            sprintf(
                'Service plan %s was not found on the given plesk server',
                $servicePlanName
            ),
        );
    }

    public static function unSupportedMailboxTypeException(string $mailName): self
    {
        return new PleskClientException(
            sprintf(
                'Technical downgrade cannot be performed. Mailname %s mailbox type does not match forwarding type',
                $mailName
            ),
        );
    }
}
