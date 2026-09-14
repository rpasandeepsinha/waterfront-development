<?php

declare(strict_types=1);

namespace Waterfront\Domain\Harbor\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Waterfront\Domain\Customers\Events\CustomerDataChangedEvent;
use Waterfront\Domain\Harbor\Interfaces\CommunicatesWithHarbor;
use Waterfront\Support\Enums\QueueName;

class DispatchToHarbor implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = QueueName::INVOICES->value;

    public int $tries = 5;

    public function __construct(
        private readonly CommunicatesWithHarbor $harbor,
    ) {
    }

    public function handle(CustomerDataChangedEvent $customerDataChangedEvent): void
    {
        if (
            $customerDataChangedEvent->customer->anonymized_at === null
            && $customerDataChangedEvent->customer->address()->exists()
        ) {
            $this->harbor->propagateCustomer($customerDataChangedEvent->customer);
        }
    }
}
