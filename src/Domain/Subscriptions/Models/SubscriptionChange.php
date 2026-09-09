<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionChangeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription as BaseSubscription;
use Waterfront\Support\Database\UuidCast;

/**
 * @property int                      $id
 * @property UuidInterface            $uuid
 * @property UuidInterface            $subscription_uuid
 * @property UuidInterface            $from_product_uuid
 * @property Product                  $fromProduct
 * @property Product                  $toProduct
 * @property UuidInterface            $to_product_uuid
 * @property ProductChangeType        $type
 * @property SubscriptionChangeStatus $status
 * @property ?int                     $failure_code
 * @property ?string                  $failure_message
 * @property ?CarbonImmutable         $requested_at
 * @property ?CarbonImmutable         $completed_at
 * @property ?CarbonImmutable         $created_at
 * @property ?CarbonImmutable         $updated_at
 * @property Subscription             $subscription
 *
 * @mixin Builder<SubscriptionChange>
 */
class SubscriptionChange extends Model implements AuditableContract
{
    use Auditable;

    protected $table = 'subscription_changes';

    /**
     * @return BelongsTo<BaseSubscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(BaseSubscription::class, 'subscription_uuid', 'uuid');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function fromProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'from_product_uuid', 'uuid');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function toProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'to_product_uuid', 'uuid');
    }

    protected function casts(): array
    {
        return [
            'status' => SubscriptionChangeStatus::class,
            'type' => ProductChangeType::class,
            'failure_code' => 'integer',
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'subscription_uuid' => UuidCast::class,
            'from_product_uuid' => UuidCast::class,
            'to_product_uuid' => UuidCast::class,
        ];
    }
}
