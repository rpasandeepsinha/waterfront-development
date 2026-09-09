<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Waterfront\Domain\Products\Models\Product;

/**
 * @property int              $id
 * @property string           $product_identifier
 * @property Environment      $environment
 * @property Product          $product
 * @property int              $product_id
 * @property int              $environment_id
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 *
 * @mixin Builder<EnvironmentProduct>
 */
class EnvironmentProduct extends Model
{
    protected $table = 'cloudstack_environment_products';

    protected $fillable = [
        'environment_id',
        'product_id',
        'product_identifier',
    ];

    /**
     * @return BelongsTo<Environment, $this>
     */
    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
