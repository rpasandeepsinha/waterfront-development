<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Actions\Hosting;

use Psr\Log\LoggerInterface;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Ferry\Dto\Hosting\HostingMigrationPayload;
use Waterfront\Domain\Ferry\Dto\Hosting\SitebuilderBaseKitDetails;
use Waterfront\Domain\Ferry\Exceptions\HostingDetailsNotSupportedException;
use Waterfront\Domain\Provision\Sitebuilder\Results\BasekitSiteResult;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Sitebuilder\DTO\SitebuilderSiteInterface;
use Waterfront\Domain\Sitebuilder\Services\SitebuilderProxy;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;

class SitebuilderSiteFetchAction
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
    ): BasekitSiteResult|SitebuilderSiteInterface {
        $siteId = $this->getIdentifier($payload);

        $this->logger->debug(
            'Attempting to find sitebuilder site on remote server',
            [
                LoggingContextKeys::QUEUE_JOB_ID => $jobUuid,
                LoggingContextKeys::SERVER_ID => $server->id,
                LoggingContextKeys::SERVER_HOSTNAME => $server->hostname,
                LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $migratedCustomer->reference_customer_number,
                LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                LoggingContextKeys::META => [
                    'payload.site_id' => $siteId,
                    'payload.driver' => $payload->driver,
                ],
            ]
        );

        return $this->sitebuilderProxy->fetchSitebuilderSite(
            siteRef: $siteId,
            subscription: $subscription,
            payload: $payload,
            server: $server
        );
    }

    private function getIdentifier(HostingMigrationPayload $payload): int
    {
        $hostingDetails = $payload->hostingDetails;

        return match (true) {
            $hostingDetails instanceof SitebuilderBaseKitDetails => $hostingDetails->basekitSiteRef,
            default => throw new HostingDetailsNotSupportedException($hostingDetails),
        };
    }
}
