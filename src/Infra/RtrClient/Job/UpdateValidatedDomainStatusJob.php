<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\Job;

use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Domains\Repositories\DomainDeploymentRepository;
use Waterfront\Domain\Domains\Services\DomainProviderHistory;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\RtrClient\Models\RtrResponseLog;
use Waterfront\Infra\RtrClient\Services\Enums\DomainStatus as RtrDomainStatus;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class UpdateValidatedDomainStatusJob extends AbstractQueueableJob
{
    public int $tries = 3;

    public function __construct(
        public readonly string $domainName,
        private readonly string $message,
        private readonly RtrResponseLog $rtrResponseLog,
    ) {
        parent::__construct();
    }

    public function handle(
        DomainDeploymentRepository $domainDeploymentRepository,
        DomainProviderHistory $domainProviderHistory,
        SubscriptionRepository $subscriptionRepository,
        LoggerInterface $logger,
    ): void {
        $domainDeployment = $domainDeploymentRepository
            ->getActiveDeploymentByDomain($this->domainName);

        if ($domainDeployment === null) {
            $logger->info(
                'RTR validation skipped: active domain deployment not found',
                $this->buildLogContext(null),
            );
            return;
        }

        $domainDeployment->loadMissing([
            'provider',
            'subscription',
        ]);

        if ($domainDeployment->provider->slug !== ProviderSlug::REALTIME_REGISTER) {
            $logger->warning(
                'RTR validation skipped: domain provider is not RTR',
                $this->buildLogContext($domainDeployment),
            );
            return;
        }

        if ($domainDeployment->domain_status === RtrDomainStatus::OK) {
            $logger->info(
                'RTR validation skipped: domain already validated',
                $this->buildLogContext($domainDeployment),
            );
            return;
        }

        if ($domainDeployment->domain_status === RtrDomainStatus::INACTIVE) {
            $logger->info(
                'RTR validation skipped: domain requires nameserver activation',
                $this->buildLogContext($domainDeployment),
            );
            return;
        }

        if ($domainDeployment->domain_status !== RtrDomainStatus::PENDING_VALIDATION) {
            $logger->warning(
                'RTR validation skipped: domain is not pending validation',
                $this->buildLogContext($domainDeployment),
            );
            return;
        }

        $subscription = $domainDeployment->subscription;
        if (
            ! in_array($subscription->technical_status, [
                TechnicalStatus::PENDING->value,
                DomainStatus::ACTIVE->value,
            ], true)
        ) {
            $logger->warning(
                'RTR validation skipped: subscription status cannot be completed',
                $this->buildLogContext($domainDeployment),
            );
            return;
        }

        DB::transaction(function () use ($domainDeployment, $domainProviderHistory, $subscription, $subscriptionRepository, $domainDeploymentRepository): void {
            $domainDeploymentRepository->setDomainStatus($domainDeployment, RtrDomainStatus::OK);

            if ($subscription->technical_status === TechnicalStatus::PENDING->value) {
                $subscriptionRepository->setTechnicalStatus($subscription, DomainStatus::ACTIVE->value);
            }

            $domainProviderHistory->saveHistory(
                $this->rtrResponseLog,
                ProviderSlug::REALTIME_REGISTER,
                $this->domainName,
                DomainStatus::ACTIVE->value,
                $this->message,
            );
        });

        $logger->notice(
            'RTR domain validation completed',
            $this->buildLogContext($domainDeployment),
        );
    }

    /**
     * @return int[]
     */
    public function backoff(): array
    {
        return [5, 30];
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::PARTNER_DOMAIN;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLogContext(?DomainDeployment $domainDeployment): array
    {
        $logContext = [
            LoggingContextKeys::DOMAIN_NAME => $this->domainName,
            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::RTR,
            LoggingContextKeys::META => [
                'rtr_response_log_id' => $this->rtrResponseLog->id,
            ],
        ];

        if ($domainDeployment !== null) {
            $subscription = $domainDeployment->subscription;

            $logContext[LoggingContextKeys::PROVISIONING_ID] = $domainDeployment->id;
            $logContext[LoggingContextKeys::SUBSCRIPTION_ID] = $subscription->id;
            $logContext[LoggingContextKeys::SUBSCRIPTION_UUID] = $subscription->uuid;
            $logContext[LoggingContextKeys::META]['domain.provider'] = $domainDeployment->provider->slug->value;
            $logContext[LoggingContextKeys::META]['domain.status'] = $domainDeployment->domain_status?->value;
            $logContext[LoggingContextKeys::META]['subscription.technical_status'] = $subscription->technical_status;
        }

        return $logContext;
    }
}
