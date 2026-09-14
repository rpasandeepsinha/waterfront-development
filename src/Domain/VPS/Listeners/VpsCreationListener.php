<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Domain\VPS\Events\CreateVps;
use Waterfront\Domain\VPS\Exceptions\ClientFactoryException;
use Waterfront\Domain\VPS\Exceptions\CloudstackNotFoundException;
use Waterfront\Domain\VPS\Services\VpsService;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Webmozart\Assert\Assert;

class VpsCreationListener implements ShouldQueue
{
    public string $queue = QueueName::CLOUDSTACK->value;

    public function __construct(
        private readonly VpsService $vpsService,
        private readonly LoggerInterface $logger,
        private readonly SubscriptionRepository $subscriptionRepository,
    ) {
    }

    public function failed(CreateVps $event, ?Throwable $exception): void
    {
        $this->logger->error(
            'Failed to create VPS',
            [
                LoggingContextKeys::SUBSCRIPTION_UUID => $event->subscriptionUuid,
                LoggingContextKeys::PROVISIONING_TYPE => ProductGroupType::VPS,
                LoggingContextKeys::PROVISIONING_PROVIDER => 'cloudstack',
                LoggingContextKeys::EXCEPTION => $exception,
            ],
        );

        $vpsSubscription = $this->subscriptionRepository->getByUuid($event->subscriptionUuid);

        if ($vpsSubscription === null) {
            $this->logger->error(
                'Failed to update VPS subscriptions status, subscription not found with uuid {subscription.uuid}',
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $event->subscriptionUuid,
                    LoggingContextKeys::PROVISIONING_TYPE => ProductGroupType::VPS,
                    LoggingContextKeys::PROVISIONING_PROVIDER => 'cloudstack',
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

            return;
        }

        $vpsSubscription->update([
            'technical_status' => TechnicalStatus::FAILED->value,
        ]);

        $vpsSubscription
            ->children()
            ->update([
                'technical_status' => TechnicalStatus::FAILED->value,
            ]);
    }

    /**
     * @throws ClientFactoryException
     * @throws CloudstackNotFoundException
     */
    public function handle(CreateVps $event): void
    {
        $this->logger->info(
            'Create VPS',
            [
                LoggingContextKeys::SUBSCRIPTION_UUID => $event->subscriptionUuid,
                LoggingContextKeys::PROVISIONING_TYPE => ProductGroupType::VPS,
                LoggingContextKeys::PROVISIONING_PROVIDER => 'cloudstack',
            ],
        );

        $vpsSubscription = $this->subscriptionRepository->getByUuid($event->subscriptionUuid);
        Assert::isInstanceOf($vpsSubscription, Subscription::class);

        $this->vpsService->create(
            subscription: $vpsSubscription,
            sshKeyUuid: $event->sshKeyUuid,
        );
    }
}
