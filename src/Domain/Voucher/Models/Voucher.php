<?php

declare(strict_types=1);

namespace Waterfront\Domain\Voucher\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Voucher\Enum\VoucherAmountType;

/**
 * @property int               $id
 * @property string            $uuid
 * @property string            $display_name
 * @property string            $internal_name
 * @property ?string           $description
 * @property ?string           $product_uuid
 * @property ?string           $product_group_uuid
 * @property string            $code
 * @property int               $amount
 * @property VoucherAmountType $amount_type
 * @property ?int              $max_claims
 * @property ?int              $billing_period
 * @property ?int              $contract_period
 * @property ?CarbonImmutable  $expiration_date
 * @property ?CarbonImmutable  $created_at
 * @property ?CarbonImmutable  $updated_at
 * @property ?CarbonImmutable  $deleted_at
 * @property ?Product          $product
 * @property ProductGroup      $productGroup
 * @property VoucherClaim      $claims
 * @property bool              $apply_with_discount
 * @property bool              $allow_multiple_claims_same_customer
 *
 * @mixin Builder<Voucher>
 */
class Voucher extends Model
{
    use SoftDeletes;

    public static function boot(): void
    {
        parent::boot();

        self::creating(function (self $model): void {
            $model->uuid ??= Str::uuid()->toString();
        });
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_uuid', 'uuid');
    }

    /**
     * @return BelongsTo<ProductGroup, $this>
     */
    public function productGroup(): BelongsTo
    {
        return $this->belongsTo(ProductGroup::class, 'product_group_uuid', 'uuid');
    }

    /**
     * @return HasMany<VoucherClaim, $this>
     */
    public function claims(): HasMany
    {
        return $this->hasMany(VoucherClaim::class);
    }

    protected function casts(): array
    {
        return [
            'amount_type' => VoucherAmountType::class,
            'expiration_date' => 'datetime',
            'apply_with_discount' => 'boolean',
            'allow_multiple_claims_same_customer' => 'boolean',
        ];
    }
}
