<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Services;

use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Domain\Hosting\Actions\ChangeHostingAction;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\DTO\SubscriptionChangeResult;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;

class HostingDowngradeExecutor
{
    public function __construct(
        private readonly HostingDowngradePossibilityChecker $downgradePossibilityChecker,
        private readonly ChangeHostingAction $changeHostingAction,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(
        Subscription $subscription,
        HostingDeployment $hostingDeployment,
        Product $oldProduct,
        Product $newProduct,
    ): SubscriptionChangeResult {
        try {
            $downgradeCheckResult = $this->downgradePossibilityChecker->canDowngradeToServicePlan(
                $hostingDeployment,
                $newProduct->slug,
            );

            if (! $downgradeCheckResult->isSuccessful) {
                return new SubscriptionChangeResult(
                    status: SubscriptionChangeResult::STATUS_ERROR,
                    errorMessage: $downgradeCheckResult->message,
                );
            }

            return $this->changeHostingAction->execute(
                subscription: $subscription,
                hostingDeployment: $hostingDeployment,
                oldProduct: $oldProduct,
                newProduct: $newProduct,
            );

            /** @phpstan-ignore-next-line */
        } catch (Throwable $exception) {
            $this->logger->warning(
                'Technical hosting downgrade to product id {product.id} not performed for subscription {subscription.uuid}',
                [
                    LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::PRODUCT_ID => $newProduct->id,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

            return new SubscriptionChangeResult(
                status: SubscriptionChangeResult::STATUS_ERROR,
                errorCode: $exception->getCode(),
                errorMessage: $exception->getMessage(),
            );
        }
    }
}
