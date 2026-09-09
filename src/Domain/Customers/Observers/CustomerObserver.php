<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Observers;

use Illuminate\Contracts\Bus\Dispatcher as JobDispatcher;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Waterfront\Domain\Customers\Events\CustomerDataChangedEvent;
use Waterfront\Domain\Customers\Jobs\SyncCustomerToMollieJob;
use Waterfront\Domain\Customers\Jobs\UpdateCustomerVatRate;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Payments\Models\MollieCustomer;

class CustomerObserver
{
    public function __construct(
        private readonly JobDispatcher $jobDispatcher,
        private readonly EventDispatcher $eventDispatcher,
    ) {
    }

    public function creating(Customer $customer): void
    {
        if ($customer->terms_of_payment === null) {
            $defaultPaymentTerm = Config::get('constants.payment-terms.default');
            assert(is_int($defaultPaymentTerm) || is_string($defaultPaymentTerm));
            $customer->terms_of_payment = intval($defaultPaymentTerm);
        }
    }

    public function created(Customer $customer): void
    {
        if ($customer->isDirty(['vat_number']) && $customer->address()->exists()) {
            $this->jobDispatcher->dispatch(new UpdateCustomerVatRate($customer));
        }
    }

    public function updated(Customer $customer): void
    {
        if ($customer->isDirty(['vat_number']) && $customer->vat_number !== null) {
            $this->jobDispatcher->dispatch(new UpdateCustomerVatRate($customer));
        }

        if ($customer->isDirty(
            [
                'email',
                'first_name',
                'last_name',
                'locale',
                'customer_number',
            ]
        )) {
            // Checkup for migration compatibility if migrations ever get squashed
            // this check could be removed. (Customers get edited in migrations before
            // Mollie customer table exists.)
            if (Schema::hasTable(new MollieCustomer()->getTable())) {
                $mollieCustomer = $customer->mollieCustomer;

                if ($mollieCustomer instanceof MollieCustomer) {
                    $this->jobDispatcher->dispatch(new SyncCustomerToMollieJob($mollieCustomer));
                }
            }
        }

        $this->eventDispatcher->dispatch(new CustomerDataChangedEvent($customer));
    }
}
