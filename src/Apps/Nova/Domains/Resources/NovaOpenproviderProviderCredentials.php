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
use Waterfront\Domain\Domains\Models\OpenproviderProviderCredentials;

/** @property OpenproviderProviderCredentials $resource */
class NovaOpenproviderProviderCredentials extends Resource
{
    public static string $model = OpenproviderProviderCredentials::class;

    public static function getTranslationKey(): string
    {
        return 'op-provider-credentials';
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->onlyOnDetail(),
            Text::make(
                self::translate('op-provider-credentials.api_url'),
                'api_url',
            )->rules('required', 'url'),
            Text::make(
                self::translate('op-provider-credentials.username'),
                'username',
            )->rules('required'),
            Credential::make(self::translate('op-provider-credentials.password'), 'password')
                ->onlyOnForms()
                ->creationRules('required')
                ->updateRules('nullable'),
            BelongsTo::make(
                self::translate('domain-provider-business-unit.plural'),
                'domainProviderBusinessUnit',
                NovaDomainProviderBusinessUnitResource::class,
            )
                ->creationRules('unique:openprovider_provider_credentials,domain_business_unit_id')
                ->updateRules('unique:openprovider_provider_credentials,domain_business_unit_id,{{resourceId}}'),
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
