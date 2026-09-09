<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int              $composed_product_id
 * @property ?int             $wp_composed_product_id
 * @property int              $mail_only_product_id
 * @property int              $web_only_product_id
 * @property ?int             $wp_web_only_product_id
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read Product $composedProduct
 * @property-read ?Product $wpComposedProduct
 * @property-read Product $mailOnlyProduct
 * @property-read Product $webOnlyProduct
 * @property-read ?Product $wpWebOnlyProduct
 *
 * @mixin Builder<HostingProductComposition>
 */
class HostingProductComposition extends Model
{
    protected $table = 'hosting_product_compositions';

    /**
     * @return BelongsTo<Product, $this>
     */
    public function composedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'composed_product_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function wpComposedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'wp_composed_product_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function mailOnlyProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'mail_only_product_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function webOnlyProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'web_only_product_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function wpWebOnlyProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'wp_web_only_product_id');
    }
}
