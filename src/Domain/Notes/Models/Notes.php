<?php

declare(strict_types=1);

namespace Waterfront\Domain\Notes\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Database\UuidCast;

/**
 * @property ?UuidInterface   $noted_by_uuid
 * @property ?string          $noted_by_metadata
 * @property Customer         $customer
 * @property int              $customer_id
 * @property ?int             $subscription_id
 * @property ?Subscription    $subscription
 * @property string           $note
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 *
 * @mixin Builder<Notes>
 */
class Notes extends Model
{
    protected $table = 'notes';

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id', 'id');
    }

    protected function casts(): array
    {
        return [
            'noted_by_uuid' => UuidCast::class,
            'created_at' => 'date',
        ];
    }
}
