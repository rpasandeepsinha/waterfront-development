<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Listeners;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Waterfront\Domain\Domains\Events\DomainTerminated;
use Waterfront\Domain\Microsoft365\Jobs\DecouplePrimaryDomainJob;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;
use Waterfront\Domain\Microsoft365\Repositories\Microsoft365CustomerInfoRepository;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\QueueName;

class DomainTerminatedListener implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = QueueName::PARTNER_DOMAIN->value;

    public function __construct(
        private readonly Microsoft365CustomerInfoRepository $customerInfoRepository,
        private readonly Dispatcher $eventDispatcher,
    ) {
    }

    public function handle(DomainTerminated $event): void
    {
        $this->decoupleDomainFromM365($event->getSubscription());
    }

    private function decoupleDomainFromM365(Subscription $subscription): void
    {
        $customerInfo = $this->customerInfoRepository->findByCustomerAndDomain(
            $subscription->customer,
            $subscription->domain,
        );
        if ($customerInfo instanceof Microsoft365CustomerInfo) {
            $this->eventDispatcher->dispatch(new DecouplePrimaryDomainJob($customerInfo));
        }
    }
}
