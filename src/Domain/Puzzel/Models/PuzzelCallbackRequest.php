<?php

declare(strict_types=1);

namespace Waterfront\Domain\Puzzel\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Support\Database\UuidCast;

/**
 * @property int                  $id
 * @property UuidInterface        $uuid
 * @property int                  $customer_id
 * @property int                  $puzzel_callback_timeslot_id
 * @property string               $phone_number
 * @property string               $name
 * @property string               $request_category
 * @property string               $request_description
 * @property CarbonImmutable      $desired_callback_time
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Customer                    $customer
 * @property-read PuzzelCallbackTimeslot $timeslot
 * @property string $callback_date
 * @property string $timeslot_uuid
 * @property int    $usage_count
 *
 * @mixin Builder<PuzzelCallbackRequest>
 */
class PuzzelCallbackRequest extends Model
{
    protected $table = 'puzzel_callback_requests';

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<PuzzelCallbackTimeslot, $this>
     */
    public function timeslot(): BelongsTo
    {
        return $this->belongsTo(
            PuzzelCallbackTimeslot::class,
            'puzzel_callback_timeslot_id',
        );
    }

    protected function casts(): array
    {
        return [
            'uuid' => UuidCast::class,
            'desired_callback_time' => 'immutable_datetime',
        ];
    }
}
