<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Waterfront\Domain\Translations\Models\TranslationKey;

/**
 * @property int              $id
 * @property int              $product_id
 * @property Product          $product
 * @property int              $contract_period
 * @property int              $billing_period
 * @property ?int             $action_period
 * @property ?int             $action_period_price
 * @property ?int             $translation_key_id
 * @property ?TranslationKey  $priceExplanation
 * @property bool             $is_default
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 *
 * @mixin Builder<ProductPeriod>
 */
class ProductPeriod extends Model
{
    protected $table = 'product_periods';

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<TranslationKey, $this>
     */
    public function priceExplanation(): BelongsTo
    {
        return $this->belongsTo(TranslationKey::class, 'translation_key_id');
    }
}
