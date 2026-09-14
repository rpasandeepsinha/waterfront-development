<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Services;

use Illuminate\Contracts\Bus\Dispatcher;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\AuditLogs\Actions\StoreAuditLogAction;
use Waterfront\Domain\AuditLogs\Enums\AuditLogEvent;
use Waterfront\Domain\Backup\Jobs\SuspendBackupJob;
use Waterfront\Domain\Domains\Jobs\SuspendDomainJob;
use Waterfront\Domain\Hosting\Jobs\SuspendHostingJob;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Redirects\Jobs\SuspendRedirectJob;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Exceptions\UnableToSuspendSubscriptionException;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Exceptions\NotImplementedException;

class SuspendSubscriptionService
{
    public function __construct(
        private readonly StoreAuditLogAction $storeAuditLogAction,
        private readonly Dispatcher $jobDispatcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     *
     * @throws UnableToSuspendSubscriptionException
     * @throws NotImplementedException
     */
    public function execute(Subscription $subscription): void
    {
        $this->logger->info(
            'Subscription with uuid: {subscription.uuid} suspension is initiated',
            [
                LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
            ],
        );

        $this->checkIfSubscriptionIsEligible($subscription);

        /** @var Subscription $subscription */
        $subscription = $subscription->loadMissing(['product.productGroup']);

        $subscription->administrative_status = AdministrativeStatus::SUSPENDED->value;
        $subscription->save();
        $subscription->refresh();

        $this->storeAuditLogAction->execute(
            AuditLogEvent::SUSPENSION,
            Subscription::class,
            $subscription->id,
        );

        if (! $this->technicalSuspendSupported($subscription)) {
            return;
        }

        $this->logger->info(
            'Creating job for subscription with uuid: {subscription.uuid} suspension',
            [
                LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
            ],
        );
        match ($subscription->product->productGroup->slug) {
            ProductGroupType::BACKUP => $this->dispatchSuspendBackup($subscription),
            ProductGroupType::REDIRECT => $this->dispatchSuspendRedirectJob($subscription),
            ProductGroupType::EXTENSION => $this->dispatchSuspendDomainJob($subscription),
            ProductGroupType::HOSTING => $this->dispatchSuspendHostingJob($subscription),
            default => throw new NotImplementedException(sprintf(
                'Products with product group "%s" can not be suspended.',
                $subscription->product->productGroup->slug->value,
            )),
        };
    }

    /**
     * @throws UnableToSuspendSubscriptionException
     */
    public function checkIfSubscriptionIsEligible(Subscription $subscription): void
    {
        if (in_array($subscription->administrative_status, AdministrativeStatus::getIneligibleForSuspension(), true)) {
            throw UnableToSuspendSubscriptionException::subscriptionAdministrativeOrTechnicalStatusNotSufficient(
                AuditLogEvent::SUSPENSION,
                $subscription,
            );
        }
    }

    public function technicalSuspendSupported(Subscription $subscription): bool
    {
        if (is_null($subscription->technical_status)) {
            return false;
        }

        if (
            in_array(
                TechnicalStatus::from($subscription->technical_status),
                TechnicalStatus::getInEligibleForSuspension(),
                true,
            )
            || ! in_array(
                $subscription->product->productGroup->slug,
                [
                    ProductGroupType::EXTENSION,
                    ProductGroupType::HOSTING,
                    ProductGroupType::BACKUP,
                    ProductGroupType::REDIRECT,
                ],
                true,
            )
        ) {
            return false;
        }

        return true;
    }

    private function dispatchSuspendBackup(Subscription $subscription): void
    {
        $this->jobDispatcher->dispatch(new SuspendBackupJob($subscription));
    }

    private function dispatchSuspendRedirectJob(Subscription $subscription): void
    {
        $this->jobDispatcher->dispatch(new SuspendRedirectJob($subscription));
    }

    private function dispatchSuspendDomainJob(Subscription $subscription): void
    {
        if ($subscription->domainDeployment === null) {
            return;
        }

        $this->jobDispatcher->dispatch(
            new SuspendDomainJob(
                domainDeployment: $subscription->domainDeployment,
                sendMailAfterSuspensionSuccess: true,
            ),
        );
    }

    private function dispatchSuspendHostingJob(Subscription $subscription): void
    {
        if ($subscription->hostingDeployment === null) {
            return;
        }

        $this->jobDispatcher->dispatch(
            new SuspendHostingJob($subscription->hostingDeployment, sendMailAfterSuspensionSuccess: true),
        );
    }
}
