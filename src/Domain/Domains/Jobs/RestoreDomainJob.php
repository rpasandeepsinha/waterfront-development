<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Jobs;

use Carbon\CarbonImmutable;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\Exceptions\EnableAutorenewalFailedException;
use Waterfront\Domain\Domains\Exceptions\FetchDomainException;
use Waterfront\Domain\Domains\Exceptions\RestoreDomainException;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class RestoreDomainJob extends AbstractQueueableJob
{
    public int $tries = 4;

    public function __construct(
        private readonly DomainDeployment $domainDeployment,
    ) {
        parent::__construct();
    }

    /**
     * @throws RestoreDomainException
     * @throws FetchDomainException
     * @throws EnableAutorenewalFailedException
     */
    public function handle(
        DomainService $domainService,
        LoggerInterface $logger,
    ): void {
        $subscription = $this->domainDeployment->subscription;
        $this->updateSubscriptionTechnicalStatus($subscription, TechnicalStatus::UNSUSPENDING->value);
        $domainDeployment = $this->domainDeployment;
        $provider = $domainDeployment->provider->slug;

        if (is_null($subscription->domain)) {
            throw new RestoreDomainException(
                sprintf('domain not filled for subscription with id %s', $subscription->id)
            );
        }

        try {
            $firstDomainCheck = $domainService->fetchDomain($subscription->domain, $provider);
        } catch (FetchDomainException $exception) {
            $logger->error(
                'fetch domain information before restore for subscription {subscription.id}: {domain.name} failed.',
                [
                    LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                    LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                    LoggingContextKeys::EXCEPTION => $exception,
                ]
            );
            $this->updateSubscriptionTechnicalStatus($subscription, TechnicalStatus::FAILED->value);
            $this->storeLastResultOnDeployment($domainDeployment, $exception->getMessage());
            throw $exception;
        }

        if ($firstDomainCheck->autoRenew && in_array(strtoupper(TechnicalStatus::OK->value), $firstDomainCheck->status, true)) {
            $this->updateSubscriptionTechnicalStatus($subscription, TechnicalStatus::OK->value);
            return;
        } elseif (! $firstDomainCheck->autoRenew && in_array(strtoupper(TechnicalStatus::OK->value), $firstDomainCheck->status, true)) {
            try {
                $domainService->enableAutoRenewal($subscription->domain, $provider);
                $this->updateSubscriptionTechnicalStatus($subscription, TechnicalStatus::OK->value);
                return;
            } catch (EnableAutorenewalFailedException $exception) {
                $logger->error(
                    'enabling auto renew for already active domain subscription {subscription.id}: {domain.name} failed',
                    [
                        LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                        LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                        LoggingContextKeys::EXCEPTION => $exception,
                    ]
                );
                $this->updateSubscriptionTechnicalStatus($subscription, TechnicalStatus::FAILED->value);
                $this->storeLastResultOnDeployment($domainDeployment, $exception->getMessage());
                throw $exception;
            }
        }

        try {
            $domainService->restore($subscription->domain, $provider);
        } catch (RestoreDomainException $exception) {
            $logger->error('Restoring domain for subscription {subscription.id}: {domain.name} failed', [
                LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                LoggingContextKeys::EXCEPTION => $exception,
            ]);
            $this->updateSubscriptionTechnicalStatus($subscription, TechnicalStatus::FAILED->value);
            $this->storeLastResultOnDeployment($domainDeployment, $exception->getMessage());

            throw new RestoreDomainException($exception->getMessage(), $exception->getCode(), $exception);
        }

        try {
            $afterRestoreDomainCheck = $domainService->fetchDomain($subscription->domain, $provider);
        } catch (FetchDomainException $exception) {
            $logger->error(
                'fetch domain information after restore for subscription {subscription.id}: {domain.name} failed.',
                [
                    LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                    LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                    LoggingContextKeys::EXCEPTION => $exception,
                ]
            );
            $this->updateSubscriptionTechnicalStatus($subscription, TechnicalStatus::FAILED->value);
            $this->storeLastResultOnDeployment($domainDeployment, $exception->getMessage());
            throw $exception;
        }

        if (! $afterRestoreDomainCheck->autoRenew) {
            try {
                $domainService->enableAutoRenewal($subscription->domain, $provider);
            } catch (EnableAutorenewalFailedException $exception) {
                $logger->error(
                    'enabling auto renew for subscription {subscription.id}: {domain.name} failed',
                    [
                        LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                        LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                        LoggingContextKeys::EXCEPTION => $exception,
                    ]
                );
                $this->updateSubscriptionTechnicalStatus($subscription, TechnicalStatus::FAILED->value);
                $this->storeLastResultOnDeployment($domainDeployment, $exception->getMessage());
                throw $exception;
            }
        }

        $this->updateSubscriptionTechnicalStatus($subscription, TechnicalStatus::OK->value);
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::PARTNER_DOMAIN;
    }

    private function storeLastResultOnDeployment(DomainDeployment $domainDeployment, string $result): void
    {
        $domainDeployment->last_result = $result;
        $domainDeployment->last_result_received = CarbonImmutable::now();
        $domainDeployment->save();
    }

    private function updateSubscriptionTechnicalStatus(Subscription $subscription, string $technicalStatus): void
    {
        $subscription->technical_status = $technicalStatus;
        $subscription->save();
    }
}
