<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Waterfront\Domain\Products\QueryBuilders\ProductSpecQueryBuilder;
use Waterfront\Domain\Providers\Models\Provider;

/**
 * @property string                $name
 * @property string|int|float|bool $value
 * @property Product               $product
 * @property int                   $product_id
 * @property int                   $id
 * @property ?CarbonImmutable      $created_at
 * @property ?CarbonImmutable      $updated_at
 *
 * @method static ProductSpecQueryBuilder query()
 *
 * @mixin ProductSpecQueryBuilder
 * @mixin Builder<ProductSpec>
 */
class ProductSpec extends Model implements AuditableContract
{
    use Auditable;

    protected $table = 'product_specs';

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Provider, $this>|null
     */
    public function provider(): ?BelongsTo
    {
        $name = $this->getAttribute('name');
        if ($name === 'domain.provider_id') {
            return $this->belongsTo(Provider::class, 'value', 'id');
        }

        return null;
    }

    public function newEloquentBuilder($query): ProductSpecQueryBuilder
    {
        return new ProductSpecQueryBuilder($query);
    }
}
