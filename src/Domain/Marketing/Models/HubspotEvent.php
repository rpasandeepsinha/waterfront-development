<?php

declare(strict_types=1);

namespace Waterfront\Domain\Marketing\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Marketing\HubspotEvents\HubspotEventStatus;

/**
 * @property-read int $id
 * @property int                $customer_id
 * @property string             $event
 * @property string             $message
 * @property HubspotEventStatus $status
 * @property ?CarbonImmutable   $created_at
 * @property ?CarbonImmutable   $updated_at
 * @property-read Customer $customer
 *
 * @mixin Builder<HubspotEvent>
 */
class HubspotEvent extends Model
{
    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    protected function casts(): array
    {
        return [
            'status' => HubspotEventStatus::class,
        ];
    }
}
