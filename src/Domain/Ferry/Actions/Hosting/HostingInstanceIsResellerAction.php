<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Actions\Hosting;

use Psr\Log\LoggerInterface;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Ferry\Dto\Hosting\HostingMigrationPayload;
use Waterfront\Domain\Ferry\Exceptions\HostingMigrationIsResellerException;
use Waterfront\Domain\Hosting\Interfaces\Hosting\SiteConfigInterface;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Support\Enums\LoggingContextKeys;

class HostingInstanceIsResellerAction
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(
        HostingMigrationPayload $payload,
        MigratedCustomer $migratedCustomer,
        Server $server,
        SiteConfigInterface $siteConfig,
        string $jobUuid,
    ): void {
        if ($siteConfig->isReseller()) {
            $username = $payload->hostingDetails->getUsername();
            $driver = $payload->driver;
            $hostname = $server->hostname;

            $this->logger->debug(
                'Provided remote instance is a reseller',
                [
                    LoggingContextKeys::QUEUE_JOB_ID => $jobUuid,
                    LoggingContextKeys::SERVER_ID => $server->id,
                    LoggingContextKeys::SERVER_HOSTNAME => $server->hostname,
                    LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $migratedCustomer->reference_customer_number,
                    LoggingContextKeys::META => [
                        'username' => $username,
                        'payload.driver' => $driver,
                    ],
                ],
            );

            throw new HostingMigrationIsResellerException(
                username: $username,
                driver: $driver,
                hostname: $hostname,
            );
        }
    }
}
