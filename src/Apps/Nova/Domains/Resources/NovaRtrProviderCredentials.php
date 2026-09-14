<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Domains\Resources;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\Fields\Credential;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Domain\Domains\Models\RtrProviderCredentials;

/** @property RtrProviderCredentials $resource */
class NovaRtrProviderCredentials extends Resource
{
    public static string $model = RtrProviderCredentials::class;

    public static function getTranslationKey(): string
    {
        return 'rtr-provider-credentials';
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->onlyOnDetail(),
            Text::make(
                self::translate('rtr-provider-credentials.api_url'),
                'api_url',
            )->rules('required', 'url'),
            Credential::make(self::translate('rtr-provider-credentials.api_key'), 'api_key')
                ->onlyOnForms()
                ->creationRules('required')
                ->updateRules('nullable'),
            Text::make(
                self::translate('rtr-provider-credentials.handle'),
                'handle',
            )->rules('required'),
            BelongsTo::make(
                self::translate('domain-provider-business-unit.plural'),
                'domainProviderBusinessUnit',
                NovaDomainProviderBusinessUnitResource::class,
            )
                ->creationRules('unique:rtr_provider_credentials,domain_business_unit_id')
                ->updateRules('unique:rtr_provider_credentials,domain_business_unit_id,{{resourceId}}'),
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
}
