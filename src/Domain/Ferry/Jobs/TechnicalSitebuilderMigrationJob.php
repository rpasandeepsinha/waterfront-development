<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Ferry\Actions\Hosting\AllowAllDirectAdminFeatureSetAction;
use Waterfront\Domain\Ferry\Actions\Hosting\ConfigureHostingDeploymentAction;
use Waterfront\Domain\Ferry\Actions\Hosting\ConfigureMailOnlyDeploymentAction;
use Waterfront\Domain\Ferry\Actions\Hosting\HostingCanGenerateSSOAction;
use Waterfront\Domain\Ferry\Actions\Hosting\HostingInstanceFetchAction;
use Waterfront\Domain\Ferry\Actions\Hosting\HostingInstanceIsResellerAction;
use Waterfront\Domain\Ferry\Actions\Hosting\SitebuilderSetDefaultDomainAction;
use Waterfront\Domain\Ferry\Actions\Hosting\SitebuilderSiteFetchAction;
use Waterfront\Domain\Ferry\Actions\Hosting\SitebuilderUserFetchAction;
use Waterfront\Domain\Ferry\Dto\Hosting\DirectAdminHostingDetails;
use Waterfront\Domain\Ferry\Dto\Hosting\HostingMigrationPayload;
use Waterfront\Domain\Ferry\Dto\Hosting\PleskHostingDetails;
use Waterfront\Domain\Ferry\Dto\Hosting\SitebuilderBaseKitDetails;
use Waterfront\Domain\Ferry\Dto\Hosting\SitebuilderBundleMigrationPayload;
use Waterfront\Domain\Ferry\Enums\MigrationStep;
use Waterfront\Domain\Ferry\Exceptions\HostingDetailsNotSupportedException;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Repositories\ServerRepository;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Providers\ProviderRepository;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Sitebuilder\Services\SitebuilderProxy;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;

class TechnicalSitebuilderMigrationJob extends MigrationJob implements ShouldQueue
{
    private SitebuilderSiteFetchAction $sitebuilderSiteFetchAction;

    private SitebuilderUserFetchAction $sitebuilderUserFetchAction;

    private HostingCanGenerateSSOAction $hostingCanGenerateSSOAction;

    private ConfigureHostingDeploymentAction $configureHostingDeploymentAction;

    private ConfigureMailOnlyDeploymentAction $configureMailOnlyDeploymentAction;

    private HostingInstanceFetchAction $hostingInstanceFetchAction;

    private HostingInstanceIsResellerAction $hostingInstanceIsResellerAction;

    private SitebuilderSetDefaultDomainAction $sitebuilderSetDefaultDomainAction;

    private AllowAllDirectAdminFeatureSetAction $allowAllDirectAdminFeatureSetAction;

    private SubscriptionRepository $subscriptionRepository;

    private ServerRepository $serverRepository;

    private ProviderRepository $providerRepository;

    private SitebuilderProxy $sitebuilderProxy;

    private readonly ?string $originalSubscriptionDomain;

    public function __construct(
        public Subscription $subscription,
        protected ?string $failedTechnicalStatus,
        protected SitebuilderBundleMigrationPayload $payload,
    ) {
        parent::__construct($subscription, $failedTechnicalStatus);

        $this->originalSubscriptionDomain = $this->subscription->domain;
    }

    public function getMigrationStep(): MigrationStep
    {
        return MigrationStep::SITEBUILDER_MIGRATION;
    }

    protected function getSuccessfulTechnicalStatus(): string
    {
        return TechnicalStatus::OK->value;
    }

