<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;

/**
 * @property int               $id
 * @property int               $from_product_id
 * @property int               $to_product_id
 * @property ?Product          $fromProduct
 * @property ?Product          $toProduct
 * @property ProductChangeType $change_type
 * @property int               $display_order
 * @property bool              $is_available_for_customer
 * @property ?CarbonImmutable  $created_at
 * @property ?CarbonImmutable  $updated_at
 * @property ?CarbonImmutable  $deleted_at
 *
 * @mixin Builder<ProductAllowedChange>
 */
class ProductAllowedChange extends Model
{
    use SoftDeletes;

    protected $table = 'product_allowed_changes';

    /**
     * @return BelongsTo<Product, $this>
     */
    public function fromProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'from_product_id', 'id')->withoutTrashed();
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function toProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'to_product_id', 'id')->withoutTrashed();
    }

    protected function casts(): array
    {
        return [
            'change_type' => ProductChangeType::class,
        ];
    }
}
