<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Actions;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Waterfront\Domain\Hosting\Services\HostingDowngradePossibilityChecker;
use Waterfront\Domain\Subscriptions\DTO\SubscriptionChangeResult;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionChangeRepository;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class RetryHostingDowngradeAction
{
    public function __construct(
        private readonly ChangeHostingAction $changeHostingAction,
        private readonly HostingDowngradePossibilityChecker $hostingDowngradePossibilityChecker,
        private readonly LoggerInterface $logger,
        private readonly SubscriptionChangeRepository $subscriptionChangeRepository,
    ) {
    }

    public function execute(Subscription $subscription): bool
    {
        $subscription->loadMissing('hostingDeployment');

        Assert::notNull(
            $subscription->hostingDeployment,
            'Subscription has no hosting deployment.',
        );

        $subscriptionChange = $this->subscriptionChangeRepository
            ->getOpenSubscriptionChangeRequest($subscription)
            ->firstOrFail();

        Assert::stringNotEmpty($subscriptionChange->toProduct->slug, 'Mutation product slug is empty.');

        $servicePlan = $subscriptionChange->toProduct->slug;

        $downgradeCheckResult = $this->hostingDowngradePossibilityChecker->canDowngradeToServicePlan(
            $subscription->hostingDeployment,
            $servicePlan,
        );
        if (! $downgradeCheckResult->isSuccessful) {
            throw new RuntimeException(sprintf(
                'Unable to downgrade to service plan (%s): %s',
                $servicePlan,
                $downgradeCheckResult->message,
            ));
        }

        $this->logger->info('Retrying hosting downgrade', [
            LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
            LoggingContextKeys::PRODUCT_ID => $subscriptionChange->toProduct->id,
        ]);

        $result = $this->changeHostingAction->execute(
            subscription: $subscription,
            hostingDeployment: $subscription->hostingDeployment,
            oldProduct: $subscriptionChange->fromProduct,
            newProduct: $subscriptionChange->toProduct,
        );

        return $result->status !== SubscriptionChangeResult::STATUS_ERROR;
    }
}
