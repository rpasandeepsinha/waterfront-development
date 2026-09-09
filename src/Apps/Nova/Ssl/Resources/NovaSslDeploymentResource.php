<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Ssl\Resources;

use Illuminate\Http\Request;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean as NovaBoolField;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\Ssl\Actions\NovaSslRenewalHealthCheckAction;
use Waterfront\Apps\Nova\Subscriptions\Resources\NovaSubscriptionResource;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Infra\Common\DateTimeFormat;

/** @property SslDeployment $resource */
class NovaSslDeploymentResource extends Resource
{
    public static string $model = SslDeployment::class;

    public static $displayInNavigation = false;

    public static $globallySearchable = false;

    /** @var array<mixed> */
    public static $search = [
        'id',
    ];

    public static function getTranslationKey(): string
    {
        return 'ssl-subscription';
    }

    public function title(): string
    {
        return (string) $this->resource->subscription->id;
    }

    /** @return array<int, Field> */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make(
                self::translate('subscription.ssl-subscription.internal_subscription'),
                'subscription',
                NovaSubscriptionResource::class
            )->sortable()
                ->exceptOnForms(),
            Text::make(self::translate('subscription.ssl-subscription.certificate_id'), 'certificate_id')
                ->resolveUsing(fn (): string => $this->resolveProviderIdentifier($this->resource->provider) ?? '---')
                ->sortable(),
            Text::make(self::translate('subscription.ssl-subscription.status'), 'status')
                ->sortable(),
            NovaBoolField::make(self::translate('subscription.ssl-subscription.dns_record'), 'dns_record')
                ->sortable(),
            NovaBoolField::make(self::translate('subscription.ssl-subscription.has_certificates'), 'has_certificates')
                ->sortable(),
            NovaBoolField::make(self::translate('subscription.ssl-subscription.has_private'), 'has_private')
                ->sortable(),
            DateTime::make(self::translate('subscription.ssl-subscription.last_result_received'), 'last_result_received')
                ->displayUsing(fn () => $this->resource->last_result_received?->format(DateTimeFormat::DUTCH))
                ->exceptOnForms()
                ->onlyOnDetail()
                ->sortable(),
            DateTime::make(self::translate('subscription.ssl-subscription.webhook_request_received'), 'webhook_request_received')
                ->displayUsing(fn () => $this->resource->webhook_request_received?->format(DateTimeFormat::DUTCH))
                ->exceptOnForms()
                ->onlyOnDetail()
                ->sortable(),
            Date::make(self::translate('subscription.ssl-subscription.expire_date'), 'expire_date')
                ->displayUsing(fn () => $this->resource->expire_date?->format(DateTimeFormat::DUTCHNOTIME))
                ->exceptOnForms()
                ->onlyOnDetail()
                ->sortable(),
            Code::make(self::translate('subscription.ssl-subscription.last_result'), 'last_result')
                ->exceptOnForms()
                ->onlyOnDetail()
                ->json(),
            Code::make(self::translate('subscription.ssl-subscription.webhook_request'), 'webhook_request')
                ->exceptOnForms()
                ->onlyOnDetail()
                ->json(),
        ];
    }

    /**
     * @return array<int, Action>
     */
    public function actions(NovaRequest $request): array
    {
        return [
            resolve(NovaSslRenewalHealthCheckAction::class),
        ];
    }

    public static function authorizedToCreate(Request $request): bool
    {
        return false;
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

    private function resolveProviderIdentifier(?Provider $provider): ?string
    {
        if (! $provider instanceof Provider) {
            return null;
        }

        switch ($provider->slug) {
            case ProviderSlug::OPEN_PROVIDER:
            case ProviderSlug::REALTIME_REGISTER:
                if ($this->resource->certificate_id !== null) {
                    return (string) $this->resource->certificate_id;
                }
                return $this->resource->request_id !== null ? 'pid: ' . $this->resource->request_id : '-';
        }

        return null;
    }
}
