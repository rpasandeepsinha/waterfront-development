<?php

declare(strict_types=1);

namespace Waterfront\Domain\Payments\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Waterfront\Domain\Customers\Models\Customer;

/**
 * @property int                     $id
 * @property int                     $customer_id
 * @property string                  $mollie_customer_reference_id
 * @property ?CarbonImmutable        $updated_at
 * @property ?CarbonImmutable        $created_at
 * @property Customer                $customer
 * @property Collection<int,Mandate> $mandates
 * @property ?CarbonImmutable        $deleted_at
 *
 * @mixin Builder<MollieCustomer>
 */
class MollieCustomer extends Model implements AuditableContract
{
    use Auditable;
    use HasTimestamps;
    use SoftDeletes;

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<Mandate, $this>
     */
    public function mandates(): HasMany
    {
        return $this->hasMany(Mandate::class);
    }
}
