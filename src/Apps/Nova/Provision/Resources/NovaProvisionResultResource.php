<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Provision\Resources;

use Laravel\Nova\Exceptions\HelperNotSupported;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\General\Traits\ResolvesActionsAndFilters;
use Waterfront\Apps\Nova\General\Traits\ViewOnlyResourceTrait;
use Waterfront\Apps\Nova\Provision\StatusBadgeConverter;
use Waterfront\Domain\Provision\Models\ProvisioningResult;

/** @property ProvisioningResult $resource */
class NovaProvisionResultResource extends Resource
{
    use ResolvesActionsAndFilters;
    use ViewOnlyResourceTrait;

    public static string $model = ProvisioningResult::class;

    public static $globallySearchable = false;

    /** @var array<mixed> */
    public static $with = ['provisioningRequest'];

    public static function getTranslationKey(): string
    {
        return 'provisioning-result';
    }

    /**
     * @throws HelperNotSupported
     *
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make(),
            Text::make('UUID', 'uuid')->copyable()->readonly(),
            BelongsTo::make(
                self::translate('provisioning-request.singular'),
                'provisioningRequest',
                NovaProvisionRequestResource::class,
            )
                ->displayUsing(fn (mixed $resource) => $resource instanceof NovaProvisionRequestResource
                    ? sprintf('%s (%d)', $resource->resource->request_name->value, $resource->resource->id)
                    : $resource)
                ->readonly(),

            StatusBadgeConverter::createProvisionStatusBadge(self::translate('provisioning-result.attributes.status')),

            Code::make(self::translate('provisioning-result.attributes.response'), 'response')
                ->resolveUsing(fn (mixed $result) => is_string($result) ? json_decode($result) : $result)
                ->json()
                ->readonly(),
            DateTime::make(self::translate('provisioning-request.attributes.created_at'), 'created_at')->readonly(),
            DateTime::make(self::translate('provisioning-request.attributes.updated_at'), 'updated_at')
                ->onlyOnDetail()
                ->readonly(),
        ];
    }

    /**
     * @return array<int, Filter>
     */
    public function filters(NovaRequest $request): array
    {
        return [];
    }
}
