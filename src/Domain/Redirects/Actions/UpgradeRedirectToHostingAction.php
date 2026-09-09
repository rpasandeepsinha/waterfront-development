<?php

declare(strict_types=1);

namespace Waterfront\Domain\Redirects\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Hosting\Events\CreateHosting;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Provision\Interfaces\ProvisionResultInterface;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Redirects\Requests\TerminateRedirectsRequest;
use Waterfront\Domain\Subscriptions\DTO\SubscriptionChangeResult;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Exceptions\SubscriptionChangeException;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;

class UpgradeRedirectToHostingAction
{
    public function __construct(
        private readonly Dispatcher $eventDispatcher,
        private readonly LoggerInterface $logger,
        private readonly ProvisionGateway $provisionGateway
    ) {
    }

    /**
     * @throws SubscriptionChangeException
     */
    public function execute(
        Subscription $subscription,
        Product $newProduct,
    ): SubscriptionChangeResult {
        $this->logger->debug(
            'Upgrading redirect subscription to hosting.',
            [
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                LoggingContextKeys::PRODUCT_ID => $subscription->product->id,
            ]
        );

        $result = $this->removeAllRedirects($subscription);

        if ($result->failed) {
            throw $result->exception ?? new SubscriptionChangeException(sprintf('Unable to remove provisioned redirects for domain %s.', $subscription->domain));
        }

        $subscription->update([
            'technical_status' => TechnicalStatus::REGISTRATION->value, // Without updating the technical status we can't execute the createhosting event
        ]);

        $this->eventDispatcher->dispatch(
            new CreateHosting(
                subscriptionUuid: $subscription->uuid,
                technicalStatus: $subscription->technical_status,
                contactPersonName: $subscription->customer->contact_name,
                contactEmail: $subscription->customer->email,
                domain: $subscription->domain,
                customer: $subscription->customer,
                product: $subscription->product,
                serverId: null,
            )
        );

        $this->logger->debug(
            'Create hosting event dispatched to provision hosting.',
            [
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                LoggingContextKeys::PRODUCT_ID => $subscription->product->id,
            ]
        );

        return new SubscriptionChangeResult(
            status: SubscriptionChangeResult::STATUS_OK
        );
    }

    private function removeAllRedirects(Subscription $subscription): ProvisionResultInterface
    {
        $terminateRequest = new TerminateRedirectsRequest(
            context: Uuid::fromString($subscription->uuid)
        );

        return $this->provisionGateway->request($terminateRequest);
    }
}
