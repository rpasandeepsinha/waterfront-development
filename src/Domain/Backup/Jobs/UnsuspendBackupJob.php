<?php

declare(strict_types=1);

namespace Waterfront\Domain\Backup\Jobs;

use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Email\Actions\SendSubscriptionUnSuspendedMailAction;
use Waterfront\Domain\Provision\Backup\Requests\SetBackupSuspensionStateRequest;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCategory;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\SubscriptionMetadataService;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class UnsuspendBackupJob extends AbstractQueueableJob
{
    public int $tries = 3;

    public function __construct(
        private readonly Subscription $subscription,
        private readonly bool $sendEmailOnSuccess = true,
    ) {
        parent::__construct();
    }

    public function handle(
        LoggerInterface $logger,
        SendSubscriptionUnSuspendedMailAction $sendSubscriptionUnSuspendedMailAction,
        ProvisionGateway $provisionGateway,
        SubscriptionMetadataService $subscriptionMetadataService,
    ): void {
        $this->subscription->technical_status = TechnicalStatus::UNSUSPENDING->value;
        $this->subscription->save();

        $result = $provisionGateway->request(
            new SetBackupSuspensionStateRequest(
                tagUuid: Uuid::fromString($this->subscription->uuid),
                enable: true,
            ),
        );

        if ($result->failed) {
            $logger->error(
                sprintf('Technical unsuspension of backup "%s" failed.', $this->subscription->uuid),
                [
                    LoggingContextKeys::SUBSCRIPTION_ID => $this->subscription->id,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $this->subscription->uuid,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::BACKUP,
                    LoggingContextKeys::EXCEPTION => $result->exception,
                ]
            );

            $this->subscription->technical_status = TechnicalStatus::UNSUSPENSION_FAILED->value;
            $subscriptionMetadataService->assignCategory($this->subscription, SubscriptionCategory::UNSUSPENSION);
            $this->subscription->save();
            return;
        }

        if ($this->sendEmailOnSuccess) {
            $this->informCustomer(
                sendSubscriptionUnSuspendedMailAction: $sendSubscriptionUnSuspendedMailAction,
                logger: $logger,
            );
        }

        $this->subscription->suspended_at = null;
        $this->subscription->technical_status = TechnicalStatus::OK->value;
        $this->subscription->save();
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::DEFAULT;
    }

    private function informCustomer(
        SendSubscriptionUnSuspendedMailAction $sendSubscriptionUnSuspendedMailAction,
        LoggerInterface $logger,
    ): void {
        $logger->info(
            sprintf(
                'Unsuspend for subscription with uuid "%s" went successfully, informing the customer...',
                $this->subscription->uuid,
            ),
            [
                LoggingContextKeys::SUBSCRIPTION_ID => $this->subscription->id,
                LoggingContextKeys::SUBSCRIPTION_UUID => $this->subscription->uuid,
            ]
        );
        $sendSubscriptionUnSuspendedMailAction->execute($this->subscription);
    }
}
