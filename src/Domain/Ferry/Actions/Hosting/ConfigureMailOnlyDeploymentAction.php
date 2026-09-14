<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Actions\Hosting;

use Psr\Log\LoggerInterface;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Ferry\Dto\Hosting\DirectAdminHostingDetails;
use Waterfront\Domain\Ferry\Dto\Hosting\HostingDetailsInterface;
use Waterfront\Domain\Ferry\Dto\Hosting\PleskHostingDetails;
use Waterfront\Domain\Ferry\Exceptions\HostingDetailsNotSupportedException;
use Waterfront\Domain\Ferry\Repositories\SpamExpertsMigrationRepository;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Support\Enums\LoggingContextKeys;

class ConfigureMailOnlyDeploymentAction
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly SpamExpertsMigrationRepository $spamExpertsMigrationRepository,
    ) {
    }

    public function execute(
        HostingDeployment $hostingDeployment,
        MigratedCustomer $migratedCustomer,
        Server $server,
        Provider $mailOnlyProvider,
        HostingDetailsInterface $hostingDetails,
        string $jobUuid,
    ): void {
        $hostingDeployment->refresh();

        $this->logger->debug(
            'Configuring mail only deployment',
            [
                LoggingContextKeys::QUEUE_JOB_ID => $jobUuid,
                LoggingContextKeys::SERVER_ID => $server->id,
                LoggingContextKeys::SERVER_HOSTNAME => $server->hostname,
                LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $migratedCustomer->reference_customer_number,
                LoggingContextKeys::META => [
                    'hosting_details.username' => $hostingDetails->getUsername(),
                ],
                LoggingContextKeys::PROVISIONING_PROVIDER => $mailOnlyProvider->slug,
            ],
        );

        /**
         * Don't forget to change the TechnicalMailOnlyMigrationJob rollback() too if you change this!
         */
        switch (true) {
            case $hostingDetails instanceof DirectAdminHostingDetails:
                $hostingDeployment->directadmin_customer_username = $hostingDetails->directadminCustomerName;
                break;

            case $hostingDetails instanceof PleskHostingDetails:
                $hostingDeployment->plesk_customer_username = $hostingDetails->pleskCustomerUsername;
                $hostingDeployment->plesk_customer_id = $hostingDetails->pleskCustomerId;
                break;

            default:
                throw new HostingDetailsNotSupportedException($hostingDetails);
        }

        $isMailOnlyServer = $hostingDeployment->subscription->product->isMailOnlyServer();

        if ($hostingDetails instanceof PleskHostingDetails) {
            $shouldUseMailOnlyProvider = $isMailOnlyServer;
        } else {
            $shouldUseMailOnlyProvider = $hostingDeployment->subscription->product->isSitebuilderProduct()
            || $isMailOnlyServer;
        }

        $shouldUseMailOnlyProvider
            ? $this->coupleMailOnlyProvider($hostingDeployment, $server, $mailOnlyProvider)
            : $this->coupleNormalProvider($hostingDeployment, $server, $mailOnlyProvider);

        $hostingDeployment->save();

        $cluster = $this->spamExpertsMigrationRepository->getSpamExpertsClusterByMigratedCustomerBuName($migratedCustomer->reference_name);

        if ($cluster !== null) {
            $hostingDeployment->spamExpertsCluster()->associate($cluster);
            $hostingDeployment->save();
        } else {
            $this->logger->notice('Unable to find a SpamExperts cluster with email only migration', [
                LoggingContextKeys::SERVER_ID => $server->id,
                LoggingContextKeys::SERVER_HOSTNAME => $server->hostname,
                LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $migratedCustomer->reference_customer_number,
                LoggingContextKeys::META => [
                    'hosting_details.username' => $hostingDetails->getUsername(),
                    'migrated_customer.business_unit' => $migratedCustomer->reference_name,
                ],
                LoggingContextKeys::PROVISIONING_PROVIDER => $mailOnlyProvider->slug,
            ]);
        }
    }

    private function coupleNormalProvider(
        HostingDeployment $hostingDeployment,
        Server $server,
        Provider $provider,
    ): void {
        $hostingDeployment->server()->associate($server);
        $hostingDeployment->provider()->associate($provider);

        $hostingDeployment->mailOnlyServer()->disassociate();
        $hostingDeployment->mailProvider()->disassociate();
    }

    private function coupleMailOnlyProvider(
        HostingDeployment $hostingDeployment,
        Server $server,
        Provider $provider,
    ): void {
        $hostingDeployment->mailOnlyServer()->associate($server);
        $hostingDeployment->mailProvider()->associate($provider);
    }
}
