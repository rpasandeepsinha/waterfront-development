<?php

declare(strict_types=1);

namespace Waterfront\Domain\Orders\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Orders\Enums\OrderStatus;
use Waterfront\Domain\Payments\Enums\PaymentMethod;
use Waterfront\Domain\Payments\Enums\PaymentStatus;
use Waterfront\Domain\Payments\Models\Payment;
use Waterfront\Domain\Voucher\Models\VoucherClaim;
use Waterfront\Support\Database\UuidCast;

/**
 * @property int                            $id
 * @property string                         $uuid
 * @property ?int                           $customer_id
 * @property OrderStatus                    $status
 * @property PaymentMethod                  $payment_method
 * @property int                            $total_price
 * @property int                            $administration_fees
 * @property ?CarbonImmutable               $created_at
 * @property ?CarbonImmutable               $updated_at
 * @property ?UuidInterface                 $ordered_by_uuid
 * @property ?string                        $ordered_by_metadata
 * @property Collection<int, OrderLineItem> $lineItems
 * @property Collection<int, VoucherClaim>  $voucherClaims
 * @property Collection<int, Payment>       $payments
 * @property Customer                       $customer
 * @property bool                           $is_invoiced
 * @property-read bool                      $is_paid
 *
 * @mixin Builder<Order>
 */
class Order extends Model implements AuditableContract
{
    use Auditable;

    protected $table = 'orders';

    /**
     * @return HasMany<OrderLineItem, $this>
     */
    public function lineItems(): HasMany
    {
        return $this->hasMany(OrderLineItem::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isPaid(): bool
    {
        return $this->getLatestPaidPayment() !== null;
    }

    public function getIsPaidAttribute(): bool
    {
        return $this->isPaid();
    }

    public function isPending(): bool
    {
        $status = $this->payments->last()->status ?? PaymentStatus::PENDING;

        return $status === PaymentStatus::PENDING;
    }

    public function isOpen(): bool
    {
        return $this->payments->last()?->status === PaymentStatus::OPEN;
    }

    public function getLatestPaidPayment(): ?Payment
    {
        if ($this->payments->count() === 0 || $this->payments->last() === null) {
            return null;
        }

        $payment = $this->payments->last();

        if ($payment->status !== PaymentStatus::PAID) {
            return null;
        }

        return $payment;
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasManyThrough<VoucherClaim, OrderLineItem, $this>
     */
    public function voucherClaims(): HasManyThrough
    {
        return $this->hasManyThrough(VoucherClaim::class, OrderLineItem::class);
    }

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_method' => PaymentMethod::class,
            'ordered_by_uuid' => UuidCast::class,
        ];
    }
}
