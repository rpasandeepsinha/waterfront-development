<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Domains\Resources;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Domain\Domains\Models\DomainProviderBusinessUnit;

/** @property DomainProviderBusinessUnit $resource */
class NovaDomainProviderBusinessUnitResource extends Resource
{
    public static string $model = DomainProviderBusinessUnit::class;

    public static $globallySearchable = false;

    /** @var array<mixed> */
    public static $search = [
        'slug',
        'name',
    ];

    public static function getTranslationKey(): string
    {
        return 'domain-provider-business-unit';
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
            ID::make()->sortable(),
            Text::make(self::translate('domain-provider-business-unit.attributes.name'), 'name')
                ->rules('required')
                ->sortable(),
            Text::make(self::translate('domain-provider-business-unit.attributes.slug'), 'slug')
                ->rules('required', 'alpha_dash')
                ->creationRules('unique:domain_provider_business_unit,slug')
                ->sortable()
                ->hideWhenUpdating(),
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
