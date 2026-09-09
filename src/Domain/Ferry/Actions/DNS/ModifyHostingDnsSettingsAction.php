<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Actions\DNS;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bus\PendingClosureDispatch;
use Illuminate\Queue\CallQueuedClosure;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Ferry\Actions\Hosting\HostingIsUsingServerHostnameAsNameservers;
use Waterfront\Domain\Ferry\Actions\Hosting\HostingModifySiteForMigrationAction;
use Waterfront\Domain\Ferry\Dto\Hosting\HostingMigrationPayload;
use Waterfront\Domain\Hosting\Interfaces\Hosting\SiteConfigInterface;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;

class ModifyHostingDnsSettingsAction
{
    public const int DNS_DELAY = 7200;

    /** @var array<int, string> */
    private readonly array $checksDnsSettings;

    public function __construct(
        private readonly HostingIsUsingServerHostnameAsNameservers $hostingIsUsingServerHostnameAsNameservers,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly LoggerInterface $logger,
        ConfigurationInterface $config,
    ) {
        /** @var array<int, string> $checksDnsSettings */
        $checksDnsSettings = $config->getAsArray('ferry-domain.validation_checks_dns');

        $this->checksDnsSettings = $checksDnsSettings;
    }

    public function execute(
        Subscription $subscription,
        MigratedCustomer $migratedCustomer,
        Server $server,
        HostingMigrationPayload $payload,
        SiteConfigInterface $siteDto,
        string $jobId,
    ): void {
        if (! in_array($payload->driver, $this->checksDnsSettings, true)) {
            // Nothing to do
            return;
        }

        // Subscription now has a real and COMPLETE backend coupling instead of the default placeholder setup.
        $domain = $this->resolveDomain($subscription);

        $isUsingServerHostnameAsNameserver = $this->hostingIsUsingServerHostnameAsNameservers
            ->execute(
                payload: $payload,
                migratedCustomer: $migratedCustomer,
                server: $server,
                siteConfig: $siteDto,
                jobUuid: $jobId
            );

        $isUsingLocalDomain = $this->subscriptionRepository
            ->subscriptionExistsForCustomerIdAndDomainForType(
                customerId: $subscription->customer->id,
                domain: $domain,
                productGroupType: ProductGroupType::EXTENSION
            );

        // See the comments made in the HostingMigrationPipe recordDnsState function or
        // https://lucid.app/lucidchart/40243863-34db-44f7-8dbb-d43d8357e0a3
        // Turn off DNS controls for the hosting migrations as the package will now be using either external DNS
        // or in the FUTURE Pdns through Coast.

        $this->delayHostingModification(
            subscription: $subscription,
            migratedCustomer: $migratedCustomer,
            payload: $payload,
            isUsingServerHostnameAsNameserver: $isUsingServerHostnameAsNameserver,
            isUsingLocalDomain: $isUsingLocalDomain,
            jobId: $jobId,
        );
    }

    private function resolveDomain(Subscription $subscription): string
    {
        $subscription->refresh();
        return is_string($subscription->domain)
            ? $subscription->domain
            : '';
    }

    private function delayHostingModification(
        Subscription $subscription,
        MigratedCustomer $migratedCustomer,
        HostingMigrationPayload $payload,
        bool $isUsingServerHostnameAsNameserver,
        bool $isUsingLocalDomain,
        string $jobId,
    ): void {
        $subscription->refresh();

        if (
            ! $subscription->hostingDeployment?->provider instanceof Provider
            || ! $subscription->hostingDeployment->server instanceof  Server
        ) {
            $this->logger->error(
                'Dns disabling unable to be executed as no hosting provider or normal server has been configured',
                [
                    LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                    LoggingContextKeys::CUSTOMER_ID => $subscription->customer_id,
                    LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $migratedCustomer->id,
                    LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                    LoggingContextKeys::QUEUE_JOB_ID => $jobId,
                ]
            );
            return;
        }

        $task = function () use (
            $subscription,
            $migratedCustomer,
            $payload,
            $jobId,
            $isUsingServerHostnameAsNameserver,
            $isUsingLocalDomain
        ) {
            $hostingModifySiteForMigrationAction = Application::getInstance()->get(HostingModifySiteForMigrationAction::class);

            $hostingModifySiteForMigrationAction->execute(
                subscription: $subscription,
                migratedCustomer: $migratedCustomer,
                hostingMigrationPayload: $payload,
                jobUuid: $jobId,
                dnsSetting: $isUsingServerHostnameAsNameserver && ! $isUsingLocalDomain,
                ssoSetting: true,
                isUsingHostingServersAsNameserver: $isUsingServerHostnameAsNameserver,
                isUsingLocalDomain: $isUsingLocalDomain
            );
        };

        new PendingClosureDispatch(CallQueuedClosure::create($task))
            ->name('HostingModifySiteForMigrationAction')
            ->delay(self::DNS_DELAY) // two hours
            ->onQueue(QueueName::FERRY);
    }
}
