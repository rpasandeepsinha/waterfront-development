<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Actions\Hosting;

use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Domain\Ferry\Exceptions\HostingPackageUnableToFetchException;
use Waterfront\Domain\Hosting\Interfaces\Hosting\HostingOfferingInterface;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Support\Enums\LoggingContextKeys;

class HostingPackageFetchAction
{
    public function __construct(
        private readonly HostingService $hostingService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws HostingPackageUnableToFetchException
     */
    public function execute(
        string $slug,
        string $migratedCustomerReference,
        Server $server,
        string $jobUuid
    ): HostingOfferingInterface {
        try {
            return $this->hostingService->getPackageOnServerAsDto($server, $slug);
        } catch (Throwable $exception) {
            $this->logger->debug(
                'Attempting to fetch package from remote backend failed',
                [
                    LoggingContextKeys::QUEUE_JOB_ID => $jobUuid,
                    LoggingContextKeys::SERVER_ID => $server->id,
                    LoggingContextKeys::SERVER_HOSTNAME => $server->hostname,
                    LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $migratedCustomerReference,
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::META => [
                        'slug' => $slug,
                    ],
                ]
            );

            throw new HostingPackageUnableToFetchException(
                message: $exception->getMessage(),
                code: $exception->getCode(),
                previous: $exception
            );
        }
    }
}
