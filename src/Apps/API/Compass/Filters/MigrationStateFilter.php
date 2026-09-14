<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Waterfront\Apps\API\Compass\Resources\Migration\MigrationStateResource;
use Waterfront\Apps\API\Compass\Support\FieldDefinition;
use Waterfront\Apps\API\Compass\Support\FieldSelectionProxy;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class MigrationStateFilter
{
    /**
     * @var string[]
     */
    private const array ALWAYS_SELECTED_COLUMNS = ['id', 'uuid'];

    public function __construct(
        private readonly Sorting $sorting,
    ) {
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
        $hasProductSort = (bool) array_filter($orderBy, fn (string $order) => str_starts_with($order, 'product.'));

        $joinedProducts = $this->applyProductJoin($query, $request, $search, $hasProductSort);

        $this->applySearch($query, $search, $joinedProducts);
        $this->applyProductGroupFilter($query, $request, $joinedProducts);
        $this->applyBusinessUnitFilter($query, $request);
        $this->applyStatusFilter($query, $request);

        $orderBy = $this->applyProductSort($query, $orderBy, $joinedProducts);
        $this->sorting->apply($query, $this->qualifySubscriptionColumns($orderBy));

        // Apply field selection last so it overrides the wildcard select added by the product join
        $proxy = new FieldSelectionProxy($request, MigrationStateResource::fieldDefinitions());
        $proxy->applyTo($query, new Subscription(), ['id', 'uuid', 'customer_id', 'product_uuid']);

        return $query;
    }

    /**
     * @param Builder<Subscription> $query
     */
    private function applyProductJoin(Builder $query, Request $request, string $search, bool $hasProductSort): bool
    {
        $needsProductJoin = $this->isSearchable($search) || $request->filled('product_group') || $hasProductSort;

        if ($needsProductJoin) {
            $query->leftJoin('products', 'products.uuid', '=', 'subscriptions.product_uuid')->select('subscriptions.*');
        }

        return $needsProductJoin;
    }

    /**
     * @param Builder<Subscription> $query
     */
    private function applySearch(Builder $query, string $search, bool $joinedProducts): void
    {
        if (! $this->isSearchable($search)) {
            return;
        }

        $term = "%{$search}%";

        $query->where(function (Builder $builder) use ($term, $joinedProducts): void {
            $builder->where('subscriptions.domain', 'ilike', $term);

            if ($joinedProducts) {
                $builder->orWhere('products.name', 'ilike', $term)->orWhere('products.slug', 'ilike', $term);
            }

            $builder->orWhereHas('customer.migratedCustomers', function (Builder $migratedCustomer) use ($term): void {
                $migratedCustomer->where('group_type', 'ilike', $term)->orWhere(
                    'reference_customer_number',
                    'ilike',
                    $term,
                );
            });

            $builder->orWhereHas('customer', function (Builder $customer) use ($term): void {
                $customer->where(
                    'email',
                    'ilike',
                    $term,
                )->orWhereRaw('cast(customers.customer_number as text) ilike ?', [$term]);
            });

            $builder->orWhereHas('migratedSubscriptions', function (Builder $migratedSubscription) use ($term): void {
                $migratedSubscription->where('reference_subscription_id', 'ilike', $term);
            });
        });
    }

    /**
     * @param Builder<Subscription> $query
     */
    private function applyProductGroupFilter(Builder $query, Request $request, bool $joinedProducts): void
    {
        if (! $request->filled('product_group')) {
            return;
        }

        $productGroupType = ProductGroupType::tryFrom($request->string('product_group')->toString());

        if ($productGroupType === null) {
            return;
        }

        if ($joinedProducts) {
            $query->whereIn('products.product_group_id', function (QueryBuilder $sub) use ($productGroupType): void {
                $sub->select('id')->from('product_groups')->where('slug', $productGroupType);
            });

            return;
        }

        $query->whereHas('product.productGroup', function (Builder $productGroup) use ($productGroupType): void {
            $productGroup->where('slug', $productGroupType);
        });
    }

    /**
     * @param Builder<Subscription> $query
     */
    private function applyBusinessUnitFilter(Builder $query, Request $request): void
    {
        if (! $request->filled('business_unit')) {
            return;
        }

        $businessUnit = $request->string('business_unit')->toString();

        $query->whereHas('customer.migratedCustomers', function (Builder $migratedCustomer) use ($businessUnit): void {
            $migratedCustomer->where('reference_name', $businessUnit);
        });
    }

    /**
     * @param Builder<Subscription> $query
     */
    private function applyStatusFilter(Builder $query, Request $request): void
    {
        $status = $request->string('status')->trim()->toString();

        if ($status === '') {
            return;
        }

        match ($status) {
            'placeholder' => $this->whereAnyDeploymentProvider($query, '='),
            'not-placeholder' => $this->whereAnyDeploymentProvider($query, '!='),
            'ok' => $query->whereIn('subscriptions.technical_status', [
                TechnicalStatus::OK->value,
                DomainStatus::ACTIVE->value,
            ]),
            'failed' => $query->whereIn('subscriptions.technical_status', [
                TechnicalStatus::FAILED->value,
                TechnicalStatus::PENDING->value,
                DomainStatus::FAILED->value,
            ]),
            'administratively-not-successful' => $this->whereMigratedCustomerFlagIsFalse(
                $query,
                'administrative_successful',
            ),
            'invoicing-not-enabled' => $this->whereMigratedCustomerFlagIsFalse($query, 'enable_invoicing'),
            'migration-not-successful' => $this->whereMigratedCustomerFlagIsFalse($query, 'successful'),
            default => $query,
        };
    }

    /**
     * @param Builder<Subscription> $query
     *
     * @return Builder<Subscription>
     */
    private function whereAnyDeploymentProvider(Builder $query, string $operator): Builder
    {
        return $query->where(function (Builder $builder) use ($operator): void {
            foreach (MigrationStateResource::DEPLOYMENT_PROVIDER_RELATIONS as $relation) {
                $builder->orWhereRelation($relation, 'slug', $operator, ProviderSlug::PLACEHOLDER->value);
            }
        });
    }

    /**
     * @param Builder<Subscription> $query
     *
     * @return Builder<Subscription>
     */
    private function whereMigratedCustomerFlagIsFalse(Builder $query, string $column): Builder
    {
        return $query->whereRelation('customer.migratedCustomers', $column, false);
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
                    $direction,
                );
            }

            return false;
        });
    }

    /**
     * @param string[] $orderBy
     *
     * @return string[]
     */
    private function qualifySubscriptionColumns(array $orderBy): array
    {
        $subscriptionColumns = [
            ...self::ALWAYS_SELECTED_COLUMNS,
            ...array_values(array_filter(
                array_map(
                    fn (FieldDefinition $definition) => $definition->column,
                    MigrationStateResource::fieldDefinitions(),
                ),
                fn (?string $column) => $column !== null,
            )),
        ];

        return array_map(function (string $order) use ($subscriptionColumns): string {
            $lastUnderscore = strrpos($order, '_');

            if ($lastUnderscore === false) {
                return $order;
            }

            $column = substr($order, 0, $lastUnderscore);

            return in_array($column, $subscriptionColumns, true) ? "subscriptions.{$order}" : $order;
        }, $orderBy);
    }

    private function isSearchable(string $search): bool
    {
        return $search !== '' && strlen($search) <= 50;
    }
}
