<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Waterfront\Apps\API\Compass\Resources\Customer\ListCustomerResource;
use Waterfront\Apps\API\Compass\Support\FieldSelectionProxy;
use Waterfront\Domain\Customers\Models\Customer;

class CustomerFilter
{
    private const array BOOLEAN_FILTERS = [
        'is_verified',
        'is_abuse',
        'has_direct_debit',
    ];

    public function __construct(private readonly Sorting $sorting)
    {
    }

    /** @param Builder<Customer> $query
     *
     * @return Builder<Customer>
     */
    public function apply(Builder $query, Request $request): Builder
    {
        $this->applySearch($query, $request);
        $this->applyBooleanFilters($query, $request);
        $this->applyPaymentTypeFilter($query, $request);
        $this->applyAnonymizedFilter($query, $request);
        $this->applyMigratedFilter($query, $request);

        $this->sorting->apply($query, array_filter((array) $request->input('orderBy', []), 'is_string'));

        $proxy = new FieldSelectionProxy($request, ListCustomerResource::fieldDefinitions());
        $this->eagerLoadRequestedRelations($query, $proxy);

        // Apply field selection last so it overrides any earlier select
        $proxy->applyTo($query, new Customer(), ['id', 'customer_number', 'first_name', 'last_name']);

        return $query;
    }

    /**
     * The migration fields are resolved from the migratedCustomers relation, so it is only
     * worth loading when at least one of them was requested.
     *
     * @param Builder<Customer>             $query
     * @param FieldSelectionProxy<Customer> $proxy
     */
    private function eagerLoadRequestedRelations(Builder $query, FieldSelectionProxy $proxy): void
    {
        if (array_any(ListCustomerResource::MIGRATION_FIELDS, fn (string $field) => $proxy->isRequested($field))) {
            $query->with('migratedCustomers');
        }
    }

    /** @param Builder<Customer> $query */
    private function applySearch(Builder $query, Request $request): void
    {
        $search = strtolower($request->string('search')->trim()->toString());

        if ($search === '' || strlen($search) > 50) {
            return;
        }

        $query->where(function (Builder $q) use ($search): void {
            $q->where('email', 'ilike', "%{$search}%")
                ->orWhere('first_name', 'ilike', "%{$search}%")
                ->orWhere('last_name', 'ilike', "%{$search}%")
                ->orWhere('customer_number', 'ilike', "%{$search}%")
                ->orWhereHas('address', function (Builder $address) use ($search): void {
                    $address->where('street_name', 'ilike', "%{$search}%")
                        ->orWhere('zip_code', 'ilike', "%{$search}%");
                });
        });
    }

    /** @param Builder<Customer> $query */
    private function applyBooleanFilters(Builder $query, Request $request): void
    {
        foreach (self::BOOLEAN_FILTERS as $field) {
            if (! $request->has($field)) {
                continue;
            }

            $query->where($field, filter_var($request->input($field), FILTER_VALIDATE_BOOLEAN));
        }
    }

    /** @param Builder<Customer> $query */
    private function applyPaymentTypeFilter(Builder $query, Request $request): void
    {
        if (! $request->has('payment_type')) {
            return;
        }

        $query->where('payment_type', $request->input('payment_type'));
    }

    /** @param Builder<Customer> $query */
    private function applyAnonymizedFilter(Builder $query, Request $request): void
    {
        if (! $request->has('anonymized')) {
            return;
        }

        $isAnonymized = filter_var($request->input('anonymized'), FILTER_VALIDATE_BOOLEAN);

        if ($isAnonymized) {
            $query->whereNotNull('anonymized_at');
        } else {
            $query->whereNull('anonymized_at');
        }
    }

    /** @param Builder<Customer> $query */
    private function applyMigratedFilter(Builder $query, Request $request): void
    {
        if (! $request->has('is_migrated')) {
            return;
        }

        $isMigrated = filter_var($request->input('is_migrated'), FILTER_VALIDATE_BOOLEAN);

        if ($isMigrated) {
            $query->whereHas('migratedCustomers');
        } else {
            $query->whereDoesntHave('migratedCustomers');
        }
    }
}
