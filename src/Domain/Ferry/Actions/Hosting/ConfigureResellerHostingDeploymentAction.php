<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Actions\Hosting;

use Psr\Log\LoggerInterface;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Ferry\Dto\Hosting\DirectAdminHostingDetails;
use Waterfront\Domain\Ferry\Dto\Hosting\HostingDetailsInterface;
use Waterfront\Domain\Ferry\Exceptions\HostingDetailsNotSupportedException;
use Waterfront\Domain\Ferry\Exceptions\ResellerHostingNotPlaceholderException;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\ResellerHosting\Models\ResellerHostingDeployment;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Support\Enums\LoggingContextKeys;

class ConfigureResellerHostingDeploymentAction
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(
        ResellerHostingDeployment $resellerHostingDeployment,
        MigratedCustomer $migratedCustomer,
        Server $server,
        Provider $hostingProvider,
        HostingDetailsInterface $hostingDetails,
        string $jobUuid,
    ): void {
        $resellerHostingDeployment->refresh();

        $this->logger->debug(
            'Configuring reseller hosting deployment',
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
         * Don't forget to change the TechnicalResellerHostingMigrationJob rollback() too if you change this!
         */
        switch (true) {
            case $hostingDetails instanceof DirectAdminHostingDetails:
                if ($resellerHostingDeployment->provider->slug !== ProviderSlug::PLACEHOLDER) {
                    throw new ResellerHostingNotPlaceholderException($resellerHostingDeployment);
                }

                $resellerHostingDeployment->directadmin_customer_username = $hostingDetails->directadminCustomerName;
                $resellerHostingDeployment->server()->associate($server);
                $resellerHostingDeployment->provider()->associate($hostingProvider);
                $resellerHostingDeployment->save();
                break;

            default:
                throw new HostingDetailsNotSupportedException($hostingDetails);
        }
    }
}
