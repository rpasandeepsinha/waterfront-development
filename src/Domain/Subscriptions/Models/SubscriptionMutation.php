<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Waterfront\Domain\Products\Models\Product;

/**
 * @property int               $id
 * @property int               $subscription_id
 * @property Subscription      $subscription
 * @property int               $product_id
 * @property Product           $product
 * @property int<0, max>       $gross_price
 * @property int<0, max>       $net_price
 * @property int<positive-int> $billing_period
 * @property int<positive-int> $contract_period
 * @property ?CarbonImmutable  $mutated_at
 * @property ?CarbonImmutable  $process_technical_at
 * @property ?CarbonImmutable  $processed_technical_at
 * @property ?CarbonImmutable  $created_at
 * @property ?CarbonImmutable  $updated_at
 *
 * @mixin Builder<SubscriptionMutation>
 */
class SubscriptionMutation extends Model implements AuditableContract
{
    use Auditable;

    protected $table = 'subscription_mutations';

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return array<string,array<string>> */
    public static function namespaceWith(): array
    {
        return [
            self::class => ['product', 'subscription'],
        ];
    }

    protected function casts(): array
    {
        return [
            'mutated_at' => 'datetime',
            'process_technical_at' => 'datetime',
            'processed_technical_at' => 'datetime',
        ];
    }
}
