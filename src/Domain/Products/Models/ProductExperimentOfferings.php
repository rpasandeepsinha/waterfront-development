<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Waterfront\Domain\Customers\Models\Customer;

/**
 * @property int                       $id
 * @property string                    $name
 * @property int                       $product_1_id
 * @property int                       $product_2_id
 * @property bool                      $product_1_free
 * @property bool                      $product_2_free
 * @property Product                   $productOne
 * @property Product                   $productTwo
 * @property Collection<int, Customer> $customers
 * @property ?CarbonImmutable          $created_at
 * @property ?CarbonImmutable          $updated_at
 *
 * @mixin Builder<ProductExperimentOfferings>
 */
class ProductExperimentOfferings extends Model implements AuditableContract
{
    use Auditable;

    protected $table = 'product_experiment_offerings';

    /**
     * @return BelongsTo<Product, $this>
     */
    public function productOne(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_1_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function productTwo(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_2_id');
    }

    /**
     * @return BelongsToMany<Customer, $this>
     */
    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(
            Customer::class,
            'product_experiment_offerings_customers',
            'product_experiment_offering_id',
            'customer_id'
        )
            ->withPivot(['redeemed_at'])
            ->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'product_1_free' => 'bool',
            'product_2_free' => 'bool',
        ];
    }
}
