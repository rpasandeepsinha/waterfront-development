<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Enums\ProductType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

/**
 * @extends Builder<Subscription>
 */
class SubscriptionQueryBuilder extends Builder
{
    public function whereLikeDomain(string $value): self
    {
        return $this->where('domain', 'like', "%$value%");
    }

    public function whereAdministrativeStatusActive(): self
    {
        return $this->where('administrative_status', AdministrativeStatus::ACTIVE->value);
    }

    /**
     * @param string|array<string> $value
     */
    public function whereProductGroup(string|array $value): self
    {
        return $this->whereHas('product.productGroup', function (Builder $query) use ($value): Builder {
            if (is_array($value)) {
                return $query->whereIn('uuid', $value);
            }

            return $query->where('uuid', $value);
        });
    }

    public function whereProductGroupType(ProductGroupType $productGroupTypeSlug): self
    {
        return $this->whereHas(
            'product.productGroup',
            fn (Builder $productGroupQuery): Builder => $productGroupQuery->where('slug', $productGroupTypeSlug),
        );
    }

    public function whereProductSpecNameAndValueIsTrue(ProductSpecName $productSpecName): self
    {
        return $this->whereHas(
            'product.productSpecs',
            fn (Builder $productSpecQuery): Builder => $productSpecQuery->where(
                'name',
                $productSpecName,
            )->whereIn('value', ['1', 'yes', true, 1]),
        );
    }

    /**
     * @param ProductSpecName[] $productSpecNames
     */
    public function whereOneOfProductSpecNamesAndValueIsTrue(array $productSpecNames): self
    {
        return $this->whereHas(
            'product.productSpecs',
            fn (Builder $productSpecQuery): Builder => $productSpecQuery->whereIn(
                'name',
                $productSpecNames,
            )->whereIn('value', ['1', 'yes', true, 1]),
        );
    }

    /**
     * @param array<int, ProductGroupType> $productGroups
     */
    public function whereProductGroupTypes(array $productGroups): self
    {
        return $this->whereHas(
            'product.productGroup',
            fn (Builder $productGroupQuery): Builder => $productGroupQuery->whereIn('slug', $productGroups),
        );
    }

    public function whereProductName(string $name): self
    {
        return $this->select('subscriptions.*')
            ->join(
                'products',
                'subscriptions.product_uuid',
                '=',
                'products.uuid',
            )
            ->where('products.name', $name);
    }

    public function whereProductSlug(string $slug): self
    {
        return $this->select('subscriptions.*')
            ->join(
                'products',
                'subscriptions.product_uuid',
                '=',
                'products.uuid',
            )
            ->where('products.slug', $slug);
    }

    public function whereProductSlugIsNot(string $slug): self
    {
        return $this->select('subscriptions.*')
            ->join(
                'products',
                'subscriptions.product_uuid',
                '!=',
                'products.uuid',
            )
            ->where('products.slug', $slug);
    }

    /**
     * @param array<ProductType|string> $slugs
     */
    public function whereProductSlugs(array $slugs): self
    {
        return $this->select('subscriptions.*')
            ->join(
                'products',
                'subscriptions.product_uuid',
                '=',
                'products.uuid',
            )
            ->whereIn('products.slug', $slugs);
    }

    /**
     * @param array<string> $names
     */
    public function whereProductNames(array $names): self
    {
        return $this->select('subscriptions.*')
            ->join(
                'products',
                'subscriptions.product_uuid',
                '=',
                'products.uuid',
            )
            ->whereIn('products.name', $names);
    }

    public function whereProductUuid(string $productUuid): self
    {
        return $this->where('product_uuid', $productUuid);
    }
}
