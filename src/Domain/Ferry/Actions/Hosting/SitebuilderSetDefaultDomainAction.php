<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Actions\Hosting;

use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Throwable;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Ferry\Dto\Hosting\HostingMigrationPayload;
use Waterfront\Domain\Ferry\Dto\Hosting\SitebuilderBaseKitDetails;
use Waterfront\Domain\Ferry\Exceptions\HostingDetailsNotSupportedException;
use Waterfront\Domain\Ferry\Exceptions\HostingInstanceNotFoundException;
use Waterfront\Domain\Ferry\Exceptions\HostingUnableToSetDefaultDomainException;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Sitebuilder\Requests\GetBasekitSiteByRefRequest;
use Waterfront\Domain\Provision\Sitebuilder\Results\BasekitSiteResult;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Sitebuilder\SitebuilderService;
use Waterfront\Support\Enums\LoggingContextKeys;

readonly class SitebuilderSetDefaultDomainAction
{
    public function __construct(
        private SitebuilderService $sitebuilderService,
        private LoggerInterface $logger,
        private ProvisionGateway $provisionGateway,
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
            'Attempting to find default sitebuilder domain for subscription from remote backend',
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
            $siteId = $this->getSiteId($payload);

            if ($this->sitebuilderService->hasSitebuilderThroughGateway($subscription->customer->email)) {
                $getBasekitSiteByRefRequest = new GetBasekitSiteByRefRequest(
                    context: Uuid::fromString($subscription->uuid),
                    siteRef: $siteId,
                );

                $basekitSiteByRefResult = $this->provisionGateway->request($getBasekitSiteByRefRequest);

                if (! $basekitSiteByRefResult instanceof BasekitSiteResult || $basekitSiteByRefResult->failed) {
                    throw new HostingInstanceNotFoundException($payload, $server, $subscription, $basekitSiteByRefResult->exception);
                }

                $this->logger->debug(
                    'Setting default sitebuilder domain on subscription',
                    [
                        LoggingContextKeys::QUEUE_JOB_ID => $jobUuid,
                        LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $migratedCustomer->reference_customer_number,
                        LoggingContextKeys::SUBSCRIPTION_UUID => $hostingDeployment->subscription_uuid,
                        LoggingContextKeys::DOMAIN_NAME => $basekitSiteByRefResult->domain,
                        LoggingContextKeys::REQUEST_DATA => (string) json_encode($payload->toArray()),
                        LoggingContextKeys::SERVER_ID => $server->id,
                        LoggingContextKeys::SERVER_HOSTNAME => $server->hostname,
                    ]
                );

                $subscription->domain = $basekitSiteByRefResult->domain;
                $subscription->save();
            } else {
                $sitebuilderSite = $this->sitebuilderService->getSiteFromRef(
                    siteRef: $siteId,
                    server: $server,
                );

                $this->logger->debug(
                    'Setting default sitebuilder domain on subscription',
                    [
                        LoggingContextKeys::QUEUE_JOB_ID => $jobUuid,
                        LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $migratedCustomer->reference_customer_number,
                        LoggingContextKeys::SUBSCRIPTION_UUID => $hostingDeployment->subscription_uuid,
                        LoggingContextKeys::DOMAIN_NAME => $sitebuilderSite->getDomain(),
                        LoggingContextKeys::REQUEST_DATA => (string) json_encode($payload->toArray()),
                        LoggingContextKeys::SERVER_ID => $server->id,
                        LoggingContextKeys::SERVER_HOSTNAME => $server->hostname,
                    ]
                );

                $subscription->domain = $sitebuilderSite->getDomain();
                $subscription->save();
            }
        } catch (Throwable $exception) {
            throw new HostingUnableToSetDefaultDomainException(
                $subscription,
                $payload,
                $server,
                $exception
            );
        }
    }

    private function getSiteId(HostingMigrationPayload $payload): int
    {
        $hostingDetails = $payload->hostingDetails;

        return match (true) {
            $hostingDetails instanceof SitebuilderBaseKitDetails => $hostingDetails->basekitSiteRef,
            default => throw new HostingDetailsNotSupportedException($hostingDetails),
        };
    }
}
