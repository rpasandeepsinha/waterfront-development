<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Services;

use Illuminate\Contracts\Bus\Dispatcher as JobDispatcher;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Backup\Events\BackupTerminateEvent;
use Waterfront\Domain\DNS\Events\TerminateDnsZoneEvent;
use Waterfront\Domain\Domains\Events\DomainTerminated;
use Waterfront\Domain\Domains\Jobs\DisableDomainAutoRenewal as DisableDomainAutoRenewalJob;
use Waterfront\Domain\Hosting\Events\TerminateHosting;
use Waterfront\Domain\Hosting\Jobs\TerminateRedirectsJob;
use Waterfront\Domain\MailManagement\Events\TerminateMailOnlyHosting;
use Waterfront\Domain\ManualProvisioning\Events\DispatchTerminateManualProvisioning;
use Waterfront\Domain\Microsoft365\Events\TerminateMicrosoft365;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Jobs\DetachVolumeDiscount;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\ResellerHosting\Jobs\TerminateResellerHostingJob;
use Waterfront\Domain\Sitebuilder\Events\TerminateSitebuilderHosting;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\VPS\Events\VpsTerminateEvent;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Jobs\AbstractQueueableJob;
use Webmozart\Assert\Assert;

readonly class DeprovisionService
{
    public function __construct(
        private Dispatcher $dispatcher,
        private JobDispatcher $jobDispatcher,
        private LoggerInterface $logger,
    ) {
    }

    public function deprovision(Subscription $subscription): void
    {
        $this->logger->info(
            'Deprovisioning for subscription {domain.name} ({subscription.uuid})',
            [
                LoggingContextKeys::DOMAIN_NAME => $subscription->domain ?? '',
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
            ],
        );

        if ($subscription->technical_status === TechnicalStatus::DELETED->value) {
            $this->logger->info(
                sprintf(
                    'Deprovisioning subscription skipped for subscription %s (%s) because it is already deleted',
                    $subscription->domain ?? '',
                    $subscription->uuid,
                ),
            );

            return;
        }

        $dispatchable = match ($subscription->product->productGroup->slug) {
            ProductGroupType::EXTENSION => $this->deprovisionExtension($subscription),
            ProductGroupType::REDIRECT => $this->deprovisionRedirects($subscription),
            ProductGroupType::HOSTING => $this->deprovisionHosting($subscription),
            ProductGroupType::DNS => $this->deprovisionDns($subscription),
            ProductGroupType::SSL => $this->deprovisionSsl($subscription),
            ProductGroupType::RESELLER_HOSTING => $this->deprovisionResellerHosting($subscription),
            ProductGroupType::CLOUDSTACK_VIRTUAL_MACHINE, ProductGroupType::VPS => $this->deprovisionVps($subscription),
            ProductGroupType::CLOUDSTACK_VOLUME => $this->deprovisionCloudStackVolume($subscription),
            ProductGroupType::MANUAL_SUBSCRIPTION => new DispatchTerminateManualProvisioning($subscription),
            ProductGroupType::VOLUME_DISCOUNT => new DetachVolumeDiscount(
                $subscription->customer,
                $subscription->product,
            ),
            ProductGroupType::MICROSOFT_365 => new TerminateMicrosoft365($subscription),
            ProductGroupType::BACKUP => $this->deprovisionBackup($subscription),
            ProductGroupType::OTHER,
            ProductGroupType::DOMAIN_EXPANSION,
            ProductGroupType::CLOUDSTACK_MANAGER_DOMAIN,
            ProductGroupType::CLOUDSTACK_OS,
            ProductGroupType::ONE_TIME_SERVICE,
            ProductGroupType::RESELLER_DISCOUNT,
            ProductGroupType::ADD_ON,
                => null,
        };

        if ($dispatchable === null) {
            return;
        }

        if ($dispatchable instanceof AbstractQueueableJob) {
            $this->jobDispatcher->dispatch($dispatchable);

            return;
        }

        $this->dispatcher->dispatch($dispatchable);
    }

    private function deprovisionDns(Subscription $subscription): TerminateDnsZoneEvent
    {
        Assert::notNull($subscription->domain);

        return new TerminateDnsZoneEvent(
            $subscription->uuid,
            $subscription->domain,
            $subscription->product->uuid,
        );
    }

    private function deprovisionSsl(Subscription $subscription): null
    {
        $subscription->sslDeployment?->delete();

        return null;
    }

    private function deprovisionCloudStackVolume(Subscription $subscription): null
    {
        $subscription->cloudStackVolumeDeployment?->delete();
        $subscription->technical_status = TechnicalStatus::DELETED->value;
        $subscription->save();

        return null;
    }

    private function deprovisionExtension(Subscription $subscription): DomainTerminated|AbstractQueueableJob
    {
        if (
            $subscription->domainDeployment !== null
            && $subscription->technical_status !== TechnicalStatus::DELETED->value
        ) {
            return new DisableDomainAutoRenewalJob($subscription->domainDeployment);
        }

        return new DomainTerminated($subscription);
    }

    private function deprovisionVps(Subscription $subscription): ?VpsTerminateEvent
    {
        if ($subscription->cloudStackVirtualMachineDeployment === null) {
            $this->logger->warning('VPS deployment already deleted, skipping termination event dispatch.', [
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
            ]);

            $subscription->technical_status = TechnicalStatus::DELETED->value;
            $subscription->save();

            return null;
        }

        $subscription->technical_status = TechnicalStatus::DELETING->value;
        $subscription->save();

        return new VpsTerminateEvent($subscription->cloudStackVirtualMachineDeployment);
    }

    private function deprovisionHosting(Subscription $subscription): TerminateSitebuilderHosting|TerminateMailOnlyHosting|TerminateHosting|null
    {
        if ($subscription->hostingDeployment === null) {
            $this->logger->warning(
                sprintf(
                    'Missing technical hosting deployment for %s (%d)',
                    $subscription->domain ?? '',
                    $subscription->id,
                ),
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                ],
            );
        }

        if ($subscription->hostingDeployment === null) {
            $subscription->technical_status = TechnicalStatus::DELETED->value;
            $subscription->save();

            return null;
        }

        return match (true) {
            $subscription->product->isSitebuilderProduct() => new TerminateSitebuilderHosting(
                $subscription->customer->email,
                $subscription->customer->name,
                $subscription,
            ),
            $subscription->product->isMailOnlyServer() => new TerminateMailOnlyHosting(
                $subscription->customer->email,
                $subscription->customer->name,
                $subscription,
            ),
            default => new TerminateHosting(
                $subscription->hostingDeployment,
                $subscription->technical_status,
            ),
        };
    }

    private function deprovisionRedirects(Subscription $subscription): AbstractQueueableJob
    {
        $subscription->technical_status = TechnicalStatus::DELETING->value;
        $subscription->save();

        return new TerminateRedirectsJob(
            $subscription,
        );
    }

    private function deprovisionResellerHosting(Subscription $subscription): ?TerminateResellerHostingJob
    {
        if ($subscription->resellerHostingDeployment === null) {
            $this->logger->warning(
                sprintf(
                    'Missing reseller hosting deployment for %s (%d)',
                    $subscription->uuid,
                    $subscription->id,
                ),
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                ],
            );

            return null;
        }

        return new TerminateResellerHostingJob(
            $subscription->customer->name,
            $subscription->customer->email,
            $subscription->resellerHostingDeployment,
        );
    }

    private function deprovisionBackup(Subscription $subscription): BackupTerminateEvent
    {
        $subscription->technical_status = TechnicalStatus::DELETING->value;
        $subscription->save();

        return new BackupTerminateEvent(
            subscription: $subscription,
        );
    }
}
