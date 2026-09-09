<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Products\Casts\PromotionCallToActionCast;
use Waterfront\Domain\Products\DTO\Configuration\PromotionCallToAction;
use Waterfront\Domain\Products\Enums\ProductPromotionPlatform;
use Waterfront\Support\Database\UuidCast;

/**
 * @property int                      $id
 * @property UuidInterface            $uuid
 * @property Product                  $product
 * @property int                      $product_id
 * @property CarbonImmutable          $start_date
 * @property CarbonImmutable          $end_date
 * @property ProductPromotionPlatform $platform
 * @property string                   $placement_url
 * @property-read  array<string, string|null>                        $call_to_action
 * @property-write array<string, string|null>|PromotionCallToAction $call_to_action
 * @property int              $weight
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 *
 * @mixin Builder<ProductPromotion>
 */
class ProductPromotion extends Model implements AuditableContract
{
    use Auditable;

    protected $table = 'product_promotions';

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid ??= Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'platform' => ProductPromotionPlatform::class,
            'call_to_action' => PromotionCallToActionCast::class,
            'uuid' => UuidCast::class,
        ];
    }
}
