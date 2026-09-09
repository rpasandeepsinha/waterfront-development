<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Waterfront\Apps\API\Compass\Resources\Subscription\SubscriptionResource;
use Waterfront\Apps\API\Compass\Support\FieldSelectionProxy;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class SubscriptionFilter
{
    public function __construct(private readonly Sorting $sorting)
    {
    }

    /**
     * @param Builder<Subscription> $query
     *
     * @return Builder<Subscription>
     */
    public function apply(Builder $query, Request $request): Builder
    {
        $search = strtolower($request->string('search')->trim()->toString());
        $orderBy = array_filter((array) $request->input('orderBy', []), 'is_string');
        $hasProductSort = (bool) array_filter($orderBy, fn (string $o) => str_starts_with($o, 'product.'));
        $hasCategorySort = (bool) array_filter($orderBy, fn (string $o) => str_starts_with($o, 'category.'));

        [$needsProductJoin, $needsCategoryJoin] = $this->applyJoins($query, $request, $search, $hasProductSort, $hasCategorySort);

        $this->applySearch($query, $request, $needsProductJoin, $needsCategoryJoin);
        $this->applyTechnicalStatusFilter($query, $request);
        $this->applyAdministrativeStatusFilter($query, $request);
        $this->applyProductGroupFilter($query, $request, $needsProductJoin);
        $this->applyCategoryAssigneeFilter($query, $request);
        $this->applyCategoryFilter($query, $request);
        $this->applyIdFilter($query, $request);

        $orderBy = $this->applyProductSort($query, $orderBy, $needsProductJoin);
        $orderBy = $this->applyCategoryNameSorting($query, $orderBy, $needsCategoryJoin);
        $this->sorting->apply($query, $orderBy);

        // Apply field selection last so it overrides the wildcard select added by the product join
        $proxy = new FieldSelectionProxy($request, SubscriptionResource::fieldDefinitions());
        $proxy->applyTo($query, new Subscription(), ['id', 'uuid', 'product_uuid', 'customer_id']);

        return $query;
    }

    /**
     * @param Builder<Subscription> $query
     *
     * @return array{bool, bool} [$needsProductJoin, $needsCategoryJoin]
     */
    private function applyJoins(Builder $query, Request $request, string $search, bool $hasProductSort, bool $hasCategorySort): array
    {
        $hasSearch = $search !== '' && strlen($search) <= 50;

        $needsProductJoin = $hasSearch || $request->has('product_group') || $hasProductSort;
        $needsCategoryJoin = $hasSearch || $request->has('category_asignee_metadata_email') || $hasCategorySort;

        if ($needsProductJoin) {
            $query->leftJoin('products', 'products.uuid', '=', 'subscriptions.product_uuid')
                ->select('subscriptions.*');
        }

        if ($needsCategoryJoin) {
            $query->leftJoin('subscription_categories', 'subscription_categories.subscription_id', '=', 'subscriptions.id');

            if (! $needsProductJoin) {
                $query->select('subscriptions.*');
            }
        }

        return [$needsProductJoin, $needsCategoryJoin];
    }

    /**
     * @param Builder<Subscription> $query
     * @param string[]              $orderBy
     *
     * @return string[]
     */
    private function applyProductSort(Builder $query, array $orderBy, bool $joinedProducts): array
    {
        return array_filter($orderBy, function (string $order) use ($query, $joinedProducts): bool {
            if (! str_starts_with($order, 'product.')) {
                return true;
            }

            $direction = str_ends_with($order, '_desc') ? 'desc' : 'asc';
            $column = substr($order, strlen('product.'), -strlen("_{$direction}"));

            if ($joinedProducts) {
                $query->orderBy("products.{$column}", $direction);
            } else {
                $query->orderBy(
                    Product::select($column)->whereColumn('uuid', 'subscriptions.product_uuid'),
                    $direction
                );
            }

            return false;
        });
    }

    /**
     * @param Builder<Subscription> $query
     * @param string[]              $orderBy
     *
     * @return string[]
     */
    private function applyCategoryNameSorting(Builder $query, array $orderBy, bool $joinedCategories): array
    {
        return array_filter($orderBy, function (string $order) use ($query, $joinedCategories): bool {
            if (! str_starts_with($order, 'category.name')) {
                return true;
            }

            $direction = str_ends_with($order, '_desc') ? 'desc' : 'asc';

            if ($joinedCategories) {
                $query->orderByRaw("subscription_categories.name {$direction} NULLS LAST");
            } else {
                $query->orderByRaw(
                    "(SELECT name FROM subscription_categories WHERE subscription_id = subscriptions.id LIMIT 1) {$direction} NULLS LAST"
                );
            }

            return false;
        });
    }

    /** @param Builder<Subscription> $query */
    private function applySearch(Builder $query, Request $request, bool $joinedProducts, bool $joinedCategories): void
    {
        $search = strtolower($request->string('search')->trim()->toString());

        if ($search === '' || strlen($search) > 50) {
            return;
        }

        $query->where(function (Builder $q) use ($search, $joinedProducts, $joinedCategories): void {
            $q->where('subscriptions.domain', 'ilike', "%{$search}%");

            if ($joinedProducts) {
                $q->orWhere('products.name', 'ilike', "%{$search}%")
                    ->orWhere('products.slug', 'ilike', "%{$search}%");
            }

            if ($joinedCategories) {
                $q->orWhereRaw(
                    "lower(subscription_categories.assignee_metadata->>'email') like ?",
                    ["%{$search}%"],
                );
            }
        });
    }

    /** @param Builder<Subscription> $query */
    private function applyTechnicalStatusFilter(Builder $query, Request $request): void
    {
        $values = array_values(array_filter((array) $request->input('technical_status'), 'is_string'));

        if ($values !== []) {
            $query->whereIn('technical_status', $values);
        }
    }

    /** @param Builder<Subscription> $query */
    private function applyAdministrativeStatusFilter(Builder $query, Request $request): void
    {
        $values = array_values(array_filter((array) $request->input('administrative_status'), 'is_string'));

        if ($values !== []) {
            $query->whereIn('administrative_status', $values);
        }
    }

    /** @param Builder<Subscription> $query */
    private function applyProductGroupFilter(Builder $query, Request $request, bool $joinedProducts): void
    {
        if (! $request->has('product_group')) {
            return;
        }

        $productGroupType = ProductGroupType::tryFrom($request->string('product_group')->toString());

        if ($productGroupType === null) {
            return;
        }

        if ($joinedProducts) {
            $query->whereIn('products.product_group_id', function (\Illuminate\Database\Query\Builder $sub) use ($productGroupType): void {
                $sub->select('id')->from('product_groups')->where('slug', $productGroupType);
            });
        } else {
            $query->whereHas('product.productGroup', function (Builder $productGroupQuery) use ($productGroupType): void {
                $productGroupQuery->where('slug', $productGroupType);
            });
        }
    }

    /** @param Builder<Subscription> $query */
    private function applyCategoryFilter(Builder $query, Request $request): void
    {
        $values = array_values(array_filter((array) $request->input('category'), 'is_string'));

        if ($values === []) {
            return;
        }

        $includeNull = in_array('null', $values, true);
        $namedValues = array_values(array_filter($values, fn (string $v) => $v !== 'null'));

        $query->where(function (Builder $q) use ($namedValues, $includeNull): void {
            if ($namedValues !== []) {
                $q->whereHas('category', fn (Builder $cat) => $cat->whereIn('name', $namedValues));
            }

            if ($includeNull) {
                $q->orWhereDoesntHave('category')
                    ->orWhereHas('category', fn (Builder $cat) => $cat->whereNull('name'));
            }
        });
    }

    /** @param Builder<Subscription> $query */
    private function applyIdFilter(Builder $query, Request $request): void
    {
        $idValues = array_values(array_filter((array) $request->input('id'), 'is_string'));

        if ($idValues !== []) {
            $query->whereIn('id', $idValues);
        }

        $uuidValues = array_values(array_filter((array) $request->input('uuid'), 'is_string'));

        if ($uuidValues !== []) {
            $query->whereIn('uuid', $uuidValues);
        }
    }

    /** @param Builder<Subscription> $query */
    private function applyCategoryAssigneeFilter(Builder $query, Request $request): void
    {
        $email = strtolower($request->string('category_asignee_metadata_email')->trim()->toString());

        if ($email === '') {
            return;
        }

        if ($email === 'unassigned') {
            $query->where(function (Builder $q): void {
                $q->whereNull('subscription_categories.assignee_metadata');
            });

            return;
        }

        $query->whereRaw(
            "lower(subscription_categories.assignee_metadata->>'email') like ?",
            ["%{$email}%"],
        );
    }
}
