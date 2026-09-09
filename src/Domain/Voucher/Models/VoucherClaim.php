<?php

declare(strict_types=1);

namespace Waterfront\Domain\Voucher\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Waterfront\Domain\Orders\Models\OrderLineItem;

/**
 * @property int<positive-int>     $id
 * @property int<positive-int>     $voucher_id
 * @property Voucher               $voucher
 * @property int<positive-int>     $order_line_item_id
 * @property OrderLineItem         $orderLineItem
 * @property int<non-negative-int> $amount_claimed
 * @property ?CarbonImmutable      $created_at
 * @property ?CarbonImmutable      $updated_at
 *
 * @mixin Builder<VoucherClaim>
 */
#[WithoutTimestamps]
class VoucherClaim extends Model
{
    /**
     * @return BelongsTo<Voucher, $this>
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    /**
     * @return BelongsTo<OrderLineItem, $this>
     */
    public function orderLineItem(): BelongsTo
    {
        return $this->belongsTo(OrderLineItem::class);
    }
}
