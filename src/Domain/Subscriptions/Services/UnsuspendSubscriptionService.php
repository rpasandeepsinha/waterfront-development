<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Bus\Dispatcher;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\AuditLogs\Actions\StoreAuditLogAction;
use Waterfront\Domain\AuditLogs\Enums\AuditLogEvent;
use Waterfront\Domain\Backup\Jobs\UnsuspendBackupJob;
use Waterfront\Domain\Domains\Jobs\UnsuspendDomainJob;
use Waterfront\Domain\Hosting\Jobs\UnsuspendHostingJob;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Redirects\Jobs\UnsuspendRedirectJob;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Exceptions\UnableToSuspendSubscriptionException;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Exceptions\NotImplementedException;

class UnsuspendSubscriptionService
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
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function execute(Subscription $subscription): void
    {
        $this->logger->info(
            'Subscription with uuid: {subscription.uuid} unsuspension is initiated',
            [
                LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
            ],
        );

        $this->checkIfSubscriptionIsEligible($subscription);

        /** @var Subscription $subscription */
        $subscription = $subscription->loadMissing(['product.productGroup']);

        $subscription->administrative_status = $subscription->cancel_date !== null
            ? AdministrativeStatus::CANCELED->value
            : AdministrativeStatus::ACTIVE->value;
        $subscription->suspended_at = null;
        $subscription->save();

        $this->storeAuditLogAction->execute(
            AuditLogEvent::UNSUSPENSION,
            Subscription::class,
            $subscription->id,
        );

        if (! $this->technicalUnsuspendSupported($subscription)) {
            return;
        }

        $subscription->refresh();
        $this->logger->info(
            'Creating job for subscription with uuid: {subscription.uuid} unsuspension',
            [
                LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
            ],
        );

        match ($subscription->product->productGroup->slug) {
            ProductGroupType::BACKUP => $this->dispatchUnsuspendBackupJob($subscription),
            ProductGroupType::REDIRECT => $this->dispatchUnsuspendRedirectJob($subscription),
            ProductGroupType::EXTENSION => $this->dispatchUnsuspendDomainJob($subscription),
            ProductGroupType::HOSTING => $this->dispatchUnsuspendHostingJob($subscription),
            default => throw new NotImplementedException(sprintf(
                'Products with product group "%s" can not be unsuspended.',
                $subscription->product->productGroup->slug->value,
            )),
        };
    }

    /**
     * @throws UnableToSuspendSubscriptionException
     */
    public function checkIfSubscriptionIsEligible(Subscription $subscription): void
    {
        if (
            $subscription->administrative_status !== AdministrativeStatus::SUSPENDED->value
            && $subscription->administrative_status !== AdministrativeStatus::EXPIRED->value
        ) {
            throw UnableToSuspendSubscriptionException::subscriptionAdministrativeOrTechnicalStatusNotSufficient(
                AuditLogEvent::UNSUSPENSION,
                $subscription,
            );
        }
    }

    public function technicalUnsuspendSupported(Subscription $subscription): bool
    {
        if (is_null($subscription->technical_status)) {
            return false;
        }

        if (
            $subscription->technical_status !== TechnicalStatus::SUSPENDED->value
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

    private function dispatchUnsuspendBackupJob(Subscription $subscription): void
    {
        $this->jobDispatcher->dispatch(new UnsuspendBackupJob($subscription));
    }

    private function dispatchUnsuspendRedirectJob(Subscription $subscription): void
    {
        $this->jobDispatcher->dispatch(new UnsuspendRedirectJob($subscription));
    }

    private function dispatchUnsuspendDomainJob(Subscription $subscription): void
    {
        if ($subscription->domainDeployment === null) {
            return;
        }

        $this->jobDispatcher->dispatch(new UnsuspendDomainJob($subscription->domainDeployment));
    }

    private function dispatchUnsuspendHostingJob(Subscription $subscription): void
    {
        if ($subscription->hostingDeployment === null) {
            return;
        }

        $this->jobDispatcher->dispatch(
            new UnsuspendHostingJob($subscription->hostingDeployment, sendEmailOnSuccess: true),
        );
    }
}
