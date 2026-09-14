<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Jobs;

use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\Exceptions\DisableAutorenewalFailedException;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\CancellationService;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class CancelDomainSubscriptionJob extends AbstractQueueableJob
{
    private const string CANCEL_REASON = 'Domain has been transferred out.';

    public int $tries = 3;

    public function __construct(
        private readonly string $domainName,
    ) {
        parent::__construct();
    }

    public function handle(
        CancellationService $cancellationService,
        DomainService $domainService,
    ): void {
        $subscription = Subscription::query()
            ->whereProductGroupType(ProductGroupType::EXTENSION)
            ->where('domain', $this->domainName)
            ->first();

        if (! $subscription instanceof Subscription || ! $subscription->domainDeployment instanceof DomainDeployment) {
            return;
        }

        $cancellationService->cancel(
            subscription: $subscription,
            cancelType: SubscriptionCancelType::CANCEL_END_DATE,
            cancelReason: SubscriptionCancelReason::REASON_TRANSFER,
            sendMail: false,
            cancelNote: self::CANCEL_REASON,
        );

        try {
            $domainService->disableAutoRenewal($subscription->domainDeployment);
        } catch (DisableAutorenewalFailedException) {
            // This exception is expected when the domain has been transferred out.
            $subscription->technical_status = TechnicalStatus::DELETED->value;
            $subscription->save();
        }
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::PARTNER_DOMAIN;
    }
}
