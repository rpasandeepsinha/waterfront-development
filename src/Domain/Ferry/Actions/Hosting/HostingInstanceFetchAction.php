<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Actions\Hosting;

use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Ferry\Dto\Hosting\DirectAdminHostingDetails;
use Waterfront\Domain\Ferry\Dto\Hosting\HostingMigrationPayload;
use Waterfront\Domain\Ferry\Dto\Hosting\PleskHostingDetails;
use Waterfront\Domain\Ferry\Exceptions\HostingDetailsNotSupportedException;
use Waterfront\Domain\Ferry\Exceptions\HostingInstanceNotFoundException;
use Waterfront\Domain\Hosting\Interfaces\Hosting\SiteConfigInterface;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;

class HostingInstanceFetchAction
{
    public function __construct(
        private readonly HostingService $hostingService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(
        Subscription $subscription,
        MigratedCustomer $migratedCustomer,
        HostingMigrationPayload $payload,
        Server $server,
        string $jobUuid,
    ): SiteConfigInterface {
        $username = $this->getIdentifier($payload);

        $this->logger->debug(
            'Attempting to find username on remote server',
            [
                LoggingContextKeys::QUEUE_JOB_ID => $jobUuid,
                LoggingContextKeys::SERVER_ID => $server->id,
                LoggingContextKeys::SERVER_HOSTNAME => $server->hostname,
                LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $migratedCustomer->reference_customer_number,
                LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                LoggingContextKeys::META => [
                    'username' => $username,
                    'payload.driver' => $payload->driver,
                ],
            ]
        );

        try {
            $config = $this->hostingService->getUserConfigAsDto($payload->driver, $username, $server);
        } catch (Throwable $exception) {
            throw new HostingInstanceNotFoundException($payload, $server, $subscription, $exception);
        }

        return $config;
    }

    private function getIdentifier(HostingMigrationPayload $payload): string
    {
        $hostingDetails = $payload->hostingDetails;

        return match (true) {
            $hostingDetails instanceof DirectAdminHostingDetails => $hostingDetails->getUsername(),
            $hostingDetails instanceof PleskHostingDetails => $hostingDetails->getUsername(),
            default => throw new HostingDetailsNotSupportedException($hostingDetails),
        };
    }
}
