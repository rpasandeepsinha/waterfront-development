<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\General\Resources;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Markdown;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource as LaravelResource;
use Laravel\Nova\URL;
use Waterfront\Apps\Nova\Customers\Resources\NovaCustomerResource;
use Waterfront\Apps\Nova\Subscriptions\Resources\NovaSubscriptionResource;
use Waterfront\Domain\Notes\Models\Notes;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Common\DateTimeFormat;

/** @property Notes $resource */
class NovaNotesResource extends Resource
{
    public static string $model = Notes::class;

    public static $displayInNavigation = false;

    public static $globallySearchable = false;

    /**
     * @var array<mixed>
     */
    public static $with = ['customer', 'subscription'];

    public static function getTranslationKey(): string
    {
        return 'notes';
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        $viaResource = $request->input('viaResource');
        $viaResourceId = $request->input('viaResourceId');

        $customerSubscriptions = new Collection();
        if ($viaResource === 'nova-customer-resources' && $viaResourceId !== null) {
            $customerSubscriptions = Subscription::query()->where('customer_id', $viaResourceId)->with('product')->get();
        }

        return [
            BelongsTo::make(self::translate('nova-resource-labels.note.relation.customer'), 'customer', NovaCustomerResource::class)->onlyOnDetail()->showOnPreview(),
            BelongsTo::make(self::translate('nova-resource-labels.subscription'), 'subscription', NovaSubscriptionResource::class)->hideWhenCreating()->showOnPreview(),
            Date::make(self::translate('nova-resource-labels.created_at'), 'created_at')
                ->displayUsing(fn () => $this->resource->created_at?->format(DateTimeFormat::DUTCHNOTIME))
                ->sortable()
                ->hideWhenCreating()
                ->showOnPreview(),
            Text::make(self::translate('nova-resource-labels.noted-by'), 'noted_by_metadata')
                ->displayUsing(function ($data) {
                    if (! is_string($data)) {
                        return 'System';
                    }
                    $decoded = json_decode($data, true, flags: JSON_THROW_ON_ERROR);
                    return is_array($decoded) ? $decoded['email'] : '';
                })
                ->hideWhenCreating()
                ->showOnPreview(),
            Select::make(self::translate('nova-resource-labels.subscription'), 'subscription_id')
                ->canSee(
                    fn ($request): bool => $customerSubscriptions->count() !== 0 && $request->input('viaResource') !== 'nova-subscription-resources'
                )
                ->options(function () use ($request, $customerSubscriptions): array {
                    if ($request->input('viaResource') !== 'nova-customer-resources') {
                        return [];
                    }

                    $options = [];

                    /** @var Subscription $customerSubscription */
                    foreach ($customerSubscriptions as $customerSubscription) {
                        $options[$customerSubscription->id] = $customerSubscription->product->name . ' - ' . $customerSubscription->domain;
                    }

                    return $options;
                })
                ->onlyOnForms()
                ->nullable(),
            Text::make(self::translate('nova-resource-labels.note'), 'note')
                ->displayUsing(function ($value) {
                    assert(is_string($value));
                    return Str::limit($value, 50);
                })
                ->onlyOnIndex()
                ->hideFromDetail()
                ->showOnPreview(false),
            Markdown::make(self::translate('nova-resource-labels.note'), 'note')->alwaysShow()->hideFromIndex()->showOnPreview(),
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

    public function authorizedToUpdate(Request $request): bool
    {
        return false;
    }

    public static function beforeCreate(NovaRequest $request, Model $model): void
    {
        if ($request->input('viaResource') === 'nova-subscription-resources') {
            $subscription = Subscription::findOrFail($request->input('viaResourceId'));
            assert($model instanceof Notes);
            if ($subscription instanceof Subscription) {
                $model->customer_id = $subscription->customer_id;
            }
        }
    }

    public static function redirectAfterCreate(NovaRequest $request, LaravelResource $resource): URL|string
    {
        if ($request->has(['viaResource', 'viaResourceId'])) {
            return "/resources/{$request->string('viaResource')}/{$request->string('viaResourceId')}";
        }

        return parent::redirectAfterCreate($request, $resource);
    }

    public static function redirectAfterUpdate(NovaRequest $request, $resource): URL|string
    {
        if ($request->has(['viaResource', 'viaResourceId'])) {
            return "/resources/{$request->string('viaResource')}/{$request->string('viaResourceId')}";
        }

        return parent::redirectAfterUpdate($request, $resource);
    }
}
