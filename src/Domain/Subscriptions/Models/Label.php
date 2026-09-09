<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Waterfront\Domain\Customers\Models\Customer;

/**
 * @property int                           $id
 * @property non-empty-string              $value
 * @property int                           $customer_id
 * @property Customer                      $customer
 * @property Collection<int, Subscription> $subscriptions
 * @property ?CarbonImmutable              $created_at
 * @property ?CarbonImmutable              $updated_at
 *
 * @mixin Builder<Label>
 */
class Label extends Model implements AuditableContract
{
    use Auditable;

    protected $table = 'labels';

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsToMany<Subscription, $this>
     */
    public function subscriptions(): BelongsToMany
    {
        return $this->belongsToMany(Subscription::class);
    }
}