    protected function registerServices(): void
    {
        $this->subscriptionRepository = self::resolve(SubscriptionRepository::class);
        $this->serverRepository = self::resolve(ServerRepository::class);
        $this->providerRepository = self::resolve(ProviderRepository::class);
        $this->hostingCanGenerateSSOAction = self::resolve(HostingCanGenerateSSOAction::class);
        $this->configureHostingDeploymentAction = self::resolve(ConfigureHostingDeploymentAction::class);
        $this->sitebuilderSetDefaultDomainAction = self::resolve(SitebuilderSetDefaultDomainAction::class);
        $this->sitebuilderSiteFetchAction = self::resolve(SitebuilderSiteFetchAction::class);
        $this->sitebuilderUserFetchAction = self::resolve(SitebuilderUserFetchAction::class);
        $this->hostingInstanceFetchAction = self::resolve(HostingInstanceFetchAction::class);
        $this->hostingInstanceIsResellerAction = self::resolve(HostingInstanceIsResellerAction::class);
        $this->configureMailOnlyDeploymentAction = self::resolve(ConfigureMailOnlyDeploymentAction::class);
        $this->allowAllDirectAdminFeatureSetAction = self::resolve(AllowAllDirectAdminFeatureSetAction::class);
        $this->sitebuilderProxy = self::resolve(SitebuilderProxy::class);
    }

    protected function runMigration(): void
    {
        $payload = $this->payload;
        $subscription = $this->subscription;
        $migratedCustomer = $this->migratedCustomer;

        $hostingDeployment = $this->subscriptionRepository->findHostingDeploymentBySubscription($subscription);

        // Sitebuilder
        $sitebuilderPayload = $this->payload->sitebuilder;

        $sitebuilderServer = $this->serverRepository->findByHostname($sitebuilderPayload->serverName);

        $sitebuilderProvider = $this->providerRepository->getByType(
            ProviderType::SITEBUILDER,
            ProviderSlug::from($sitebuilderPayload->driver),
        );

        $this->fetchAndVerifySitebuilderInstance(
            subscription: $subscription,
            migratedCustomer: $migratedCustomer,
            payload: $sitebuilderPayload,
            server: $sitebuilderServer,
        );

        // Mail only
        $mailOnlyPayload = $this->payload->mailOnly;

        $mailOnlyServer = $this->serverRepository->findByHostname($mailOnlyPayload->serverName);

        $mailOnlyProvider =
            $this->getMailProvider(
                payload: $mailOnlyPayload,
            );

        $this->fetchAndVerifyMailOnlyInstance(
            payload: $mailOnlyPayload,
            subscription: $subscription,
            migratedCustomer: $migratedCustomer,
            server: $mailOnlyServer,
        );

        // 1. set remote connection data
        // 2. couple server
        // 3. couple provider
        $this->migrateHostingDeployment(
            hostingDeployment: $hostingDeployment,
            migratedCustomer: $migratedCustomer,
            sitebuilderServer: $sitebuilderServer,
            sitebuilderProvider: $sitebuilderProvider,
            mailOnlyServer: $mailOnlyServer,
            mailOnlyProvider: $mailOnlyProvider,
            payload: $payload,
        );
    }

    protected function rollback(Throwable $throwable): void
    {
        $hostingDeployment = $this->subscriptionRepository->findHostingDeploymentBySubscription($this->subscription);

        // Clear technical details based on the driver provided
        $sitebuilderHostingDetails = $this->payload->sitebuilder->hostingDetails;

        // Sitebuilder
        match (true) {
            $sitebuilderHostingDetails instanceof SitebuilderBaseKitDetails
                => $this->sitebuilderProxy->rollbackSitebuilderDeployementFromMigration(
                subscription: $this->subscription,
                sitebuilderBaseKitDetails: $sitebuilderHostingDetails,
                hostingDeployment: $hostingDeployment,
            ),
            default => throw new HostingDetailsNotSupportedException($sitebuilderHostingDetails),
        };

        // Mail only
        $mailHostingDetails = $this->payload->mailOnly->hostingDetails;

        switch (true) {
            case $mailHostingDetails instanceof DirectAdminHostingDetails:
                $hostingDeployment->directadmin_customer_username = null;
                break;

            case $mailHostingDetails instanceof PleskHostingDetails:
                $hostingDeployment->plesk_customer_username = null;
                $hostingDeployment->plesk_customer_id = null;
                break;

            default:
                throw new HostingDetailsNotSupportedException($mailHostingDetails);
        }

        // Set mail server to null
        $hostingDeployment->mailOnlyServer()->disassociate();

        // Set mail provider to Placeholder
        $mailOnlyProvider = $this->providerRepository->getByType(
            ProviderType::MAILONLY,
            ProviderSlug::PLACEHOLDER,
        );
        $hostingDeployment->mailProvider()->associate($mailOnlyProvider);

        $hostingDeployment->save();

        $this->subscription->domain = $this->originalSubscriptionDomain;
        $this->subscription->save();
    }

