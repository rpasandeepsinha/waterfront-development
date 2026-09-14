<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Customers\Lenses;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ExportAsCsv;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\LensRequest;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;
use SandwaveIo\LighthouseAuthBase\Permissions\Permissions;
use Waterfront\Apps\Nova\Customers\Filters\MigratedBatchFilter;
use Waterfront\Apps\Nova\General\Traits\ResolvesActionsAndFilters;
use Waterfront\Infra\Authentication\AuthorizationChecker;
use Waterfront\Infra\Translation\TranslatorInterface;

class MigratedCustomersLens extends Lens
{
    use ResolvesActionsAndFilters;

    private const string FIELD_ID = 'id';
    private const string FIELD_CUSTOMER_ID = 'customer_id';
    private const string FIELD_CUSTOMER_NUMBER = 'customer_number';
    private const string FIELD_ORGANIZATION = 'organization';
    private const string FIELD_FIRST_NAME = 'first_name';
    private const string FIELD_LAST_NAME = 'last_name';
    private const string FIELD_REFERENCE_CUSTOMER_ID = 'reference_customer_number';
    private const string FIELD_SUBSCRIPTIONS = 'subscriptions';
    private const string FIELD_SUBSCRIPTIONS_COUNT = 'subscriptions_count';
    private const string FIELD_MIGRATED_SUBSCRIPTIONS = 'migratedSubscriptions';
    private const string FIELD_BATCH_ID = 'group_type';
    private const string TABLE_CUSTOMERS = 'customers';
    private const string TABLE_PIVOT = 'customer_migrated_customer';
    private const string TABLE_MIGRATED_CUSTOMERS = 'migrated_customers';

    private const string PIVOT_CUSTOMER_ID = self::TABLE_PIVOT . '.' . self::FIELD_CUSTOMER_ID;
    private const string PIVOT_MIGRATED_CUSTOMER_ID = self::TABLE_PIVOT . '.migrated_customer_id';
    private const string CUSTOMERS_CUSTOMER_ID = self::TABLE_CUSTOMERS . '.' . self::FIELD_ID;
    private const string CUSTOMERS_CUSTOMER_NUMBER = self::TABLE_CUSTOMERS . '.' . self::FIELD_CUSTOMER_NUMBER;
    private const string CUSTOMERS_ORGANIZATION = self::TABLE_CUSTOMERS . '.' . self::FIELD_ORGANIZATION;
    private const string CUSTOMERS_FIRST_NAME = self::TABLE_CUSTOMERS . '.' . self::FIELD_FIRST_NAME;
    private const string CUSTOMERS_LAST_NAME = self::TABLE_CUSTOMERS . '.' . self::FIELD_LAST_NAME;
    private const string MIGRATED_CUSTOMERS_ID = self::TABLE_MIGRATED_CUSTOMERS . '.' . self::FIELD_ID;
    private const string MIGRATED_CUSTOMERS_REFERENCE_CUSTOMER_ID =
        self::TABLE_MIGRATED_CUSTOMERS . '.' . self::FIELD_REFERENCE_CUSTOMER_ID;
    private const string MIGRATED_CUSTOMERS_BATCH_ID = self::TABLE_MIGRATED_CUSTOMERS . '.' . self::FIELD_BATCH_ID;

    public static function query(LensRequest $request, Builder $query): Builder
    {
        return $request->withOrdering($request->withFilters(
            $query
                ->join(self::TABLE_PIVOT, self::PIVOT_CUSTOMER_ID, '=', self::CUSTOMERS_CUSTOMER_ID)
                ->join(
                    self::TABLE_MIGRATED_CUSTOMERS,
                    self::PIVOT_MIGRATED_CUSTOMER_ID,
                    '=',
                    self::MIGRATED_CUSTOMERS_ID,
                )
                ->select(self::columns())
                ->withCount([
                    self::FIELD_SUBSCRIPTIONS => fn (Builder $query) => $query->has(self::FIELD_MIGRATED_SUBSCRIPTIONS),
                ]),
        ));
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make(self::translate('nova-resource-labels.waterfront-customer-id'), self::FIELD_ID)->sortable(),
            Text::make(
                self::translate('nova-resource-labels.waterfront-customer-number'),
                self::FIELD_CUSTOMER_NUMBER,
            )->sortable(),
            Text::make(
                self::translate('nova-resource-labels.reference-customer-id'),
                self::FIELD_REFERENCE_CUSTOMER_ID,
            )->sortable(),
            Text::make(
                self::translate('nova-resource-labels.migrated-subscriptions-count'),
                self::FIELD_SUBSCRIPTIONS_COUNT,
            )->sortable(),
        ];
    }

    /**
     * @return array<int, Filter>
     */
    public function filters(NovaRequest $request): array
    {
        return [
            $this->resolveFilter(MigratedBatchFilter::class),
        ];
    }

    /**
     * @return array<int, Action>
     */
    public function actions(NovaRequest $request): array
    {
        /** @var AuthorizationChecker $authorizationChecker */
        $authorizationChecker = App::make(AuthorizationChecker::class);

        return [
            ExportAsCsv::make()
                ->nameable('migrated-customers-' . date('Y-m-d-H-i-s') . '.csv')
                ->canSee(fn (Request $request) => $authorizationChecker->can(Permissions::EXPORT_CUSTOMER_DATA)),
        ];
    }

    public function uriKey(): string
    {
        return 'migrated-customers';
    }

    public static function translate(string $translationKey): string
    {
        $translator = resolve(TranslatorInterface::class);

        return $translator->translate($translationKey);
    }

    /**
     * @return array<int, string>
     */
    protected static function columns(): array
    {
        return [
            self::CUSTOMERS_CUSTOMER_ID,
            self::CUSTOMERS_CUSTOMER_NUMBER,
            self::CUSTOMERS_ORGANIZATION,
            self::CUSTOMERS_FIRST_NAME,
            self::CUSTOMERS_LAST_NAME,
            self::MIGRATED_CUSTOMERS_REFERENCE_CUSTOMER_ID,
            self::MIGRATED_CUSTOMERS_BATCH_ID,
        ];
    }
}
