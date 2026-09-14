<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Jobs;

use Throwable;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Ferry\Actions\Hosting\ConfigureResellerHostingDeploymentAction;
use Waterfront\Domain\Ferry\Actions\Hosting\HostingCanGenerateSSOAction;
use Waterfront\Domain\Ferry\Actions\Hosting\HostingInstanceFetchAction;
use Waterfront\Domain\Ferry\Actions\Hosting\ResellerHostingDeploymentSetDefaultDomainAction;
use Waterfront\Domain\Ferry\Actions\Hosting\ResellerHostingModifySiteForMigrationAction;
use Waterfront\Domain\Ferry\Dto\Hosting\DirectAdminHostingDetails;
use Waterfront\Domain\Ferry\Dto\Hosting\HostingDetailsInterface;
use Waterfront\Domain\Ferry\Dto\Hosting\HostingMigrationPayload;
use Waterfront\Domain\Ferry\Enums\MigrationStep;
use Waterfront\Domain\Ferry\Exceptions\HostingDetailsNotSupportedException;
use Waterfront\Domain\Ferry\Exceptions\ResellerHostingMigrationIsNotAResellerException;
use Waterfront\Domain\Hosting\Interfaces\Hosting\SiteConfigInterface;
use Waterfront\Domain\Hosting\Repositories\ServerRepository;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\ResellerHosting\Models\ResellerHostingDeployment;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Support\Enums\LoggingContextKeys;

class TechnicalResellerHostingMigrationJob extends MigrationJob
{
    private HostingCanGenerateSSOAction $hostingCanGenerateSSOAction;

    private HostingInstanceFetchAction $hostingInstanceFetchAction;

    private ConfigureResellerHostingDeploymentAction $configureResellerHostingDeploymentAction;

    private ResellerHostingDeploymentSetDefaultDomainAction $resellerHostingDeploymentSetDefaultDomainAction;

    private ResellerHostingModifySiteForMigrationAction $resellerHostingModifySiteForMigrationAction;

    private ServerRepository $serverRepository;

    private SubscriptionRepository $subscriptionRepository;

    private SiteConfigInterface $siteDto;

    private readonly ?string $originalSubscriptionDomain;

    public function __construct(
        public Subscription $subscription,
        protected ?string $failedTechnicalStatus,
        protected HostingMigrationPayload $payload,
    ) {
        parent::__construct($this->subscription, $this->failedTechnicalStatus);

        $this->originalSubscriptionDomain = $this->subscription->domain;
    }

    /**
     * @inheritDoc
     */
    public function getMigrationStep(): MigrationStep
    {
        return MigrationStep::RESELLER_HOSTING_MIGRATION;
    }

    protected function runMigration(): void
    {
        // Gather local data needed to run a hosting migration
        $payload = $this->payload;
        $hostingDetails = $payload->hostingDetails;
        $subscription = $this->subscription;
        $migratedCustomer = $this->migratedCustomer;

        $server = $this->serverRepository->findByHostname($payload->serverName);

        $hostingProvider = Provider::query()
            ->where('slug', $payload->driver)
            ->where('type', ProviderType::HOSTING)
            ->firstOrFail();

        $resellerHostingDeployment = $this->subscription->resellerHostingDeployment()->firstOrFail();

        // Start fetching and verifying procedures for the remote backend
        //
        // 1. fetch remote user
        // 2. check if user is a reseller
        // 3. fetch remote package
        // 4. check if sso can be generated
        $this->fetchAndVerifyResellerHostingInstance(
            subscription: $subscription,
            migratedCustomer: $migratedCustomer,
            payload: $payload,
            server: $server,
        );

        // All checks done, now commit the needed changes to the entities locally and remote.
        //
        // 1. fill deployment (username, server etc.)
        // 2. fill subscription domain if empty
        // 3. disable dns management on DA user based on nameserver state and domain inclusion
        //      (includes enable on sso in the modify user call to the backend)
        $this->migrateResellerHostingInstance(
            subscription: $subscription,
            migratedCustomer: $migratedCustomer,
            resellerHostingDeployment: $resellerHostingDeployment,
            server: $server,
            hostingProvider: $hostingProvider,
            hostingDetails: $hostingDetails,
            payload: $payload,
        );
    }

    protected function rollback(Throwable $throwable): void
    {
        /** @var ResellerHostingDeployment $resellerHostingDeployment */
        $resellerHostingDeployment = $this->subscription->resellerHostingDeployment()->firstOrFail();

        // Clear technical details based on the driver provided
        $hostingDetails = $this->payload->hostingDetails;

        $resellerHostingDeployment->directadmin_customer_username = match (true) {
            $hostingDetails instanceof DirectAdminHostingDetails => null,
            default => throw new HostingDetailsNotSupportedException($hostingDetails),
        };

        // set server to null
        $resellerHostingDeployment->server()->disassociate();

        // set provider to Placeholder
        $placeholderProvider = Provider::query()
            ->where('slug', ProviderSlug::PLACEHOLDER->value)
            ->where('type', ProviderType::HOSTING)
            ->firstOrFail();
        $resellerHostingDeployment->provider()->associate($placeholderProvider);

        $resellerHostingDeployment->save();

        $this->subscription->domain = $this->originalSubscriptionDomain;
        $this->subscription->save();
    }

