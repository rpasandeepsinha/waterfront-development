<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Observers;

use Illuminate\Contracts\Bus\Dispatcher as JobDispatcher;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Waterfront\Domain\Customers\Events\CustomerDataChangedEvent;
use Waterfront\Domain\Customers\Jobs\UpdateCustomerVatRate;
use Waterfront\Domain\Customers\Models\CustomerAddress;

class CustomerAddressObserver
{
    public function __construct(
        private readonly JobDispatcher $jobDispatcher,
        private readonly EventDispatcher $eventDispatcher,
    ) {
    }

    public function created(CustomerAddress $customerAddress): void
    {
        if ($customerAddress->isDirty(['country_code'])) {
            $this->jobDispatcher->dispatch(new UpdateCustomerVatRate($customerAddress->customer));
        }
    }

    public function updated(CustomerAddress $customerAddress): void
    {
        if ($customerAddress->isDirty(['country_code'])) {
            $this->jobDispatcher->dispatch(new UpdateCustomerVatRate($customerAddress->customer));
        }

        $this->eventDispatcher->dispatch(new CustomerDataChangedEvent($customerAddress->customer));
    }
}
