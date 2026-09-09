<?php

declare(strict_types=1);

namespace Waterfront\Domain\Backup\Jobs;

use Carbon\CarbonImmutable;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Backup\Actions\ChangeBackupAction;
use Waterfront\Domain\Subscriptions\DTO\SubscriptionChangeResult;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionChangeStatus;
use Waterfront\Domain\Subscriptions\Exceptions\SubscriptionChangeException;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Models\SubscriptionChange;
use Waterfront\Domain\Subscriptions\Models\SubscriptionMutation;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class UpgradeBackupJob extends AbstractQueueableJob
{
    public int $tries = 3;

    public function __construct(
        private readonly Subscription $subscription,
        private readonly SubscriptionMutation $subscriptionMutation,
        private readonly SubscriptionChange $subscriptionChange,
    ) {
        parent::__construct();
    }

    /**
     * @throws SubscriptionChangeException
     */
    public function handle(
        ChangeBackupAction $changeBackupAction,
        LoggerInterface $logger,
    ): void {
        $logger->info(
            'Start upgrading backup with subscription: {subscription.uuid}',
            [
                LoggingContextKeys::SUBSCRIPTION_UUID => $this->subscription->uuid,
                LoggingContextKeys::META => [
                    'subscription_change_id' => $this->subscriptionChange->id,
                    'subscription_mutation_id' => $this->subscriptionMutation->id,
                ],
            ]
        );

        $this->subscriptionChange->status = SubscriptionChangeStatus::INPROGRESS;
        $this->subscriptionChange->save();

        $this->subscriptionMutation->processed_technical_at = CarbonImmutable::now();
        $this->subscriptionMutation->save();

        $result = $changeBackupAction->execute($this->subscription, $this->subscriptionChange);

        if ($result->status !== SubscriptionChangeResult::STATUS_OK) {
            $this->subscriptionChange->status = SubscriptionChangeStatus::EXECUTION_FAILED;
            $this->subscriptionChange->failure_code = $result->errorCode;
            $this->subscriptionChange->failure_message = $result->errorMessage;
            $this->subscriptionChange->save();

            throw new SubscriptionChangeException(
                sprintf(
                    'Upgrade Backup failed with status %s',
                    $result->status,
                )
            );
        }

        $this->subscriptionChange->status = SubscriptionChangeStatus::COMPLETED;
        $this->subscriptionChange->completed_at = CarbonImmutable::now();
        $this->subscriptionChange->save();
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::DEFAULT;
    }
}
