<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Acronis\Resources;

use Illuminate\Http\Request;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Hidden;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Ramsey\Uuid\Uuid;
use Waterfront\Apps\Nova\Acronis\Actions\NovaShowAcronisOfferingItemsForTenantAction;
use Waterfront\Apps\Nova\Acronis\Actions\NovaTestAcronisUserSsoAction;
use Waterfront\Apps\Nova\Acronis\Actions\NovaVerifyAcronisProviderAction;
use Waterfront\Apps\Nova\Fields\Credential;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\General\Traits\ResolvesActionsAndFilters;
use Waterfront\Domain\Provision\Backup\Acronis\Models\AcronisProvider;

/** @property AcronisProvider $resource */
class NovaAcronisProviderResource extends Resource
{
    use ResolvesActionsAndFilters;

    public static string $model = AcronisProvider::class;

    public static function getTranslationKey(): string
    {
        return 'acronis-providers';
    }

    public function title(): string
    {
        return $this->resource->name;
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->onlyOnDetail(),

            Hidden::make(self::translate('acronis-providers.attributes.uuid'), 'uuid')
                ->default(fn ($request) => Uuid::uuid4())
                ->fillUsing(function ($request, $model, $attribute, $requestAttribute) {
                    /** @var AcronisProvider $model */
                    $model->uuid = Uuid::uuid4();
                })
                ->showOnCreating()
                ->sortable(),

            Text::make(self::translate('acronis-providers.name'), 'name')->rules('required', 'string'),

            Text::make(self::translate('acronis-providers.endpoint'), 'endpoint')->rules('required', 'string', 'url'),

            Text::make(self::translate('acronis-providers.tenant_uuid'), 'tenant_uuid')->rules('required', 'uuid'),

            Text::make(self::translate('acronis-providers.client_id'), 'client_id')
                ->onlyOnForms()
                ->creationRules('required', 'uuid')
                ->updateRules('nullable', 'uuid'),

            Boolean::make(self::translate('acronis-providers.default'), 'default'),

            Text::make(self::translate('acronis-providers.sso_target_url'), 'sso_target_url')->rules(
                'required',
                'string',
                'url',
            ),

            Credential::make(self::translate('acronis-providers.client_secret'), 'client_secret')
                ->onlyOnForms()
                ->creationRules('required')
                ->updateRules('nullable'),
        ];
    }

    public function authorizedToDelete(Request $request): bool
    {
        return false;
    }

    public function authorizedToForceDelete(Request $request): bool
    {
        return false;
    }

    /**
     * @return array<int, Action>
     */
    public function actions(NovaRequest $request): array
    {
        return [
            $this->resolveAction(NovaVerifyAcronisProviderAction::class),
            $this->resolveAction(NovaShowAcronisOfferingItemsForTenantAction::class),
            $this->resolveAction(NovaTestAcronisUserSsoAction::class),
        ];
    }
}
