<?php

declare(strict_types=1);

namespace Waterfront\Apps\OneOffScripts\Domain\Jobs;

use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

abstract class RepairFailedDomainSubscriptionJob extends AbstractQueueableJob
{
    public function __construct(
        protected Subscription $subscription,
        protected bool $dryRun,
        protected string $triggeredBy,
    ) {
        parent::__construct();
    }

    public function failed(Throwable $throwable): void
    {
        Log::error(
            sprintf('[%s] failed for failed domain subscription.', basename(str_replace('\\', '/', static::class))),
            array_merge(
                $this->buildBaseLogContext(),
                [
                    LoggingContextKeys::EXCEPTION => $throwable,
                ],
            ),
        );
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::DEFAULT;
    }

    /**
     * @return array<string, int|string|null>
     */
    protected function buildBaseLogContext(): array
    {
        return [
            LoggingContextKeys::ONE_OFF_SCRIPT => $this->triggeredBy,
            LoggingContextKeys::SUBSCRIPTION_UUID => $this->subscription->uuid,
            LoggingContextKeys::DOMAIN_NAME => $this->subscription->domain,
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    protected function buildLogContext(?DomainDeployment $domainDeployment = null): array
    {
        $logContext = $this->buildBaseLogContext();

        if ($domainDeployment instanceof DomainDeployment) {
            $logContext[LoggingContextKeys::PROVISIONING_ID] = $domainDeployment->id;
        }

        return $logContext;
    }

    /**
     * @param array<string, mixed> $meta
     */
    protected function logDryRunOrExecuting(
        LoggerInterface $logger,
        string $dryRunMessage,
        string $executingMessage,
        ?DomainDeployment $domainDeployment = null,
        array $meta = [],
    ): void {
        $logger->info(
            message: $this->dryRun ? $dryRunMessage : $executingMessage,
            context: $this->buildLogContext($domainDeployment) + [
                LoggingContextKeys::META => $meta + [
                    'dry_run' => $this->dryRun,
                ],
            ],
        );
    }
}
