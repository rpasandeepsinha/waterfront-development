<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\DNS\Resources;

use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\Subscriptions\Resources\NovaSubscriptionResource;
use Waterfront\Domain\DNS\Models\DnsDeployment;
use Waterfront\Domain\DNS\Repository\DnsProductSpecRepository;
use Waterfront\Domain\Provision\DNS\Enums\NameserverType;
use Waterfront\Infra\Common\DateTimeFormat;

/** @property DnsDeployment $resource */
class NovaDnsDeploymentResource extends Resource
{
    public static string $model = DnsDeployment::class;

    public static $displayInNavigation = false;

    public static $globallySearchable = false;

    /** @var array<mixed> */
    public static $search = [
        'id',
    ];

    public static function getTranslationKey(): string
    {
        return 'dns-deployment';
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        $nameservers = match ($this->resource->nameserver_type) {
            NameserverType::EXTERNAL => $this->resource->externalNameservers->pluck('nameserver'),
            NameserverType::INTERNAL => $this->resource->dnsNameservers->pluck('nameserver'),
            NameserverType::VANITY => $this->resource->vanityNameservers->pluck('nameserver'),
        };
        $nameservers = $nameservers->toArray();

        /** @var string[] $nameservers */

        return [
            ID::make()->onlyOnDetail(),
            Text::make('NameserverType', 'nameserver_type')->onlyOnDetail(),
            BelongsTo::make(
                self::translate('subscription.domain-subscription.internal_subscription'),
                'subscription',
                NovaSubscriptionResource::class,
            )->sortable(),
            DateTime::make(self::translate('dns-deployment.last-received-internal-client-time'), 'last_result_received')
                ->displayUsing(fn () => $this->resource->last_result_received?->format(DateTimeFormat::DUTCH))
                ->onlyOnDetail()
                ->sortable(),
            Code::make(self::translate('dns-deployment.last-received-internal-client-response'), 'last_result')
                ->onlyOnDetail()
                ->json(),
            DateTime::make(
                self::translate('dns-deployment.last-received-premium-provider-time'),
                'last_result_premium_provider_received',
            )
                ->displayUsing(
                    fn () => $this->resource->last_result_premium_provider_received?->format(DateTimeFormat::DUTCH),
                )
                ->onlyOnDetail()
                ->sortable()
                ->canSee(fn (): bool => $this->isPremiumDns()),
            Code::make(
                self::translate('dns-deployment.last-received-premium-provider-response'),
                'last_result_premium_provider',
            )
                ->onlyOnDetail()
                ->json()
                ->canSee(fn (): bool => $this->isPremiumDns()),
            Code::make(
                self::translate('dns-deployment.last-received-premium-provider-response'),
                'last_result_premium_provider',
            )
                ->onlyOnDetail()
                ->json(),
            Code::make(self::translate('subscription.domain-subscription.dns-nameservers'))->displayUsing(
                fn () => implode("\n", $nameservers),
            ),
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

    public function authorizedToUpdate(Request $request): bool
    {
        return false;
    }

    public function authorizedToForceDelete(Request $request): bool
    {
        return false;
    }

    /**
     * @throws BindingResolutionException
     */
    private function isPremiumDns(): bool
    {
        $subscription = $this->resource->subscription;
        /** @var DnsProductSpecRepository $dnsProductSpecRepo */
        $dnsProductSpecRepo = Container::getInstance()->make(DnsProductSpecRepository::class);

        return $dnsProductSpecRepo->isPremiumDns($subscription->product);
    }
}
