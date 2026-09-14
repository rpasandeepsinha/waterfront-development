<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Microsoft365\Models\Microsoft365KpnProduct;
use Waterfront\Domain\Pricing\Models\ProductIntroductionDiscount;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Enums\ProductType;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Models\ProductAllowedChange;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\VPS\Models\EnvironmentProduct;

/**
 * @property int                                          $id
 * @property int                                          $product_group_id
 * @property ProductGroup                                 $productGroup
 * @property Collection<int, ProductPromotion>            $productPromotions
 * @property Collection<int, ProductSpec>                 $productSpecs
 * @property Collection<int, Subscription>                $subscriptions
 * @property Collection<int, ProductAllowedChange>        $allowedChanges
 * @property Collection<int, ProductPeriod>               $periods
 * @property Collection<int, ProductAddonCoupling>        $addonCouplings
 * @property Collection<int, ProductIntroductionDiscount> $introductionDiscounts
 * @property int                                          $weight
 * @property string                                       $name
 * @property string                                       $slug
 * @property string                                       $uuid
 * @property ?string                                      $description
 * @property bool                                         $orderable
 * @property ?CarbonImmutable                             $created_at
 * @property ?CarbonImmutable                             $updated_at
 * @property ?CarbonImmutable                             $deleted_at
 * @property Collection<int, Microsoft365KpnProduct>      $microsoft365KpnProducts
 * @property ?ProductDiscount                             $productDiscount
 *
 * @mixin Builder<Product>
 */
class Product extends Model implements AuditableContract
{
    use Auditable;
    use SoftDeletes;

    protected $table = 'products';

    protected $attributes = [
        'weight' => 9999,
    ];

    public static function boot(): void
    {
        parent::boot();

        self::creating(
            function ($model): void {
                $model->uuid = Str::uuid()->toString();
                if ($model->slug === null) {
                    $str = trim(strtolower($model->name));
                    $productName = preg_replace('/[\s.,-]+/', '_', $str);

                    $model->slug = $model->productGroup !== null
                        ? strtolower($model->productGroup->slug->value) . '_' . $productName
                        : $productName;
                }
            },
        );
    }

    /**
     * @return HasMany<ProductAllowedChange, $this>
     */
    public function allowedProductUpgrades(): HasMany
    {
        return $this->hasMany(ProductAllowedChange::class, 'from_product_id')->where(
            'change_type',
            ProductChangeType::UPGRADE,
        );
    }

    /**
     * @return HasMany<ProductAllowedChange, $this>
     */
    public function allowedChanges(): HasMany
    {
        return $this->hasMany(ProductAllowedChange::class, 'from_product_id');
    }

    /**
     * @return HasMany<ProductAllowedChange, $this>
     */
    public function allowedProductDowngrades(): HasMany
    {
        return $this->hasMany(ProductAllowedChange::class, 'from_product_id')->where(
            'change_type',
            ProductChangeType::DOWNGRADE,
        );
    }

    /**
     * @return HasMany<ProductAllowedChange, $this>
     */
    public function allowedProductReinstalls(): HasMany
    {
        return $this->hasMany(ProductAllowedChange::class, 'from_product_id')->where(
            'change_type',
            ProductChangeType::REINSTALL,
        );
    }

    /**
     * @return HasMany<ProductAddonCoupling, $this>
     */
    public function addonCouplings(): HasMany
    {
        return $this->hasMany(ProductAddonCoupling::class, 'parent_product_id')->whereHas('addonProduct');
    }

    public function isBaseKitProduct(): bool
    {
        return $this->productSpecs()->where('name', ProductSpecName::BASEKIT_PACKAGE_REFERENCE)->exists();
    }

    public function isSitebuilderProduct(): bool
    {
        return $this->slug === ProductType::SITEBUILDER->value || $this->isBaseKitProduct();
    }

    /**
     * @return BelongsTo<ProductGroup, $this>
     */
    public function productGroup(): BelongsTo
    {
        return $this->belongsTo(ProductGroup::class)->withTrashed();
    }

    /**
     * @return HasMany<ProductIntroductionDiscount, $this>
     */
    public function introductionDiscounts(): HasMany
    {
        return $this->hasMany(ProductIntroductionDiscount::class);
    }

    /**
     * @return HasMany<ProductSpec, $this>
     */
    public function productSpecs(): HasMany
    {
        return $this->hasMany(ProductSpec::class);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'product_uuid', 'uuid');
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasMany<EnvironmentProduct, $this>
     */
    public function cloudstackEnvironments(): HasMany
    {
        return $this->hasMany(EnvironmentProduct::class);
    }

    /**
     * @return hasMany<Microsoft365KpnProduct, $this>
     */
    public function microsoft365KpnProducts(): HasMany
    {
        return $this->hasMany(Microsoft365KpnProduct::class, 'product_id', 'id');
    }

    /**
     * @return HasMany<ProductPromotion, $this>
     */
    public function productPromotions(): HasMany
    {
        return $this->hasMany(ProductPromotion::class, 'product_id', 'id');
    }

    /**
     * @return HasOne<ProductDiscount, $this>
     */
    public function productDiscount(): HasOne
    {
        return $this->hasOne(ProductDiscount::class);
    }

    /**
     * @return HasMany<ProductPeriod, $this>
     */
    public function periods(): HasMany
    {
        return $this->hasMany(ProductPeriod::class, 'product_id', 'id');
    }

    /**
     * Determine whether this subscription is mail-only, based on product specs.
     *
     * In the new logic, we rely on a custom product spec named 'uses_mail_only_server'.
     * If 'uses_mail_only_server' === '1', it's considered a "mail-only" subscription.
     */
    public function isMailOnlyServer(): bool
    {
        return (
            $this->productSpecs->firstWhere('name', ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER->value)?->value
            === '1'
        );
    }

    public function isDnsProduct(): bool
    {
        return in_array(
            ProductType::tryFrom($this->slug),
            ProductType::getDnsProductTypes(),
            true,
        );
    }

    public function isDomainProduct(): bool
    {
        return $this->productGroup->slug === ProductGroupType::EXTENSION;
    }

    public function isRedirectProduct(): bool
    {
        return $this->productGroup->slug === ProductGroupType::REDIRECT;
    }

    public function isHostingProduct(): bool
    {
        return $this->productGroup->slug === ProductGroupType::HOSTING;
    }

    public function isBackupProduct(): bool
    {
        return $this->productGroup->slug === ProductGroupType::BACKUP;
    }
}
