<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Observers;

use Illuminate\Contracts\Events\Dispatcher;
use Waterfront\Domain\Customers\Enums\CustomerContactType;
use Waterfront\Domain\Customers\Events\CustomerDataChangedEvent;
use Waterfront\Domain\Customers\Models\CustomerContact;

class CustomerContactObserver
{
    public function __construct(
        private readonly Dispatcher $eventDispatcher,
    ) {
    }

    public function created(CustomerContact $customerContact): void
    {
        if ($customerContact->type === CustomerContactType::FINANCIAL->value) {
            $this->eventDispatcher->dispatch(new CustomerDataChangedEvent($customerContact->customer));
        }
    }

    public function updated(CustomerContact $customerContact): void
    {
        if ($customerContact->type === CustomerContactType::FINANCIAL->value) {
            $this->eventDispatcher->dispatch(new CustomerDataChangedEvent($customerContact->customer));
        }
    }

    public function deleted(CustomerContact $customerContact): void
    {
        if ($customerContact->type === CustomerContactType::FINANCIAL->value) {
            $this->eventDispatcher->dispatch(new CustomerDataChangedEvent($customerContact->customer));
        }
    }
}