    private function fetchAndVerifySitebuilderInstance(
        Subscription $subscription,
        MigratedCustomer $migratedCustomer,
        HostingMigrationPayload $payload,
        Server $server,
    ): void {
        $this->sitebuilderSiteFetchAction->execute(
            subscription: $subscription,
            migratedCustomer: $migratedCustomer,
            payload: $payload,
            server: $server,
            jobUuid: $this->getJobId(),
        );

        $this->sitebuilderUserFetchAction->execute(
            subscription: $subscription,
            migratedCustomer: $migratedCustomer,
            payload: $payload,
            server: $server,
            jobUuid: $this->getJobId(),
        );

        $this->hostingCanGenerateSSOAction->execute(
            subscription: $subscription,
            migratedCustomer: $migratedCustomer,
            payload: $payload,
            server: $server,
            jobUuid: $this->getJobId(),
        );
    }

    private function fetchAndVerifyMailOnlyInstance(
        HostingMigrationPayload $payload,
        Subscription $subscription,
        MigratedCustomer $migratedCustomer,
        Server $server,
    ): void {
        $mailDto = $this->hostingInstanceFetchAction->execute(
            subscription: $subscription,
            migratedCustomer: $migratedCustomer,
            payload: $payload,
            server: $server,
            jobUuid: $this->getJobId(),
        );

        $this->hostingInstanceIsResellerAction->execute(
            payload: $payload,
            migratedCustomer: $migratedCustomer,
            server: $server,
            siteConfig: $mailDto,
            jobUuid: $this->getJobId(),
        );
    }

    private function migrateHostingDeployment(
        HostingDeployment $hostingDeployment,
        MigratedCustomer $migratedCustomer,
        Server $sitebuilderServer,
        Provider $sitebuilderProvider,
        Server $mailOnlyServer,
        Provider $mailOnlyProvider,
        SitebuilderBundleMigrationPayload $payload,
    ): void {
        // Sitebuilder
        $this->configureHostingDeploymentAction->execute(
            hostingDeployment: $hostingDeployment,
            migratedCustomer: $migratedCustomer,
            server: $sitebuilderServer,
            hostingProvider: $sitebuilderProvider,
            hostingDetails: $payload->sitebuilder->hostingDetails,
            jobUuid: $this->getJobId(),
        );

        // Mail only
        $this->configureMailOnlyDeploymentAction->execute(
            hostingDeployment: $hostingDeployment,
            migratedCustomer: $migratedCustomer,
            server: $mailOnlyServer,
            mailOnlyProvider: $mailOnlyProvider,
            hostingDetails: $payload->mailOnly->hostingDetails,
            jobUuid: $this->getJobId(),
        );

        $hostingDeployment->refresh();

        if ($hostingDeployment->subscription->domain === null) {
            // Set the default domain configured on the remote sitebuilder entity.
            $this->sitebuilderSetDefaultDomainAction->execute(
                hostingDeployment: $hostingDeployment,
                migratedCustomer: $migratedCustomer,
                payload: $payload->sitebuilder,
                server: $sitebuilderServer,
                jobUuid: $this->getJobId(),
            );
        }

        $this->allowAllDirectAdminFeatureSetAction->execute(
            hostingDeployment: $hostingDeployment,
            migratedCustomer: $migratedCustomer,
            server: $mailOnlyServer,
            mailOnlyProvider: $mailOnlyProvider,
            hostingDetails: $payload->mailOnly->hostingDetails,
            jobUuid: $this->getJobId(),
        );
    }

    private function getMailProvider(
        HostingMigrationPayload $payload,
    ): Provider {
        if ($payload->hostingDetails instanceof PleskHostingDetails) {
            $providerType = ProviderType::HOSTING;
        } else {
            $providerType = ProviderType::MAILONLY;
        }

        return $this->providerRepository->getByType(
            $providerType,
            ProviderSlug::from($payload->driver),
        );
    }
}
