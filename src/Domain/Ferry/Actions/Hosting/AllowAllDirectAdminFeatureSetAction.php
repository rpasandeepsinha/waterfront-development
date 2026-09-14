<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Actions\Hosting;

use Psr\Log\LoggerInterface;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Ferry\Dto\Hosting\HostingDetailsInterface;
use Waterfront\Domain\Hosting\DirectAdmin\Services\DirectAdminHostingService;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Infra\DirectAdminClient\BehavesAsDirectAdmin;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class AllowAllDirectAdminFeatureSetAction
{
    public function __construct(
        private readonly BehavesAsDirectAdmin $directAdmin,
        private readonly DirectAdminHostingService $directAdminHostingService,
        private readonly LoggerInterface $logger,
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
        if ($mailOnlyProvider->slug !== ProviderSlug::DIRECTADMIN) {
            // Feature sets only need to be set to allow all on DirectAdmin
            return;
        }

        $userName = $hostingDeployment->directadmin_customer_username;

        Assert::string($userName);

        $userConfig = $this->directAdminHostingService->getUserConfigAsAdmin(
            identifier: $userName,
            server: $server,
        );

        if (array_key_exists('feature_sets', $userConfig) && $userConfig['feature_sets'] === '') {
            return;
        }

        $this->logger->debug(
            'Setting "Allow All Commands" policy on the DirectAdmin feature sets for user',
            [
                LoggingContextKeys::QUEUE_JOB_ID => $jobUuid,
                LoggingContextKeys::SERVER_ID => $server->id,
                LoggingContextKeys::SERVER_HOSTNAME => $server->hostname,
                LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $migratedCustomer->reference_customer_number,
                LoggingContextKeys::PRODUCT_SLUG => $hostingDeployment->subscription->product->slug,
                LoggingContextKeys::META => [
                    'hosting_details' => $hostingDetails->toArray(),
                ],
                LoggingContextKeys::PROVISIONING_PROVIDER => $mailOnlyProvider->slug,
            ],
        );

        $userConfig['feature_sets'] = '';

        // See comment in Directadmin modifyCustomer()
        unset($userConfig['package']);

        $this->directAdmin->user($server)->update(
            $userName,
            $userConfig,
        );
    }
}
