<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Services\ManualMigration;

use Psr\Log\LoggerInterface;
use Waterfront\Domain\Customers\DTO\CreateSubscriptionsDTO;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Ferry\Actions\Subscriptions\StoreSubscriptionAction;
use Waterfront\Domain\Ferry\Dto\ResponseDto;
use Waterfront\Domain\Ferry\Exceptions\NoSubscriptionsStoredException;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;

class SubscriptionService
{
    public function __construct(
        private readonly StoreSubscriptionAction $storeSubscriptionAction,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param mixed[] $subscription
     *
     * @throws NoSubscriptionsStoredException
     */
    public function storeSubscription(
        Customer $customer,
        string $referenceCustomerId,
        array $subscription,
    ): Subscription {
        $subscriptionsToCreate = CreateSubscriptionsDTO::create(
            $customer,
            $referenceCustomerId,
            $subscription,
        );

        $subscriptions = $this->storeSubscriptionAction->execute($subscriptionsToCreate, new ResponseDto());

        // This service only allows to create 1 subscription, let's verify that it's actually created
        if (count($subscriptions) !== 1) {
            $this->logger->error('Failed to create subscriptions during manual migration for customer', [
                LoggingContextKeys::CUSTOMER_ID => $customer->id,
                LoggingContextKeys::DOMAIN_NAME => $this->getDomainName($subscription),
            ]);
            throw new NoSubscriptionsStoredException('Subscription not created');
        }

        return current($subscriptions);
    }

    /**
     * @param mixed[] $subscription
     */
    private function getDomainName(array $subscription): ?string
    {
        $groupedSubscriptions = current($subscription);

        if (! is_array($groupedSubscriptions)) {
            return null;
        }

        $firstSubscription = current($groupedSubscriptions);

        if (! is_array($firstSubscription)) {
            return null;
        }

        if (! array_key_exists('domain', $firstSubscription)) {
            return null;
        }

        return $firstSubscription['domain'];
    }
}
