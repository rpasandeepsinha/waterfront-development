<?php

declare(strict_types=1);

namespace Waterfront\Domain\Payments\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Orders\Models\Order;
use Waterfront\Domain\Payments\Enums\PaymentStatus;
use Webmozart\Assert\Assert;

/**
 * @property PaymentStatus    $status
 * @property string           $external_id
 * @property Customer         $customer
 * @property string           $customer_uuid
 * @property int              $amount
 * @property ?Order           $order
 * @property ?int             $order_id
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property ?bool            $create_direct_debit_mandate
 *
 * @mixin Builder<Payment>
 */
class Payment extends Model implements AuditableContract
{
    use Auditable;

    public ?string $checkout_url = null;

    public $appends = [
        'checkout_url',
    ];

    protected $table = 'payments';

    /**
     * Defines the relation with the customer.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_uuid', 'uuid');
    }

    /**
     * Defines the relation with the order.
     *
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return string|null
     */
    public function getCheckoutUrlAttribute()
    {
        return $this->checkout_url;
    }

    public function getStatusAttribute(): PaymentStatus
    {
        $statusAttribute = $this->attributes['status'];
        $status = $statusAttribute instanceof PaymentStatus
            ? $statusAttribute
            : PaymentStatus::tryFrom($statusAttribute);
        Assert::notNull($status);

        return $status;
    }

    public function setStatusAttribute(PaymentStatus|string $status): void
    {
        $this->attributes['status'] = $status instanceof PaymentStatus ? $status : PaymentStatus::tryFrom($status);
    }
}
