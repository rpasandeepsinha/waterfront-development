<?php

declare(strict_types=1);

namespace Waterfront\Domain\Orders\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Waterfront\Domain\OneTimeServices\Models\OneTimeService;
use Waterfront\Domain\Orders\Enums\OrderLineItemStatus;
use Waterfront\Domain\Pricing\Models\OrderLinePrice;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Models\SubscriptionMutation;
use Waterfront\Domain\Voucher\Models\VoucherClaim;

/**
 * @property int                             $id
 * @property Order                           $order
 * @property ?Product                        $product
 * @property ?Subscription                   $subscription
 * @property ?Subscription                   $parentSubscription
 * @property ?OneTimeService                 $oneTimeService
 * @property ?string                         $domain
 * @property int                             $gross_price
 * @property int                             $order_id
 * @property string                          $product_uuid
 * @property string                          $product_name
 * @property int                             $billing_period
 * @property int                             $contract_period
 * @property int                             $net_price
 * @property OrderLineItemStatus             $status
 * @property bool                            $should_invoice
 * @property ?string                         $subscription_uuid
 * @property ?string                         $parent_subscription_uuid
 * @property ?int                            $server
 * @property ?string                         $transfer_secret
 * @property ?string                         $meta_data
 * @property ?string                         $experiment_slug
 * @property ?int                            $parent_id
 * @property ?int                            $one_time_service_id
 * @property ?OrderLineItem                  $parent
 * @property Collection<int, OrderLineItem>  $children
 * @property ?VoucherClaim                   $voucherClaim
 * @property ?CarbonImmutable                $created_at
 * @property ?CarbonImmutable                $updated_at
 * @property ?CarbonImmutable                $processed_at
 * @property ?int                            $subscription_mutation_id
 * @property ?SubscriptionMutation           $subscriptionMutation
 * @property Collection<int, OrderLinePrice> $prices
 *
 * @mixin Builder<OrderLineItem>
 */
class OrderLineItem extends Model implements AuditableContract
{
    use Auditable;

    protected $table = 'order_line_items';

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return HasMany<OrderLineItem, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(OrderLineItem::class, 'parent_id');
    }

    /**
     * @return BelongsTo<OrderLineItem, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(OrderLineItem::class, 'parent_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_uuid', 'uuid');
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_uuid', 'uuid');
    }

    /**
     * @return BelongsTo<SubscriptionMutation, $this>
     */
    public function subscriptionMutation(): BelongsTo
    {
        return $this->belongsTo(SubscriptionMutation::class, 'subscription_mutation_id', 'id');
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function parentSubscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'parent_subscription_uuid', 'uuid');
    }

    /**
     * @return BelongsTo<OneTimeService, $this>
     */
    public function oneTimeService(): BelongsTo
    {
        return $this->belongsTo(OneTimeService::class, 'one_time_service_id', 'id');
    }

    /**
     * @return HasOne<VoucherClaim, $this>
     */
    public function voucherClaim(): HasOne
    {
        return $this->hasOne(VoucherClaim::class);
    }

    /**
     * @return HasMany<OrderLinePrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(OrderLinePrice::class);
    }

    protected function casts(): array
    {
        return [
            'status' => OrderLineItemStatus::class,
        ];
    }
}
