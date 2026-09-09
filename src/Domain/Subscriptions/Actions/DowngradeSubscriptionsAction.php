<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Actions;

use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Domain\DNS\Actions\ChangeDnsAction;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Services\HostingDowngradeExecutor;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Repositories\ProductSpecRepository;
use Waterfront\Domain\Sitebuilder\SitebuilderService;
use Waterfront\Domain\Subscriptions\DTO\SubscriptionChangeResult;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Exceptions\NotImplementedException;
use Webmozart\Assert\Assert;

class DowngradeSubscriptionsAction
{
    public function __construct(
        private readonly ChangeDnsAction $changeDnsAction,
        private readonly ProductSpecRepository $productSpecRepository,
        private readonly SitebuilderService $sitebuilderService,
        private readonly HostingDowngradeExecutor $hostingDowngradeExecutor,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(
        Subscription $subscription,
        Product $newProduct
    ): SubscriptionChangeResult {
        $subscription->refresh();

        switch ($subscription->product->productGroup->slug) {
            case ProductGroupType::HOSTING:
                return $this->downgradeHosting($subscription, $newProduct);
            case ProductGroupType::DNS:
                return $this->downgradeDns($subscription);
            default:
                throw new NotImplementedException(
                    sprintf(
                        'Trying to downgrade subscription "%s" for product group "%s" which doesn\'t support downgrades.',
                        $subscription->uuid,
                        $subscription->product->productGroup->slug->value
                    )
                );
        }
    }

    private function downgradeDns(Subscription $subscription): SubscriptionChangeResult
    {
        try {
            $this->changeDnsAction->execute($subscription, ProductChangeType::DOWNGRADE);

            return new SubscriptionChangeResult(status: SubscriptionChangeResult::STATUS_OK);

            /** @phpstan-ignore-next-line */
        } catch (Throwable $exception) {
            $this->logger->warning(
                'Technical DNS downgrade failed for subscription {subscription.uuid}',
                [
                    LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::EXCEPTION => $exception,
                ]
            );

            return new SubscriptionChangeResult(
                status: SubscriptionChangeResult::STATUS_ERROR,
                errorCode: $exception->getCode(),
                errorMessage: $exception->getMessage(),
            );
        }
    }

    private function downgradeHosting(Subscription $subscription, Product $newProduct): SubscriptionChangeResult
    {
        $subscription->loadMissing('hostingDeployment');

        $hostingDeployment = $subscription->hostingDeployment;
        Assert::isInstanceOf($hostingDeployment, HostingDeployment::class);

        $newProductIsMailProduct = $this->productSpecRepository->booleanSpecificationIsTrue($newProduct, ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER);

        if ($newProductIsMailProduct
            && $hostingDeployment->mail_only_provider_id === null
            && $hostingDeployment->mail_only_server_id === null
        ) {
            $hostingDeployment->mail_only_provider_id = $hostingDeployment->provider_id;
            $hostingDeployment->mail_only_server_id = $hostingDeployment->server_id;
            $hostingDeployment->provider_id = null;
            $hostingDeployment->server_id = null;
            $hostingDeployment->save();
        }

        if (
            $subscription->product->isSitebuilderProduct()
            && $this->sitebuilderService->hasSitebuilderThroughGateway($subscription->customer->email)
        ) {
            return $this->downgradeSitebuilder($subscription);
        }

        if ($newProductIsMailProduct) {
            return new SubscriptionChangeResult(status: SubscriptionChangeResult::STATUS_OK);
        }

        return $this->changeHostingServicePlan($subscription, $hostingDeployment, $newProduct);
    }

    private function downgradeSitebuilder(Subscription $subscription): SubscriptionChangeResult
    {
        try {
            $this->sitebuilderService->update($subscription);

            return new SubscriptionChangeResult(status: SubscriptionChangeResult::STATUS_OK);

            /** @phpstan-ignore-next-line */
        } catch (Throwable $exception) {
            $this->logger->warning(
                'Technical sitebuilder downgrade failed for subscription {subscription.uuid}',
                [
                    LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::EXCEPTION => $exception,
                ]
            );

            return new SubscriptionChangeResult(
                status: SubscriptionChangeResult::STATUS_ERROR,
                errorCode: $exception->getCode(),
                errorMessage: $exception->getMessage(),
            );
        }
    }

    private function changeHostingServicePlan(
        Subscription $subscription,
        HostingDeployment $hostingDeployment,
        Product $newProduct
    ): SubscriptionChangeResult {
        return $this->hostingDowngradeExecutor->execute(
            subscription: $subscription,
            hostingDeployment: $hostingDeployment,
            oldProduct: $subscription->product,
            newProduct: $newProduct,
        );
    }
}
