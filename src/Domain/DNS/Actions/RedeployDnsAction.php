<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Actions;

use Exception;
use Illuminate\Contracts\Events\Dispatcher;
use Waterfront\Domain\DNS\Events\CreateDns;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\SubscriptionProcessor\Builder\EventSubscriptionDataBuilder;

readonly class RedeployDnsAction
{
    public function __construct(
        private Dispatcher $eventDispatcher,
        private EventSubscriptionDataBuilder $dataBuilder,
    ) {
    }

    public function execute(Subscription $subscription): void
    {
        if ($subscription->product->productGroup->slug !== ProductGroupType::DNS) {
            throw new Exception('RedeployDnsAction called with subscription with incompatible product group '
            . $subscription->product->productGroup->slug->value);
        }

        $domain = $subscription->domain;
        assert(is_string($domain));

        if (! $subscription->dnsDeployment()->exists()) {
            $this->dataBuilder->buildDnsDeployment($subscription);
        }

        $this->eventDispatcher->dispatch(
            new CreateDns(
                $subscription->uuid,
                $domain,
            ),
        );

        $subscription->technical_status = TechnicalStatus::PENDING->value;
        $subscription->save();
    }
}
