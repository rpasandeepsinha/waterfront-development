<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Hosting\Resources;

use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\General\Traits\ResolvesActionsAndFilters;
use Waterfront\Apps\Nova\Hosting\Filters\NovaOriginalBusinessUnitFilter;
use Waterfront\Domain\Servers\Models\LegacyRedirectingServer;

/** @property LegacyRedirectingServer|null $resource */
class NovaRedirectLegacyServerResource extends Resource
{
    use ResolvesActionsAndFilters;

    public static string $model = LegacyRedirectingServer::class;

    public static $globallySearchable = false;

    /** @var array<mixed> */
    public static $search = [
        'hostname',
        'ipv4',
        'ipv6',
        'original_business_unit',
    ];

    public static function getTranslationKey(): string
    {
        return 'legacy-redirecting-servers';
    }

    /** @return array<int, Field> */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make('hostname')
                ->required()
                ->creationRules('required', 'unique:hosting_redirecting_legacy_servers,hostname')
                ->updateRules('required', 'unique:hosting_redirecting_legacy_servers,hostname,{{resourceId}}')
                ->sortable(),
            Text::make('ipv4')
                ->required()
                ->creationRules('required', 'ipv4', 'unique:hosting_redirecting_legacy_servers,ipv4')
                ->updateRules('required', 'ipv4', 'unique:hosting_redirecting_legacy_servers,ipv4,{{resourceId}}')
                ->sortable(),
            Text::make('ipv6')
                ->creationRules('ipv6', 'unique:hosting_redirecting_legacy_servers,ipv6', 'nullable')
                ->updateRules('ipv6', 'unique:hosting_redirecting_legacy_servers,ipv6,{{resourceId}}', 'nullable')
                ->sortable(),
            Text::make('original_business_unit')->required()->rules('required')->sortable(),
        ];
    }

    /**
     * @return array<int, Filter>
     */
    public function filters(NovaRequest $request): array
    {
        return [
            $this->resolveFilter(NovaOriginalBusinessUnitFilter::class),
        ];
    }
}
