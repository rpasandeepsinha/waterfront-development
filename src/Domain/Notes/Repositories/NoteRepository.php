<?php

declare(strict_types=1);

namespace Waterfront\Domain\Notes\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Notes\Models\Notes;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class NoteRepository
{
    /**
     * @return Builder<Notes>
     */
    public function findByCustomer(Customer $customer): Builder
    {
        return Notes::where('customer_id', $customer->id);
    }

    /**
     * @return Builder<Notes>
     */
    public function findBySubscription(Subscription $subscription): Builder
    {
        return Notes::where('subscription_id', $subscription->id);
    }
}
