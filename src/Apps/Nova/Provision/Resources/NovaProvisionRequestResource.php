<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Provision\Resources;

use Laravel\Nova\Exceptions\HelperNotSupported;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\HasOne;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\General\Traits\ResolvesActionsAndFilters;
use Waterfront\Apps\Nova\General\Traits\ViewOnlyResourceTrait;
use Waterfront\Apps\Nova\Provision\Filters\NovaProvisionRequestNameFilter;
use Waterfront\Apps\Nova\Provision\Filters\NovaProvisionRequestTypeFilter;
use Waterfront\Apps\Nova\Provision\StatusBadgeConverter;
use Waterfront\Apps\Nova\Subscriptions\Resources\NovaSubscriptionResource;
use Waterfront\Domain\Provision\Models\ProvisioningRequest;

/**
 * @property ProvisioningRequest $resource
 */
class NovaProvisionRequestResource extends Resource
{
    use ResolvesActionsAndFilters;
    use ViewOnlyResourceTrait;

    public static string $model = ProvisioningRequest::class;

    public static $globallySearchable = false;

    /** @var array<mixed> */
    public static $with =  ['subscription.product.productGroup', 'result'];

    public static function getTranslationKey(): string
    {
        return 'provisioning-request';
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
            Text::make('UUID', 'uuid')
                ->copyable()
                ->onlyOnDetail()
                ->readonly(),
            Text::make(self::translate('provisioning-request.attributes.name'), 'request_name')
                ->readonly(),
            Text::make(self::translate('provisioning-request.attributes.type'), 'request_type')
                ->readonly(),
            BelongsTo::make(self::translate('subscription.singular'), 'subscription', NovaSubscriptionResource::class)
                ->exceptOnForms()
                ->readonly()
                // @phpstan-ignore-next-line argument.type Comes from Laravel Nova
                ->displayUsing(fn (NovaSubscriptionResource $subscription) => sprintf('%s (%s - %s)', $subscription->domain, $subscription->product->productGroup->name, $subscription->product->name)),

            StatusBadgeConverter::createProvisionStatusBadge(self::translate('provisioning-request.attributes.last-status'))
                ->resolveUsing(fn (mixed $value, mixed $resource, mixed $attribute) => $this->resource->result()->orderBy('created_at', 'desc')->first()?->status->value ?? StatusBadgeConverter::MISSING),

            Code::make(self::translate('provisioning-request.attributes.request_data'), 'request_data')
                ->resolveUsing(fn (mixed $result) => is_string($result) ? json_decode($result) : $result)
                ->json()
                ->readonly(),

            DateTime::make(self::translate('provisioning-request.attributes.created_at'), 'created_at')
                ->onlyOnDetail()
                ->readonly(),
            DateTime::make(self::translate('provisioning-request.attributes.updated_at'), 'updated_at')
                ->readonly(),
            HasOne::make(self::translate('provisioning-result.singular'), 'result', NovaProvisionResultResource::class),
        ];
    }

    /**
     * @return array<int, Filter>
     */
    public function filters(NovaRequest $request): array
    {
        return [
            $this->resolveFilter(NovaProvisionRequestNameFilter::class),
            $this->resolveFilter(NovaProvisionRequestTypeFilter::class),
        ];
    }
}
