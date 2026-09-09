<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Ferry\Actions\DNS\ModifyHostingDnsSettingsAction;
use Waterfront\Domain\Ferry\Actions\Hosting\ConfigureHostingDeploymentAction;
use Waterfront\Domain\Ferry\Actions\Hosting\HostingCanGenerateSSOAction;
use Waterfront\Domain\Ferry\Actions\Hosting\HostingDeploymentSetDefaultDomainAction;
use Waterfront\Domain\Ferry\Actions\Hosting\HostingInstanceFetchAction;
use Waterfront\Domain\Ferry\Actions\Hosting\HostingInstanceIsResellerAction;
use Waterfront\Domain\Ferry\Dto\Hosting\DirectAdminHostingDetails;
use Waterfront\Domain\Ferry\Dto\Hosting\HostingDetailsInterface;
use Waterfront\Domain\Ferry\Dto\Hosting\HostingMigrationPayload;
use Waterfront\Domain\Ferry\Dto\Hosting\PleskHostingDetails;
use Waterfront\Domain\Ferry\Enums\MigrationSource;
use Waterfront\Domain\Ferry\Enums\MigrationStep;
use Waterfront\Domain\Ferry\Exceptions\HostingDetailsNotSupportedException;
use Waterfront\Domain\Hosting\Actions\ChangeHostingAction;
use Waterfront\Domain\Hosting\Interfaces\Hosting\SiteConfigInterface;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Repositories\ServerRepository;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;

class TechnicalHostingMigrationJob extends MigrationJob implements ShouldQueue
{
    private HostingCanGenerateSSOAction $hostingCanGenerateSSOAction;

    private HostingInstanceFetchAction $hostingInstanceFetchAction;

    private ConfigureHostingDeploymentAction $configureHostingSubscriptionAction;

    private HostingDeploymentSetDefaultDomainAction $hostingDeploymentSetDefaultDomainAction;

    private HostingInstanceIsResellerAction $hostingInstanceIsResellerAction;

    private ChangeHostingAction $changeHostingAction;

    private ServerRepository $serverRepository;

    private SubscriptionRepository $subscriptionRepository;

    private ModifyHostingDnsSettingsAction $modifyHostingDnsSettingsAction;

    private SiteConfigInterface $siteDto;

    private readonly string|null $originalSubscriptionDomain;

    public function __construct(
        public Subscription $subscription,
        protected string|null $failedTechnicalStatus,
        protected HostingMigrationPayload $payload,
        public MigrationSource $migrationSource = MigrationSource::AZURE_DATA_FACTORY,
    ) {
        parent::__construct($this->subscription, $this->failedTechnicalStatus);

        $this->originalSubscriptionDomain = $this->subscription->domain;
    }

    public function getMigrationStep(): MigrationStep
    {
        return MigrationStep::HOSTING_MIGRATION;
    }

    protected function runMigration(): void
    {
        // Gather local data needed to run a hosting migration
        $payload = $this->payload;
        $hostingDetails = $payload->hostingDetails;
        $subscription = $this->subscription;
        $migratedCustomer = $this->migratedCustomer;

        $server = $this->serverRepository
            ->findByHostname($payload->serverName);

        $hostingProvider = Provider::query()->where('slug', $payload->driver)->where('type', ProviderType::HOSTING)->firstOrFail();
        $hostingDeployment = $this->subscriptionRepository
            ->findHostingDeploymentBySubscription($this->subscription);

        // Start fetching and verifying procedures for the remote backend
        //
        // 1. fetch remote user
        // 2. check if user is a reseller
        // 3. fetch remote package
        // 4. check if sso can be generated
        $this->fetchAndVerifyHostingInstance(
            subscription: $subscription,
            migratedCustomer: $migratedCustomer,
            payload: $payload,
            server: $server
        );

        // All checks done, now commit the needed changes to the entities locally and remote.
        //
        // 1. fill deployment (username, server etc.)
        // 2. fill subscription domain if empty
        // 3. disable dns management on DA user based on nameserver state and domain inclusion
        //      (includes enable on sso in the modify user call to the backend)
        $this->migrateHostingInstance(
            subscription: $subscription,
            migratedCustomer: $migratedCustomer,
            hostingDeployment: $hostingDeployment,
            server: $server,
            hostingProvider: $hostingProvider,
            hostingDetails: $hostingDetails,
            payload: $payload,
        );
    }

    protected function getSuccessfulTechnicalStatus(): string
    {
        return TechnicalStatus::OK->value;
    }

    protected function registerServices(): void
    {
        $this->hostingInstanceFetchAction                = self::resolve(HostingInstanceFetchAction::class);
        $this->hostingCanGenerateSSOAction               = self::resolve(HostingCanGenerateSSOAction::class);
        $this->configureHostingSubscriptionAction        = self::resolve(ConfigureHostingDeploymentAction::class);
        $this->hostingDeploymentSetDefaultDomainAction   = self::resolve(HostingDeploymentSetDefaultDomainAction::class);
        $this->hostingInstanceIsResellerAction           = self::resolve(HostingInstanceIsResellerAction::class);
        $this->modifyHostingDnsSettingsAction            = self::resolve(ModifyHostingDnsSettingsAction::class);
        $this->serverRepository                          = self::resolve(ServerRepository::class);
        $this->subscriptionRepository                    = self::resolve(SubscriptionRepository::class);
        $this->changeHostingAction                       = self::resolve(ChangeHostingAction::class);
    }

