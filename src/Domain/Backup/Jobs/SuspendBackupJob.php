<?php

declare(strict_types=1);

namespace Waterfront\Domain\Backup\Jobs;

use Carbon\CarbonImmutable;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Email\Actions\SendSubscriptionSuspendedMailAction;
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

class SuspendBackupJob extends AbstractQueueableJob
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
        SendSubscriptionSuspendedMailAction $sendSubscriptionSuspendedMailAction,
        ProvisionGateway $provisionGateway,
        SubscriptionMetadataService $subscriptionMetadataService,
    ): void {
        $this->subscription->technical_status = TechnicalStatus::SUSPENDING->value;
        $this->subscription->save();

        $result = $provisionGateway->request(
            new SetBackupSuspensionStateRequest(
                tagUuid: Uuid::fromString($this->subscription->uuid),
                enable: false,
            ),
        );

        if ($result->failed) {
            $logger->error(
                sprintf('Technical suspension of backup "%s" failed.', $this->subscription->uuid),
                [
                    LoggingContextKeys::SUBSCRIPTION_ID => $this->subscription->id,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $this->subscription->uuid,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::BACKUP,
                    LoggingContextKeys::EXCEPTION => $result->exception,
                ]
            );

            $this->subscription->technical_status = TechnicalStatus::SUSPENSION_FAILED->value;
            $subscriptionMetadataService->assignCategory($this->subscription, SubscriptionCategory::SUSPENSION);
            $this->subscription->save();
            return;
        }

        if ($this->sendEmailOnSuccess) {
            $this->informCustomer(
                sendSubscriptionSuspendedMailAction: $sendSubscriptionSuspendedMailAction,
                logger: $logger,
            );
        }

        $this->subscription->technical_status = TechnicalStatus::SUSPENDED->value;
        $this->subscription->suspended_at = CarbonImmutable::now();
        $this->subscription->save();
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::DEFAULT;
    }

    private function informCustomer(
        SendSubscriptionSuspendedMailAction $sendSubscriptionSuspendedMailAction,
        LoggerInterface $logger,
    ): void {
        $logger->info(
            sprintf(
                'Suspension for subscription with uuid "%s" went successfully, informing the customer...',
                $this->subscription->uuid,
            ),
            [
                LoggingContextKeys::SUBSCRIPTION_ID => $this->subscription->id,
                LoggingContextKeys::SUBSCRIPTION_UUID => $this->subscription->uuid,
            ]
        );
        $sendSubscriptionSuspendedMailAction->execute($this->subscription);
    }
}
