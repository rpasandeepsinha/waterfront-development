<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Actions\Hosting;

use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Ferry\Dto\Hosting\HostingMigrationPayload;
use Waterfront\Domain\Ferry\Dto\Hosting\SitebuilderBaseKitDetails;
use Waterfront\Domain\Ferry\Exceptions\HostingDetailsNotSupportedException;
use Waterfront\Domain\Ferry\Exceptions\HostingInstanceNotFoundException;
use Waterfront\Domain\Provision\Sitebuilder\Results\BasekitUserResult;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Sitebuilder\DTO\SitebuilderUserInterface;
use Waterfront\Domain\Sitebuilder\Services\SitebuilderProxy;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;

class SitebuilderUserFetchAction
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly SitebuilderProxy $sitebuilderProxy,
    ) {
    }

    public function execute(
        Subscription $subscription,
        MigratedCustomer $migratedCustomer,
        HostingMigrationPayload $payload,
        Server $server,
        string $jobUuid,
    ): BasekitUserResult|SitebuilderUserInterface {
        $userId = $this->getIdentifier($payload);

        $this->logger->debug(
            'Attempting to find user on remote server',
            [
                LoggingContextKeys::QUEUE_JOB_ID => $jobUuid,
                LoggingContextKeys::SERVER_ID => $server->id,
                LoggingContextKeys::SERVER_HOSTNAME => $server->hostname,
                LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $migratedCustomer->reference_customer_number,
                LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                LoggingContextKeys::META => [
                    'userId' => $userId,
                    'payload.driver' => $payload->driver,
                ],
            ],
        );

        try {
            return $this->sitebuilderProxy->getSitebuilderUser(
                userRef: $userId,
                subscription: $subscription,
                payload: $payload,
                server: $server,
            );
        } catch (Throwable $exception) {
            throw new HostingInstanceNotFoundException($payload, $server, $subscription, $exception);
        }
    }

    private function getIdentifier(HostingMigrationPayload $payload): int
    {
        $hostingDetails = $payload->hostingDetails;

        return match (true) {
            $hostingDetails instanceof SitebuilderBaseKitDetails => $hostingDetails->basekitUserRef,
            default => throw new HostingDetailsNotSupportedException($hostingDetails),
        };
    }
}
