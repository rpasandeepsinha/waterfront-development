<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Waterfront\Domain\Products\Models\Product;

/**
 * @property int              $id
 * @property string           $kpn_product_code
 * @property int              $product_id
 * @property int              $contract_period
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property Product          $product
 *
 * @mixin Builder<Microsoft365KpnProduct>
 */
class Microsoft365KpnProduct extends Model
{
    protected $table = 'microsoft365_kpn_product';

    protected $fillable = [
        'product_id',
        'contract_period',
        'kpn_product_code',
    ];

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
