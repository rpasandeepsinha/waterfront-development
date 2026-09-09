<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Actions\Hosting;

use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Ferry\Dto\Hosting\HostingMigrationPayload;
use Waterfront\Domain\Ferry\Exceptions\HostingUnableToSetDefaultDomainException;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Support\Enums\LoggingContextKeys;

readonly class HostingDeploymentSetDefaultDomainAction
{
    public function __construct(
        private HostingService $hostingService,
        private LoggerInterface $logger,
    ) {
    }

    public function execute(
        HostingDeployment $hostingDeployment,
        MigratedCustomer $migratedCustomer,
        HostingMigrationPayload $payload,
        Server $server,
        string $jobUuid,
    ): void {
        $this->logger->debug(
            'Attempting to find default hosting domain for subscription from remote backend',
            [
                LoggingContextKeys::QUEUE_JOB_ID => $jobUuid,
                LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $migratedCustomer->reference_customer_number,
                LoggingContextKeys::SUBSCRIPTION_UUID => $hostingDeployment->subscription_uuid,
                LoggingContextKeys::REQUEST_DATA => (string) json_encode($payload->toArray()),
                LoggingContextKeys::SERVER_ID => $server->id,
                LoggingContextKeys::SERVER_HOSTNAME => $server->hostname,
            ]
        );

        $subscription = $hostingDeployment->subscription;

        try {
            $defaultDomain = $this->hostingService->getDefaultDomain(
                ProviderSlug::from($payload->driver),
                $payload->hostingDetails->getUsername(),
                $server
            );

            if ($defaultDomain === null || $defaultDomain === '') {
                // Fallback if both the payload does NOT contain a domain name
                // and there is NO DOMAIN on the hosting package
                $defaultDomain = sprintf(
                    '%s.%s',
                    $payload->hostingDetails->getUsername(),
                    $server->hostname
                );
            }

            $this->logger->debug(
                'Setting default hosting domain on subscription',
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $hostingDeployment->subscription_uuid,
                    LoggingContextKeys::DOMAIN_NAME => $defaultDomain,
                    LoggingContextKeys::REQUEST_DATA => (string) json_encode($payload->toArray()),
                    LoggingContextKeys::SERVER_ID => $server->id,
                    LoggingContextKeys::SERVER_HOSTNAME => $server->hostname,
                ]
            );

            $subscription->domain = $defaultDomain;
            $subscription->save();
        } catch (Throwable $exception) {
            throw new HostingUnableToSetDefaultDomainException(
                $subscription,
                $payload,
                $server,
                $exception
            );
        }
    }
}
