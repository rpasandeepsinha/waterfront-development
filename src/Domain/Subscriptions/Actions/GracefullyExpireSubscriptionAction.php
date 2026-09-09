<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Bus\Dispatcher;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\AuditLogs\Actions\StoreAuditLogAction;
use Waterfront\Domain\AuditLogs\Enums\AuditLogEvent;
use Waterfront\Domain\Domains\Jobs\DisableDomainAutoRenewal;
use Waterfront\Domain\Domains\Jobs\SuspendDomainJob;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Hosting\Jobs\SuspendHostingJob;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\ProductSpec;
use Waterfront\Domain\Products\Repositories\ProductSpecRepository;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\SubscriptionChangeService;
use Waterfront\Support\Enums\LoggingContextKeys;

class GracefullyExpireSubscriptionAction
{
    public function __construct(
        private readonly SaveSubscriptionAdministrativeStatusAction $saveSubscriptionAdministrativeStatusAction,
        private readonly SaveSubscriptionTerminationDateAction $setSubscriptionTerminationDateAction,
        private readonly Dispatcher $jobDispatcher,
        private readonly ProductSpecRepository $productSpecRepository,
        private readonly LoggerInterface $logger,
        private readonly StoreAuditLogAction $storeAuditLogAction,
        private readonly SubscriptionChangeService $changeService,
    ) {
    }

    public function execute(Subscription $subscription): void
    {
        $this->logger->info('Expiring subscription {subscription.id}: {domain.name}', [
            LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
            LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
        ]);

        $terminationDate = new CarbonImmutable();
        $gracePeriodDays = $this->getGracePeriodInDays($subscription);

        if ($gracePeriodDays > 0) {
            $this->logger->info(sprintf('Grace period of %d days detected for subscription {subscription.id}: {domain.name} ', $gracePeriodDays), [
                LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
            ]);
            $terminationDate = new CarbonImmutable()->addDays($gracePeriodDays);
            $this->suspendDeployment($subscription);
        }

        $this->saveSubscriptionAdministrativeStatusAction->execute($subscription, AdministrativeStatus::EXPIRED);
        $this->setSubscriptionTerminationDateAction->execute($subscription, $terminationDate);
    }

    private function suspendDeployment(Subscription $subscription): void
    {
        $productGroupType = $subscription->product->productGroup->slug;

        match ($productGroupType) {
            ProductGroupType::EXTENSION => $this->dispatchSuspendAndDisableAutoRenewalJobs($subscription),
            ProductGroupType::HOSTING => $this->dispatchSuspendHostingJob($subscription),
            ProductGroupType::DNS,
            ProductGroupType::OTHER,
            ProductGroupType::DOMAIN_EXPANSION,
            ProductGroupType::SSL,
            ProductGroupType::RESELLER_DISCOUNT,
            ProductGroupType::RESELLER_HOSTING,
            ProductGroupType::REDIRECT,
            ProductGroupType::CLOUDSTACK_VIRTUAL_MACHINE,
            ProductGroupType::CLOUDSTACK_VOLUME,
            ProductGroupType::CLOUDSTACK_OS,
            ProductGroupType::MICROSOFT_365,
            ProductGroupType::MANUAL_SUBSCRIPTION,
            ProductGroupType::ADD_ON,
            ProductGroupType::VPS,
            ProductGroupType::VOLUME_DISCOUNT,
            ProductGroupType::CLOUDSTACK_MANAGER_DOMAIN,
            ProductGroupType::BACKUP,
            ProductGroupType::ONE_TIME_SERVICE => $this->setSuspendedState($subscription),
        };
    }

    private function setSuspendedState(Subscription $subscription): void
    {
        $this->logger->info(
            sprintf(
                "'suspending' subscription {subscription.id}: {domain.name} with productGroup: %s",
                $subscription->product->productGroup->slug->value
            ),
            [
                LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
            ]
        );

        $subscription->technical_status = TechnicalStatus::SUSPENDED->value;
        $subscription->suspended_at = CarbonImmutable::now();
        $subscription->save();

        $this->storeAuditLogAction->execute(
            AuditLogEvent::SUSPENSION,
            Subscription::class,
            $subscription->id,
        );
    }

    private function dispatchSuspendAndDisableAutoRenewalJobs(Subscription $subscription): void
    {
        $deployment = $subscription->domainDeployment;
        if ($deployment === null) {
            $this->setSuspendedState($subscription);
            return;
        }

        $this->jobDispatcher->dispatch($this->getDisableRenewalJob($deployment));
        $this->jobDispatcher->dispatch($this->getSuspendDomainJob($deployment));
    }

    private function getSuspendDomainJob(DomainDeployment $deployment): SuspendDomainJob
    {
        return new SuspendDomainJob(
            domainDeployment: $deployment,
            sendMailAfterSuspensionSuccess: false
        );
    }

    private function getDisableRenewalJob(DomainDeployment $deployment): DisableDomainAutoRenewal
    {
        return new DisableDomainAutoRenewal($deployment);
    }

    private function dispatchSuspendHostingJob(Subscription $subscription): void
    {
        $deployment = $subscription->hostingDeployment;
        if ($deployment === null) {
            $this->setSuspendedState($subscription);
            return;
        }
        $this->jobDispatcher->dispatch(new SuspendHostingJob($deployment, sendMailAfterSuspensionSuccess: false));
    }

    /**
     * Trying to find the graceperiod for a subscription, if given subscription is a child we attempt to find a graceperiod
     * for given child, if nothing has been found we attempt to find it via the parent. If that results in nothing we
     * simply return 0 because there is no grace period set.
     */
    private function getGracePeriodInDays(Subscription $subscription): int
    {
        if ($this->changeService->shouldDowngradeSubscriptionWithParent($subscription)) {
            return 0;
        }

        if ($subscription->parent !== null) {
            $period = $this->productSpecRepository->findBySpecification($subscription->product, 'services.technical_grace_period');

            if (! $period instanceof ProductSpec) {
                $period = $this->productSpecRepository->findBySpecification($subscription->parent->product, 'services.technical_grace_period');
            }
        } else {
            $period = $this->productSpecRepository->findBySpecification($subscription->product, 'services.technical_grace_period');
        }

        return $period instanceof ProductSpec ? (int) $period->value : 0;
    }
}
