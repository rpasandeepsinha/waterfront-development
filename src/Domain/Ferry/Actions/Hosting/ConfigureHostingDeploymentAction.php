<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Actions\Hosting;

use Psr\Log\LoggerInterface;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Ferry\Dto\Hosting\DirectAdminHostingDetails;
use Waterfront\Domain\Ferry\Dto\Hosting\HostingDetailsInterface;
use Waterfront\Domain\Ferry\Dto\Hosting\PleskHostingDetails;
use Waterfront\Domain\Ferry\Dto\Hosting\SitebuilderBaseKitDetails;
use Waterfront\Domain\Ferry\Exceptions\HostingDetailsNotSupportedException;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Sitebuilder\Services\SitebuilderProxy;
use Waterfront\Support\Enums\LoggingContextKeys;

class ConfigureHostingDeploymentAction
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly SitebuilderProxy $sitebuilderProxy,
    ) {
    }

    public function execute(
        HostingDeployment $hostingDeployment,
        MigratedCustomer $migratedCustomer,
        Server $server,
        Provider $hostingProvider,
        HostingDetailsInterface $hostingDetails,
        string $jobUuid,
    ): void {
        $hostingDeployment->refresh();

        $this->logger->debug(
            'Configuring hosting deployment',
            [
                LoggingContextKeys::QUEUE_JOB_ID => $jobUuid,
                LoggingContextKeys::SERVER_ID => $server->id,
                LoggingContextKeys::SERVER_HOSTNAME => $server->hostname,
                LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $migratedCustomer->reference_customer_number,
                LoggingContextKeys::META => [
                    'hosting_details' => $hostingDetails->toArray(),
                ],
                LoggingContextKeys::PROVISIONING_PROVIDER => $hostingProvider->slug,
            ]
        );

        /**
         * Don't forget to change the TechnicalHostingMigrationJob rollback() too if you change this!
         */
        switch (true) {
            case $hostingDetails instanceof DirectAdminHostingDetails:
                $hostingDeployment->directadmin_customer_username = $hostingDetails->directadminCustomerName;
                $hostingDeployment->server()->associate($server);
                $hostingDeployment->provider()->associate($hostingProvider);
                break;

            case $hostingDetails instanceof PleskHostingDetails:
                $hostingDeployment->plesk_customer_username = $hostingDetails->pleskCustomerUsername;
                $hostingDeployment->plesk_customer_id = $hostingDetails->pleskCustomerId;
                $hostingDeployment->server()->associate($server);
                $hostingDeployment->provider()->associate($hostingProvider);
                break;

            case $hostingDetails instanceof SitebuilderBaseKitDetails:
                $this->sitebuilderProxy->createBasekitDeploymentFromMigration(
                    hostingDeployment: $hostingDeployment,
                    baseKitDetails: $hostingDetails,
                    server: $server,
                    hostingProvider: $hostingProvider
                );
                break;

            default:
                throw new HostingDetailsNotSupportedException($hostingDetails);
        }

        $hostingDeployment->save();
    }
}
