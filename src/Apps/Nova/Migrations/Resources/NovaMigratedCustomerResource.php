<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Migrations\Resources;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\Boolean as NovaBoolField;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use SandwaveIo\LighthouseAuthBase\Permissions\Permissions;
use Waterfront\Apps\Nova\Customers\Resources\NovaCustomerResource;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\General\Traits\ResolvesActionsAndFilters;
use Waterfront\Apps\Nova\Migrations\Actions\NovaMigratedCustomerEnableInvoicingAction;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Infra\Authentication\AuthorizationChecker;

/** @property MigratedCustomer $resource */
class NovaMigratedCustomerResource extends Resource
{
    use ResolvesActionsAndFilters;

    public static string $model = MigratedCustomer::class;

    public static $displayInNavigation = false;

    public static $globallySearchable = false;

    public static function getTranslationKey(): string
    {
        return 'migrated.customer';
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make(self::translate('ID'), 'id')->sortable()->hideFromDetail(),

            Text::make(self::translate('migrated.customer.source_name'), 'reference_name')->readonly(),
            Text::make(self::translate('migrated.customer.group_type'), 'group_type')->readonly(),
            Text::make(
                self::translate('migrated.customer.reference_customer_id'),
                'reference_customer_number',
            )->readonly(),
            DateTime::make(self::translate('migrated.customer.migration_date'), 'migrated_at'),

            NovaBoolField::make(
                self::translate('migrated.customer.administrative_successful'),
                'administrative_successful',
            ),

            NovaBoolField::make(self::translate('migrated.customer.enable_invoicing'), 'enable_invoicing')
                ->help(self::translate('nova-action.enable-invoicing.help'))
                ->readonly(),

            NovaBoolField::make(self::translate('migrated.customer.successful'), 'successful'),

            HasMany::make(
                self::translate('nova-resource-labels.customers'),
                'customers',
                NovaCustomerResource::class,
            ),
            HasMany::make(
                self::translate('subscription.plural'),
                'migratedSubscriptions',
                NovaMigratedSubscriptionResource::class,
            ),
        ];
    }

    public static function authorizedToCreate(Request $request): bool
    {
        return false;
    }

    public function authorizedToDelete(Request $request): bool
    {
        return false;
    }

    public function authorizedToForceDelete(Request $request): bool
    {
        return false;
    }

    public function authorizedToUpdate(Request $request): bool
    {
        return $this->canManageMigrations();
    }

    public function authorizedToView(Request $request): bool
    {
        return true;
    }

    /**
     * @return array<int, Action>
     */
    public function actions(NovaRequest $request): array
    {
        return [
            $this->resolveAction(NovaMigratedCustomerEnableInvoicingAction::class)
                ->canSee(fn () => $this->canManageMigrations())
                ->canRun(fn () => $this->resource->exists === false || $this->resource->enable_invoicing === false),
        ];
    }

    private function canManageMigrations(): bool
    {
        /** @var AuthorizationChecker $authorizationChecker */
        $authorizationChecker = App::make(AuthorizationChecker::class);

        return $authorizationChecker->can(Permissions::MANAGE_MIGRATIONS);
    }
}
