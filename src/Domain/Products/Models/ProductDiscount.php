<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Waterfront\Domain\Customers\Models\Customer;

/**
 * @property int                       $id
 * @property string                    $name
 * @property ?string                   $description
 * @property ?int                      $product_id
 * @property Collection<int, Customer> $customers
 * @property Product                   $product
 * @property ?CarbonImmutable          $created_at
 * @property ?CarbonImmutable          $updated_at
 * @property ?CarbonImmutable          $deleted_at
 *
 * @mixin Builder<ProductDiscount>
 */
class ProductDiscount extends Model
{
    use SoftDeletes;

    protected $table = 'product_discounts';

    /**
     * @return BelongsToMany<Customer, $this>
     */
    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class)->withTimestamps();
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
