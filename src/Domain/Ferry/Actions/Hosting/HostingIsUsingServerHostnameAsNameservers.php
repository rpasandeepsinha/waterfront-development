<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Actions\Hosting;

use ErrorException;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Ferry\Dto\Hosting\HostingMigrationPayload;
use Waterfront\Domain\Hosting\Interfaces\Hosting\SiteConfigInterface;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Support\Enums\LoggingContextKeys;

class HostingIsUsingServerHostnameAsNameservers
{
    public function __construct(
        private readonly HostingService $hostingService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(
        HostingMigrationPayload $payload,
        MigratedCustomer $migratedCustomer,
        Server $server,
        SiteConfigInterface $siteConfig,
        string $jobUuid,
    ): bool {
        $username = $payload->hostingDetails->getUsername();

        $this->logger->debug(
            'Checking if hosting nameservers are the same as the server hostname',
            [
                LoggingContextKeys::QUEUE_JOB_ID => $jobUuid,
                LoggingContextKeys::SERVER_ID => $server->id,
                LoggingContextKeys::SERVER_HOSTNAME => $server->hostname,
                LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $migratedCustomer->reference_customer_number,
                LoggingContextKeys::META => [
                    'username' => $username,
                ],
            ],
        );

        try {
            return $this->hostingService->isUsingHostingServerAsNameserver(
                $payload->driver,
                $server->ipv4,
                $server->ipv6,
                $siteConfig,
            );
        } catch (ErrorException $exception) {
            $this->logger->error(
                'Failed to verify that the hosting server is being used as the nameserver.',
                [
                    LoggingContextKeys::QUEUE_JOB_ID => $jobUuid,
                    LoggingContextKeys::REQUEST_DATA => (string) json_encode($payload->toArray()),
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $migratedCustomer->reference_customer_number,
                ],
            );

            return false;
        }
    }
}
