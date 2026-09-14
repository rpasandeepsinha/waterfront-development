<?php

declare(strict_types=1);

namespace Waterfront\Domain\AuditLogs\Actions;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\History\Models\Audit;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;
use Waterfront\Domain\ResellerHosting\Models\ResellerHostingDeployment;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;
use Waterfront\Domain\VPS\Models\VolumeDeployment;

class FetchAuditLogsForDomainAction
{
    public const array LOCATION_DEPLOYMENT_DOMAIN = [
        DomainDeployment::class,
        'Modules\DomainService\Models\Subscription',
        'Waterfront\Domain\Domains\Models\DomainSubscription',
    ];
    public const array LOCATION_DEPLOYMENT_HOSTING = [
        HostingDeployment::class,
        'Modules\HostingService\Models\Subscription',
        'Waterfront\Domain\Hosting\Models\HostingSubscription',
    ];
    public const array LOCATION_DEPLOYMENT_SSL = [
        SslDeployment::class,
        'Modules\SslService\Models\Subscription',
        'Waterfront\Domain\Ssl\Models\SslSubscription',
    ];
    public const array LOCATION_DEPLOYMENT_RESELLERHOSTING = [
        ResellerHostingDeployment::class,
        'App\Models\ResellerHostingSubscription',
        'Waterfront\Domain\ResellerHosting\Models\ResellerHostingSubscription',
    ];
    public const array LOCATION_DEPLOYMENT_M365 = [
        Microsoft365Deployment::class,
        'Waterfront\Domain\Microsoft365\Models\Microsoft365Subscription',
    ];
    public const array LOCATION_DEPLOYMENT_VOLUME = [
        VolumeDeployment::class,
        'Waterfront\Domain\VPS\Models\VolumeSubscription',
    ];
    public const array LOCATION_DEPLOYMENT_VIRTUALMACHINE = [
        VirtualMachineDeployment::class,
        'Waterfront\Domain\VPS\Models\ManagerMachineSubscription',
    ];

    /**
     * @return LengthAwarePaginator<int, Audit>
     */
    public function execute(string $domain, int $pageSize): LengthAwarePaginator
    {
        $subscriptionUuidCollection = Subscription::query()->select(['id', 'uuid'])->where('domain', $domain)->get();

        $subscriptCollectionByKeyId = $subscriptionUuidCollection->keyBy('id');
        $subscriptCollectionByKeyUuid = $subscriptionUuidCollection->keyBy('uuid');

        $domainSubscriptionIds = DomainDeployment::query()
            ->select('id')
            ->whereIn('subscription_uuid', $subscriptCollectionByKeyUuid->keys())
            ->get();
        $hostingSubscriptionIds = HostingDeployment::query()
            ->select('id')
            ->whereIn('subscription_uuid', $subscriptCollectionByKeyUuid->keys())
            ->get();
        $resellerHostingDeploymentIds = ResellerHostingDeployment::query()
            ->select('id')
            ->whereIn('subscription_uuid', $subscriptCollectionByKeyUuid->keys())
            ->get();
        $sslDeploymentIds = SslDeployment::query()
            ->select('id')
            ->whereIn('subscription_uuid', $subscriptCollectionByKeyUuid->keys())
            ->get();
        $microsoft365SubscriptionIds = Microsoft365Deployment::query()
            ->select('id')
            ->whereIn('subscription_id', $subscriptCollectionByKeyId->keys())
            ->get();
        $csVirtualMachineSubscriptionIds = VirtualMachineDeployment::query()
            ->select('id')
            ->whereIn('subscription_uuid', $subscriptCollectionByKeyUuid->keys())
            ->get();
        $csVolumeSubscriptionIds = VolumeDeployment::query()
            ->select('id')
            ->whereIn('subscription_uuid', $subscriptCollectionByKeyUuid->keys())
            ->get();

        return Audit::query()
            /**
             * The auditable relation is a morph relation, that means a certain string will be morphed into a model
             * e.g. 'SubscriptionMorph' can be morphed into the Subscription Class. In this case we're also using it to
             * eager load certain relations.
             */
            ->with(['auditable' => function (MorphTo $morphTo) {
                $morphTo->morphWith(
                    [
                        ...Subscription::namespaceWith(),
                        ...DomainDeployment::namespaceWith(),
                        ...HostingDeployment::namespacesWith(),
                        ...SslDeployment::namespaceWith(),
                        ...Microsoft365Deployment::namespaceWith(),
                        ...VirtualMachineDeployment::namespaceWith(),
                        ...VolumeDeployment::namespaceWith(),
                        ...ResellerHostingDeployment::namespacesWith(),
                    ],
                );
            }])
            ->orWhere(fn (Builder $builder) => $builder->where('auditable_type', Subscription::class)->whereIn(
                'auditable_id',
                $subscriptCollectionByKeyId->keys(),
            ))
            ->orWhere(
                fn (Builder $builder) => $builder->whereIn('auditable_type', self::LOCATION_DEPLOYMENT_DOMAIN)->whereIn(
                    'auditable_id',
                    $domainSubscriptionIds,
                ),
            )
            ->orWhere(
                fn (Builder $builder) => $builder->whereIn(
                    'auditable_type',
                    self::LOCATION_DEPLOYMENT_HOSTING,
                )->whereIn('auditable_id', $hostingSubscriptionIds),
            )
            ->orWhere(
                fn (Builder $builder) => $builder->where(
                    'auditable_type',
                    self::LOCATION_DEPLOYMENT_RESELLERHOSTING,
                )->whereIn('auditable_id', $resellerHostingDeploymentIds),
            )
            ->orWhere(
                fn (Builder $builder) => $builder->whereIn('auditable_type', self::LOCATION_DEPLOYMENT_SSL)->whereIn(
                    'auditable_id',
                    $sslDeploymentIds,
                ),
            )
            ->orWhere(
                fn (Builder $builder) => $builder->whereIn('auditable_type', self::LOCATION_DEPLOYMENT_M365)->whereIn(
                    'auditable_id',
                    $microsoft365SubscriptionIds,
                ),
            )
            ->orWhere(
                fn (Builder $builder) => $builder->whereIn(
                    'auditable_type',
                    self::LOCATION_DEPLOYMENT_VIRTUALMACHINE,
                )->whereIn('auditable_id', $csVirtualMachineSubscriptionIds),
            )
            ->orWhere(
                fn (Builder $builder) => $builder->whereIn('auditable_type', self::LOCATION_DEPLOYMENT_VOLUME)->whereIn(
                    'auditable_id',
                    $csVolumeSubscriptionIds,
                ),
            )
            ->orderBy('id', 'desc')
            ->paginate($pageSize);
    }
}
