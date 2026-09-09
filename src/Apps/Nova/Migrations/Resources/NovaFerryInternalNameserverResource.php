<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Migrations\Resources;

use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\General\Traits\ResolvesActionsAndFilters;
use Waterfront\Domain\Ferry\Models\FerryInternalNameserver;

/** @property FerryInternalNameserver $resource */
class NovaFerryInternalNameserverResource extends Resource
{
    use ResolvesActionsAndFilters;

    public static string $model = FerryInternalNameserver::class;

    public static $globallySearchable = false;

    public static function getTranslationKey(): string
    {
        return 'migrated.internal_nameserver';
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make(self::translate('dns-nameserver.singular'), 'nameserver_hostname')
                ->required()
                ->creationRules('required', 'unique:migrated_dns_internal_nameservers,nameserver_hostname')
                ->updateRules('required', 'unique:migrated_dns_internal_nameservers,nameserver_hostname,{{resourceId}}'),
            DateTime::make(self::translate('nova-resource-labels.created_at'), 'created_at')
                ->readonly()
                ->hideWhenCreating()
                ->hideWhenUpdating(),
            DateTime::make(self::translate('nova-resource-labels.updated_at'), 'updated_at')
                ->readonly()
                ->hideWhenCreating()
                ->hideWhenUpdating(),
        ];
    }
}
