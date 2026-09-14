<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Migrations\Lenses;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\LensRequest;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;
use Waterfront\Apps\Nova\General\Traits\ResolvesActionsAndFilters;
use Waterfront\Apps\Nova\Migrations\Filters\NovaMigrationTotalBatchFilter;

class NovaMigratedTotalLens extends Lens
{
    use ResolvesActionsAndFilters;

    /** @phpstan-ignore-next-line */
    public static function query(LensRequest $request, $query): Builder
    {
        $batchValue = self::getBatchGroupFilterValue($request);

        return $request->withOrdering(
            $request->withFilters(
                $query
                    ->select([
                        'reference_name' => 'mc_orig.reference_name',

                        'migrated_customers_count' => DB::table('migrated_customers', 'mc')
                            ->selectRaw('count(distinct mc.reference_customer_number)')
                            ->whereRaw('mc.reference_name = mc_orig.reference_name')
                            ->when(
                                $batchValue !== null,
                                fn ($query) => $query->where('mc.group_type', $batchValue),
                            ),

                        'migrated_subscriptions_count' => DB::table('migrated_customers', 'mc')
                            ->selectRaw('count(distinct ms.reference_subscription_id)')
                            ->leftJoin('customer_migrated_customer as cmc', 'cmc.migrated_customer_id', '=', 'mc.id')
                            ->leftJoin('customers as c', 'cmc.customer_id', '=', 'c.id')
                            ->leftJoin('subscriptions as s', 'c.id', '=', 's.customer_id')
                            ->leftJoin('migrated_subscription_subscription as mss', 'mss.subscription_id', '=', 's.id')
                            ->leftJoin('migrated_subscriptions as ms', 'mss.migrated_subscription_id', '=', 'ms.id')
                            ->whereRaw('mc.reference_name = mc_orig.reference_name')
                            ->when(
                                $batchValue !== null,
                                fn ($query) => $query->where('mc.group_type', $batchValue),
                            ),
                    ])
                    ->from('migrated_customers', 'mc_orig')
                    ->groupBy('mc_orig.reference_name')
                    ->orderBy('mc_orig.reference_name'),
            ),
            fn ($query) => $query->orderBy('mc_orig.reference_name', 'desc'),
        );
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make('Business unit', 'reference_name'),
            Text::make('Gemigreerde klanten', 'migrated_customers_count'),
            Text::make('Gemigreerde abonnementen', 'migrated_subscriptions_count'),
        ];
    }

    public function uriKey(): string
    {
        return 'migrated-total';
    }

    /**
     * @return array<int, Filter>
     */
    public function filters(NovaRequest $request): array
    {
        return [
            // update the key in the getBatchGroupFilterValue function if you add a filter above this one
            $this->resolveFilter(NovaMigrationTotalBatchFilter::class),
        ];
    }

    public function name(): string
    {
        return 'Migratie totaal';
    }

    /**
     * @return array<int, string>
     */
    protected static function columns(): array
    {
        return [
            'reference_name',
            'migrated_customers_count',
            'migrated_subscriptions_count',
        ];
    }

    private static function getBatchGroupFilterValue(LensRequest $request): ?string
    {
        // A bit hacky, but it works.
        /** @var string $queryFilters */
        $queryFilters = $request->query('filters');

        /** @var string $decodedFilters */
        $decodedFilters = base64_decode($queryFilters, true);

        /** @var array<int, array<string,string>> $filters */
        $filters = json_decode($decodedFilters, true);

        /** @var string|null $batchValue */
        $batchValue = Arr::get(
            $filters,
            '0.' . NovaMigrationTotalBatchFilter::class,
        );

        if ($batchValue === null || $batchValue === '') {
            return null;
        }

        return $batchValue;
    }
}
