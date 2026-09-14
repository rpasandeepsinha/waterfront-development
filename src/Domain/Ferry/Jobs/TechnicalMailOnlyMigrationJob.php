<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Ferry\Actions\DNS\ModifyHostingDnsSettingsAction;
use Waterfront\Domain\Ferry\Actions\Hosting\AllowAllDirectAdminFeatureSetAction;
use Waterfront\Domain\Ferry\Actions\Hosting\ConfigureMailOnlyDeploymentAction;
use Waterfront\Domain\Ferry\Actions\Hosting\HostingCanGenerateSSOAction;
use Waterfront\Domain\Ferry\Actions\Hosting\HostingDeploymentSetDefaultDomainAction;
use Waterfront\Domain\Ferry\Actions\Hosting\HostingInstanceFetchAction;
use Waterfront\Domain\Ferry\Actions\Hosting\HostingInstanceIsResellerAction;
use Waterfront\Domain\Ferry\Dto\Hosting\DirectAdminHostingDetails;
use Waterfront\Domain\Ferry\Dto\Hosting\HostingMigrationPayload;
use Waterfront\Domain\Ferry\Dto\Hosting\PleskHostingDetails;
use Waterfront\Domain\Ferry\Enums\MigrationStep;
use Waterfront\Domain\Ferry\Exceptions\HostingDetailsNotSupportedException;
use Waterfront\Domain\Hosting\Interfaces\Hosting\SiteConfigInterface;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Repositories\ServerRepository;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Repositories\ProductSpecRepository;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Providers\ProviderRepository;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;

class TechnicalMailOnlyMigrationJob extends MigrationJob implements ShouldQueue
{
    private ?string $originalSubscriptionDomain = null;

    private ConfigureMailOnlyDeploymentAction $configureMailOnlyDeploymentAction;

    private HostingInstanceFetchAction $hostingInstanceFetchAction;

    private HostingInstanceIsResellerAction $hostingInstanceIsResellerAction;

    private HostingDeploymentSetDefaultDomainAction $hostingDeploymentSetDefaultDomainAction;

    private AllowAllDirectAdminFeatureSetAction $allowAllDirectAdminFeatureSetAction;

    private ServerRepository $serverRepository;

    private SubscriptionRepository $subscriptionRepository;

    private ProviderRepository $providerRepository;

    private ProductSpecRepository $productSpecRepository;

    private HostingCanGenerateSSOAction $hostingCanGenerateSSOAction;

    private ModifyHostingDnsSettingsAction $modifyHostingDnsSettingsAction;

    private SiteConfigInterface $mailOnlyDto;

    public function __construct(
        public Subscription $subscription,
        protected ?string $failedTechnicalStatus,
        protected HostingMigrationPayload $payload,
    ) {
        parent::__construct(
            $this->subscription,
            $this->failedTechnicalStatus,
        );
    }

    public function getMigrationStep(): MigrationStep
    {
        return MigrationStep::MAIL_ONLY_MIGRATION;
    }

    protected function runMigration(): void
    {
        // Gather local data needed to run a mail only migration
        $payload = $this->payload;
        $subscription = $this->subscription;
        $product = $subscription->product;
        $migratedCustomer = $this->migratedCustomer;
        $this->originalSubscriptionDomain = $subscription->domain;

        $hostingDeployment = $this->subscriptionRepository->findHostingDeploymentBySubscription($subscription);

        $server = $this->serverRepository->findByHostname($payload->serverName);

        $mailProvider = $this->getMailProvider(
            payload: $payload,
            product: $product,
        );

        $this->fetchAndVerifyMailOnlyInstance(
            payload: $payload,
            subscription: $subscription,
            migratedCustomer: $migratedCustomer,
            server: $server,
        );

        $this->migrateMailOnly(
            migratedCustomer: $this->migratedCustomer,
            hostingDeployment: $hostingDeployment,
            server: $server,
            mailOnlyProvider: $mailProvider,
            payload: $payload,
        );
    }

    protected function getSuccessfulTechnicalStatus(): string
    {
        return TechnicalStatus::OK->value;
    }