    protected function registerServices(): void
    {
        $this->serverRepository = self::resolve(ServerRepository::class);
        $this->hostingInstanceFetchAction = self::resolve(HostingInstanceFetchAction::class);
        $this->hostingCanGenerateSSOAction = self::resolve(HostingCanGenerateSSOAction::class);
        $this->configureResellerHostingDeploymentAction = self::resolve(ConfigureResellerHostingDeploymentAction::class);
        $this->resellerHostingDeploymentSetDefaultDomainAction = self::resolve(ResellerHostingDeploymentSetDefaultDomainAction::class);
        $this->subscriptionRepository = self::resolve(SubscriptionRepository::class);
        $this->resellerHostingModifySiteForMigrationAction = self::resolve(ResellerHostingModifySiteForMigrationAction::class);
    }

    /**
     * @inheritDoc
     */
    protected function getSuccessfulTechnicalStatus(): string
    {
        return TechnicalStatus::OK->value;
    }

    private function fetchAndVerifyResellerHostingInstance(
        Subscription $subscription,
        MigratedCustomer $migratedCustomer,
        HostingMigrationPayload $payload,
        Server $server,
    ): void {
        $this->siteDto = $this->hostingInstanceFetchAction->execute(
            subscription: $subscription,
            migratedCustomer: $migratedCustomer,
            payload: $payload,
            server: $server,
            jobUuid: $this->getJobId(),
        );

        $this->verifyReseller(
            siteConfig: $this->siteDto,
            server: $server,
            migratedCustomer: $migratedCustomer,
            username: $payload->hostingDetails->getUsername(),
            driver: $payload->driver,
            jobUuid: $this->getJobId(),
        );

        if ($this->siteDto->hasSsoEnabled()) {
            $this->hostingCanGenerateSSOAction->execute(
                subscription: $subscription,
                migratedCustomer: $migratedCustomer,
                payload: $payload,
                server: $server,
                jobUuid: $this->getJobId(),
            );
        }
    }

    private function migrateResellerHostingInstance(
        Subscription $subscription,
        MigratedCustomer $migratedCustomer,
        ResellerHostingDeployment $resellerHostingDeployment,
        Server $server,
        Provider $hostingProvider,
        HostingDetailsInterface $hostingDetails,
        HostingMigrationPayload $payload,
    ): void {
        // 1. set remote connection data
        // 2. couple server
        // 3. couple provider
        // 4. set default domain
        $this->migrateResellerHostingDeployment(
            resellerHostingDeployment: $resellerHostingDeployment,
            migratedCustomer: $migratedCustomer,
            server: $server,
            hostingProvider: $hostingProvider,
            hostingDetails: $hostingDetails,
            payload: $payload,
        );

        // Configure the backend to either enable or disable dns management in the external panel
        // Also enforce SSO accessibility for the remote user.
        // based on the given domain and state of nameservers coupled to the provided domain.
        $this->modifyBackend(
            subscription: $subscription,
            migratedCustomer: $migratedCustomer,
        );
    }

    private function migrateResellerHostingDeployment(
        ResellerHostingDeployment $resellerHostingDeployment,
        MigratedCustomer $migratedCustomer,
        Server $server,
        Provider $hostingProvider,
        HostingDetailsInterface $hostingDetails,
        HostingMigrationPayload $payload,
    ): void {
        $this->configureResellerHostingDeploymentAction->execute(
            resellerHostingDeployment: $resellerHostingDeployment,
            migratedCustomer: $migratedCustomer,
            server: $server,
            hostingProvider: $hostingProvider,
            hostingDetails: $hostingDetails,
            jobUuid: $this->getJobId(),
        );

        if ($resellerHostingDeployment->subscription->domain === null) {
            // Set the default domain configured on the remote hosting entity.
            $this->resellerHostingDeploymentSetDefaultDomainAction->execute(
                resellerHostingDeployment: $resellerHostingDeployment,
                migratedCustomer: $migratedCustomer,
                payload: $payload,
                server: $server,
                jobUuid: $this->getJobId(),
            );
        }
    }

    private function modifyBackend(
        Subscription $subscription,
        MigratedCustomer $migratedCustomer,
    ): void {
        // Subscription now has a real and COMPLETE backend coupling instead of the default placeholder setup.
        $domain = $this->resolveDomain($subscription);

        $isUsingLocalDomain = $this->subscriptionRepository->subscriptionExistsForCustomerIdAndDomainForType(
            customerId: $subscription->customer->id,
            domain: $domain,
            productGroupType: ProductGroupType::EXTENSION,
        );

        $this->resellerHostingModifySiteForMigrationAction->execute(
            subscription: $subscription,
            migratedCustomer: $migratedCustomer,
            hostingMigrationPayload: $this->payload,
            jobUuid: $this->getJobId(),
            isUsingLocalDomain: $isUsingLocalDomain,
        );
    }

    private function resolveDomain(Subscription $subscription): string
    {
        $subscription = $subscription->refresh();

        return is_string($subscription->domain) ? $subscription->domain : '';
    }

    private function verifyReseller(
        SiteConfigInterface $siteConfig,
        Server $server,
        MigratedCustomer $migratedCustomer,
        string $username,
        string $driver,
        string $jobUuid,
    ): void {
        if (! $siteConfig->isReseller()) {
            $this->logger->debug(
                'Provided remote instance is a reseller',
                [
                    LoggingContextKeys::QUEUE_JOB_ID => $jobUuid,
                    LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                    LoggingContextKeys::SERVER_ID => $server->id,
                    LoggingContextKeys::SERVER_HOSTNAME => $server->hostname,
                    LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $migratedCustomer->reference_customer_number,
                    LoggingContextKeys::META => [
                        'username' => $username,
                        'payload.driver' => $driver,
                    ],
                ],
            );

            throw new ResellerHostingMigrationIsNotAResellerException(
                username: $username,
                driver: $driver,
                hostname: $server->hostname,
            );
        }
    }
}
