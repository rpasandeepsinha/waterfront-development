<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Actions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\DNS\Actions\ChangeDnsAction;
use Waterfront\Domain\DNS\Exceptions\DnsChangeException;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Exceptions\DowngradeCancelException;
use Waterfront\Domain\Subscriptions\Exceptions\SubscriptionChangeException;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\SubscriptionChangeService;
use Waterfront\Support\Enums\LoggingContextKeys;

class DowngradeSubscriptionOnCancelAction
{
    public function __construct(
        private readonly SubscriptionChangeService $subscriptionChangeService,
        private readonly ChangeDnsAction $changeDnsAction,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(Subscription $subscription): void
    {
        try {
            $downgradeProduct = $this->subscriptionChangeService->getAvailableDowngradeWhenCanceled($subscription);
            $this->changeDnsAction->execute($subscription, ProductChangeType::DOWNGRADE);
            $this->subscriptionChangeService->change(
                changeType: ProductChangeType::DOWNGRADE,
                subscription: $subscription,
                newProduct: $downgradeProduct,
            );
        } catch (DowngradeCancelException|ModelNotFoundException|DnsChangeException|SubscriptionChangeException $e) {
            $this->logger->error(
                'Tried to downgrade subscription with uuid: {subscription.uuid} while canceled',
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                    LoggingContextKeys::EXCEPTION => $e,
                ],
            );
        }
    }
}
