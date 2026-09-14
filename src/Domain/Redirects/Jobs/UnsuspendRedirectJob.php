<?php

declare(strict_types=1);

namespace Waterfront\Domain\Redirects\Jobs;

use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Email\Actions\SendSubscriptionUnSuspendedMailAction;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Redirects\Requests\UnsuspendRedirectRequest;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class UnsuspendRedirectJob extends AbstractQueueableJob
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
    ): void {
        $this->subscription->technical_status = TechnicalStatus::UNSUSPENDING->value;
        $this->subscription->save();

        $result = $provisionGateway->request(
            new UnsuspendRedirectRequest(
                context: Uuid::fromString($this->subscription->uuid),
            ),
        );

        if ($result->failed) {
            $logger->error(
                sprintf('Technical unsuspension of redirect "%s" failed.', $this->subscription->uuid),
                [
                    LoggingContextKeys::SUBSCRIPTION_ID => $this->subscription->id,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $this->subscription->uuid,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                    LoggingContextKeys::EXCEPTION => $result->exception,
                ],
            );

            $this->subscription->technical_status = TechnicalStatus::UNSUSPENSION_FAILED->value;
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
                'Unsuspension for subscription with uuid "%s" went successfully, informing the customer...',
                $this->subscription->uuid,
            ),
            [
                LoggingContextKeys::SUBSCRIPTION_ID => $this->subscription->id,
                LoggingContextKeys::SUBSCRIPTION_UUID => $this->subscription->uuid,
            ],
        );
        $sendSubscriptionUnSuspendedMailAction->execute($this->subscription);
    }
}
