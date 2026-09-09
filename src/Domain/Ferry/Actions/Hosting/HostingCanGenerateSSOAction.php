<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Actions\Hosting;

use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Ferry\Dto\Hosting\HostingMigrationPayload;
use Waterfront\Domain\Ferry\Exceptions\HostingSSONotResolvableException;
use Waterfront\Domain\Hosting\Actions\GetSsoUrlAction;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;

class HostingCanGenerateSSOAction
{
    public function __construct(
        private readonly GetSsoUrlAction $getSsoUrlAction,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(
        Subscription $subscription,
        MigratedCustomer $migratedCustomer,
        HostingMigrationPayload $payload,
        Server $server,
        string $jobUuid,
    ): string|bool {
        $username = $payload->hostingDetails->getUsername();

        $this->logger->debug(
            'Attempting to fetch SSO from backend server',
            [
                LoggingContextKeys::QUEUE_JOB_ID => $jobUuid,
                LoggingContextKeys::SERVER_ID => $server->id,
                LoggingContextKeys::SERVER_HOSTNAME => $server->hostname,
                LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $migratedCustomer->reference_customer_number,
                LoggingContextKeys::META => [
                    'username' => $username,
                ],
            ]
        );

        try {
            $ssoUrl = $this->getSsoUrlAction->execute($server, $username, '127.0.0.1');
        } catch (Throwable $exception) {
            throw new HostingSSONotResolvableException($payload, $server, $subscription, $exception);
        }

        return $ssoUrl;
    }
}
