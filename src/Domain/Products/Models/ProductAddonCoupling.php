<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int              $id
 * @property int              $parent_product_id
 * @property int              $addon_product_id
 * @property Product          $parentProduct
 * @property Product          $addonProduct
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 *
 */
class ProductAddonCoupling extends Model
{
    protected $table = 'product_addon_coupling';

    /**
     * @return BelongsTo<Product, $this>
     */
    public function parentProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'parent_product_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function addonProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'addon_product_id');
    }
}
