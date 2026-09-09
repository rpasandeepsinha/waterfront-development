<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Hosting\Resources;

use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\Boolean as NovaBoolField;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\Fields\Credential;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\General\Traits\ResolvesActionsAndFilters;
use Waterfront\Apps\Nova\Hosting\Actions\NovaFetchSsoFromSpamExpertsCluster;
use Waterfront\Domain\Hosting\Models\SpamExpertsCluster;

/** @property SpamExpertsCluster $resource */
class NovaSpamExpertsClusterResource extends Resource
{
    use ResolvesActionsAndFilters;

    public static string $model = SpamExpertsCluster::class;

    /** @var array<mixed> */
    public static $search = [
        'hostname',
    ];

    public static function getTranslationKey(): string
    {
        return 'spamexperts-cluster';
    }

    public function title(): string
    {
        return $this->resource->hostname;
    }

    public static function label(): string
    {
        return self::translate('nova-resource-labels.spamexperts-clusters');
    }

    /** @return array<int, Field> */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make(self::translate('spamexperts-cluster.attributes.hostname'), 'hostname')
                ->rules('required')
                ->sortable(),

            Text::make(self::translate('spamexperts-cluster.attributes.business_unit'), 'business_unit')
                ->rules('required')
                ->sortable(),

            Text::make(self::translate('spamexperts-cluster.attributes.username'), 'username')
                ->rules('required')
                ->sortable(),

            Credential::make(self::translate('spamexperts-cluster.attributes.password'), 'password')
                ->onlyOnForms()
                ->creationRules('required')
                ->updateRules('nullable'),

            NovaBoolField::make(self::translate('spamexperts-cluster.attributes.ssl'), 'ssl')
                ->default(true),
        ];
    }

    /** @return array<int, Action> */
    public function actions(NovaRequest $request): array
    {
        return [
            $this->resolveAction(NovaFetchSsoFromSpamExpertsCluster::class),
        ];
    }
}