    protected function registerServices(): void
    {
        $this->configureMailOnlyDeploymentAction = self::resolve(ConfigureMailOnlyDeploymentAction::class);
        $this->serverRepository = self::resolve(ServerRepository::class);
        $this->subscriptionRepository = self::resolve(SubscriptionRepository::class);
        $this->providerRepository = self::resolve(ProviderRepository::class);
        $this->hostingInstanceFetchAction = self::resolve(HostingInstanceFetchAction::class);
        $this->hostingInstanceIsResellerAction = self::resolve(HostingInstanceIsResellerAction::class);
        $this->hostingDeploymentSetDefaultDomainAction = self::resolve(HostingDeploymentSetDefaultDomainAction::class);
        $this->allowAllDirectAdminFeatureSetAction = self::resolve(AllowAllDirectAdminFeatureSetAction::class);
        $this->modifyHostingDnsSettingsAction = self::resolve(ModifyHostingDnsSettingsAction::class);
        $this->productSpecRepository = self::resolve(ProductSpecRepository::class);
        $this->hostingCanGenerateSSOAction = self::resolve(HostingCanGenerateSSOAction::class);
    }

    protected function rollback(Throwable $throwable): void
    {
        $hostingDeployment = $this->subscriptionRepository->findHostingDeploymentBySubscription($this->subscription);

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

        // set mail server to null
        $hostingDeployment->mailOnlyServer()->disassociate();

        // set provider to Placeholder
        $mailOnlyProvider = $this->providerRepository->getByType(
            ProviderType::MAILONLY,
            ProviderSlug::PLACEHOLDER,
        );
        $hostingDeployment->mailProvider()->associate($mailOnlyProvider);

        $hostingDeployment->save();

        $this->subscription->domain = $this->originalSubscriptionDomain;
        $this->subscription->save();
    }

    private function fetchAndVerifyMailOnlyInstance(
        HostingMigrationPayload $payload,
        Subscription $subscription,
        MigratedCustomer $migratedCustomer,
        Server $server,
    ): void {
        $this->mailOnlyDto = $this->hostingInstanceFetchAction->execute(
            subscription: $subscription,
            migratedCustomer: $migratedCustomer,
            payload: $payload,
            server: $server,
            jobUuid: $this->getJobId(),
        );

        if ($payload->hostingDetails instanceof PleskHostingDetails) {
            $this->hostingCanGenerateSSOAction->execute(
                subscription: $subscription,
                migratedCustomer: $migratedCustomer,
                payload: $payload,
                server: $server,
                jobUuid: $this->getJobId(),
            );
        }

        $this->hostingInstanceIsResellerAction->execute(
            payload: $payload,
            migratedCustomer: $migratedCustomer,
            server: $server,
            siteConfig: $this->mailOnlyDto,
            jobUuid: $this->getJobId(),
        );
    }

    private function migrateMailOnly(
        MigratedCustomer $migratedCustomer,
        HostingDeployment $hostingDeployment,
        Server $server,
        Provider $mailOnlyProvider,
        HostingMigrationPayload $payload,
    ): void {
        $this->configureMailOnlyDeploymentAction->execute(
            hostingDeployment: $hostingDeployment,
            migratedCustomer: $migratedCustomer,
            server: $server,
            mailOnlyProvider: $mailOnlyProvider,
            hostingDetails: $payload->hostingDetails,
            jobUuid: $this->getJobId(),
        );

        if ($hostingDeployment->subscription->domain === null) {
            // Set the default domain configured on the remote hosting entity.
            $this->hostingDeploymentSetDefaultDomainAction->execute(
                hostingDeployment: $hostingDeployment,
                migratedCustomer: $migratedCustomer,
                payload: $payload,
                server: $server,
                jobUuid: $this->getJobId(),
            );
        }

        $this->allowAllDirectAdminFeatureSetAction->execute(
            hostingDeployment: $hostingDeployment,
            migratedCustomer: $migratedCustomer,
            server: $server,
            mailOnlyProvider: $mailOnlyProvider,
            hostingDetails: $payload->hostingDetails,
            jobUuid: $this->getJobId(),
        );

        $this->modifyHostingDnsSettingsAction->execute(
            subscription: $hostingDeployment->subscription,
            migratedCustomer: $migratedCustomer,
            server: $server,
            payload: $payload,
            siteDto: $this->mailOnlyDto,
            jobId: $this->getJobId(),
        );
    }

    private function getMailProvider(
        HostingMigrationPayload $payload,
        Product $product,
    ): Provider {
        if ($payload->hostingDetails instanceof PleskHostingDetails) {
            $providerType = ProviderType::HOSTING;
        } else {
            $providerType = $this->productSpecRepository->booleanSpecificationIsTrue(
                $product,
                ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER,
            )
                ? ProviderType::MAILONLY
                : ProviderType::HOSTING;
        }

        return $this->providerRepository->getByType(
            $providerType,
            ProviderSlug::from($payload->driver),
        );
    }
}
