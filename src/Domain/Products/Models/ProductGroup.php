<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\Enums\ProductGroupType;

/**
 * @property int                       $id
 * @property string                    $uuid
 * @property string                    $name
 * @property ProductGroupType          $slug
 * @property int                       $ledger_code
 * @property float                     $default_rate
 * @property int                       $default_billing_period
 * @property int                       $default_contract_period
 * @property ?CarbonImmutable          $created_at
 * @property ?CarbonImmutable          $updated_at
 * @property ?CarbonImmutable          $deleted_at
 * @property Collection<int, Customer> $customers
 * @property Collection<int, Product>  $products
 * @property ?Pivot                    $pivot
 *
 * @mixin Builder<ProductGroup>
 */
class ProductGroup extends Model
{
    use SoftDeletes;

    protected $table = 'product_groups';

    public static function boot(): void
    {
        parent::boot();

        self::creating(function ($model): void {
            $model->uuid = Str::uuid()->toString();
        });
    }

    /**
     * @return BelongsToMany<Customer, $this>
     */
    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class)->withPivot('discount');
    }

    public function getDefaultRatePercentageAttribute(): int
    {
        return intval(round($this->default_rate * 100));
    }

    public function setDefaultRatePercentageAttribute(int $value): void
    {
        $rate = $value === 0 ? 0.00 : round($value / 100, 2);
        $this->attributes['default_rate'] = $rate;
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    protected function casts(): array
    {
        return [
            'slug' => ProductGroupType::class,
        ];
    }
}