    protected function rollback(Throwable $throwable): void
    {
        $hostingDeployment = $this->subscriptionRepository
            ->findHostingDeploymentBySubscription($this->subscription);

        // Clear technical details based on the driver provided
        $hostingDetails = $this->payload->hostingDetails;

        switch (true) {
            case $hostingDetails instanceof DirectAdminHostingDetails:
                $hostingDeployment->directadmin_customer_username = null;
                break;

            case $hostingDetails instanceof PleskHostingDetails:
                $hostingDeployment->plesk_customer_username = null;
                $hostingDeployment->plesk_customer_id = null;
                break;

            default:
                throw new HostingDetailsNotSupportedException($hostingDetails);
        }

        // set server to null
        $hostingDeployment->server()->disassociate();

        // set provider to Placeholder
        $placeholderProvider = Provider::query()->where('slug', ProviderSlug::PLACEHOLDER->value)->where('type', ProviderType::HOSTING)->firstOrFail();
        $hostingDeployment->provider()->associate($placeholderProvider);

        $hostingDeployment->save();

        $this->subscription->domain = $this->originalSubscriptionDomain;
        $this->subscription->save();
    }

    private function fetchAndVerifyHostingInstance(
        Subscription $subscription,
        MigratedCustomer $migratedCustomer,
        HostingMigrationPayload $payload,
        Server $server
    ): void {
        $this->siteDto = $this->hostingInstanceFetchAction->execute(
            subscription: $subscription,
            migratedCustomer: $migratedCustomer,
            payload: $payload,
            server: $server,
            jobUuid: $this->getJobId()
        );

        $this->hostingInstanceIsResellerAction->execute(
            payload: $payload,
            migratedCustomer: $migratedCustomer,
            server: $server,
            siteConfig: $this->siteDto,
            jobUuid: $this->getJobId()
        );

        if ($this->siteDto->hasSsoEnabled()) {
            $this->hostingCanGenerateSSOAction->execute(
                subscription: $subscription,
                migratedCustomer: $migratedCustomer,
                payload: $payload,
                server: $server,
                jobUuid: $this->getJobId()
            );
        }
    }

    private function migrateHostingInstance(
        Subscription $subscription,
        MigratedCustomer $migratedCustomer,
        HostingDeployment $hostingDeployment,
        Server $server,
        Provider $hostingProvider,
        HostingDetailsInterface $hostingDetails,
        HostingMigrationPayload $payload,
    ): void {
        // 1. set remote connection data
        // 2. couple server
        // 3. couple provider
        // 4. set default domain
        $this->migrateHostingDeployment(
            hostingDeployment: $hostingDeployment,
            migratedCustomer: $migratedCustomer,
            server: $server,
            hostingProvider: $hostingProvider,
            hostingDetails: $hostingDetails,
            payload: $payload
        );

        $subscription->refresh();
        $hostingDeployment->refresh();

        // Configure the backend to either enable or disable dns management in the external panel
        // Also enforce SSO accessibility for the remote user.
        // based on the given domain and state of nameservers coupled to the provided domain.
        $this->modifyHostingDnsSettingsAction->execute(
            subscription: $subscription,
            migratedCustomer: $migratedCustomer,
            server: $server,
            payload: $payload,
            siteDto: $this->siteDto,
            jobId: $this->getJobId()
        );

        // Sync the administratively configured product to the remote package using the service plan that is coupled to the
        // slug of the local product set during the administrative subscription migration from the product lists in ADF.
        $this->syncServicePlanToRemote(
            subscription: $subscription,
            hostingDeployment: $hostingDeployment,
        );
    }

    private function migrateHostingDeployment(
        HostingDeployment $hostingDeployment,
        MigratedCustomer $migratedCustomer,
        Server $server,
        Provider $hostingProvider,
        HostingDetailsInterface $hostingDetails,
        HostingMigrationPayload $payload,
    ): void {
        $this->configureHostingSubscriptionAction->execute(
            hostingDeployment: $hostingDeployment,
            migratedCustomer: $migratedCustomer,
            server: $server,
            hostingProvider: $hostingProvider,
            hostingDetails: $hostingDetails,
            jobUuid: $this->getJobId()
        );

        if ($hostingDeployment->subscription->domain === null) {
            // Set the default domain configured on the remote hosting entity.
            $this->hostingDeploymentSetDefaultDomainAction->execute(
                hostingDeployment: $hostingDeployment,
                migratedCustomer: $migratedCustomer,
                payload: $payload,
                server: $server,
                jobUuid: $this->getJobId()
            );
        }
    }

    private function syncServicePlanToRemote(
        Subscription $subscription,
        HostingDeployment $hostingDeployment,
    ): void {
        if ($this->siteDto->getPackage() === $subscription->product->slug) {
            return;
        }

        $this->changeHostingAction->execute(
            subscription: $subscription,
            hostingDeployment: $hostingDeployment,
            oldProduct: $subscription->product,
            newProduct: $subscription->product,
            originalServicePlan: $this->siteDto->getPackage(),
        );
    }
}
