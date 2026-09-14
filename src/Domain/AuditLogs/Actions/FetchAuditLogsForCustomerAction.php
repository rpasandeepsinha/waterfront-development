<?php

declare(strict_types=1);

namespace Waterfront\Domain\AuditLogs\Actions;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\CustomerAddress;
use Waterfront\Domain\Customers\Models\CustomerContact;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\History\Models\Audit;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;
use Waterfront\Domain\ResellerHosting\Models\ResellerHostingDeployment;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Models\SubscriptionMutation;
use Waterfront\Domain\VPS\Models\ManagerDomainDeployment;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;
use Waterfront\Domain\VPS\Models\VolumeDeployment;

class FetchAuditLogsForCustomerAction
{
    /*
     * We're supporting old namespaces because models have moved, we don't have morphmapping yet.
     * In the audit log refactor we will start to support this so this can be less complex. (Ticket: WATER-4467)
     */
    public const array LOCATION_CUSTOMER = [Customer::class, 'App\Models\Customer', 'Modules\Customer\Models\Customer'];
    public const array LOCATION_CUSTOMER_ADDRESS = [
        CustomerAddress::class,
        'App\Models\CustomerAddress',
        'Modules\Customer\Models\CustomerAddress',
    ];
    public const array LOCATION_CUSTOMER_CONTACT = [
        CustomerContact::class,
        'App\Models\CustomerContact',
        'Modules\Customer\Models\CustomerContact',
    ];
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
    public const array LOCATION_DEPLOYMENT_MANAGERDOMAIN = [
        ManagerDomainDeployment::class,
        'Waterfront\Domain\VPS\Models\ManagerDomainSubscription',
    ];
    public const array LOCATION_DEPLOYMENT_VIRTUALMACHINE = [
        VirtualMachineDeployment::class,
        'Waterfront\Domain\VPS\Models\ManagerMachineSubscription',
    ];

    /**
     * @return LengthAwarePaginator<int, Audit>
     */
    public function execute(Customer $customer, int $pageSize): LengthAwarePaginator
    {
        $customer = $customer->load('customerContacts', 'address');

        $customerContactIds = $customer->customerContacts->pluck('id')->toArray();

        $subscriptionCollection = $customer->subscriptions()->get();
        $invoiceCollectionIds = $customer->invoices()->pluck('id')->toArray();

        $subscriptCollectionByKeyId = $subscriptionCollection->keyBy('id');
        $subscriptCollectionByKeyUuid = $subscriptionCollection->keyBy('uuid');

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

        $mutations = SubscriptionMutation::query()
            ->select('id')
            ->whereIn('subscription_id', $subscriptCollectionByKeyId->keys())
            ->get();

        // Audit log query for Customer,Subscriptions & Users
        return Audit::query()
            /**
             * The auditable relation is a morph relation, that means a certain string will be morphed into a model
             * e.g. 'SubscriptionMorph' can be morped into the Subscription Class. In this case we're also using it to
             * eager load certain relations.
             */
            ->with(['auditable' => function (MorphTo $morphTo) {
                $morphTo->morphWith(
                    [
                        'App\Models\User' => 'customer',
                        ...HostingDeployment::namespacesWith(),
                        ...CustomerAddress::namespaceWith(),
                        ...Subscription::namespaceWith(),
                        ...Invoice::namespaceWith(),
                        ...DomainDeployment::namespaceWith(),
                        ...SslDeployment::namespaceWith(),
                        ...Microsoft365Deployment::namespaceWith(),
                        ...VirtualMachineDeployment::namespaceWith(),
                        ...VolumeDeployment::namespaceWith(),
                        ...ResellerHostingDeployment::namespacesWith(),
                        ...SubscriptionMutation::namespaceWith(),
                    ],
                );
            }])
            ->where(fn (Builder $builder) => $builder->whereIn('auditable_type', self::LOCATION_CUSTOMER)->where(
                'auditable_id',
                $customer->id,
            ))
            ->orWhere(fn (Builder $builder) => $builder->whereIn(
                'auditable_type',
                self::LOCATION_CUSTOMER_ADDRESS,
            )->where('auditable_id', $customer->address?->id))
            ->orWhere(
                fn (Builder $builder) => $builder->whereIn('auditable_type', self::LOCATION_CUSTOMER_CONTACT)->whereIn(
                    'auditable_id',
                    $customerContactIds,
                ),
            )
            ->orWhere(fn (Builder $builder) => $builder->where('auditable_type', Subscription::class)->whereIn(
                'auditable_id',
                $subscriptCollectionByKeyId->keys(),
            ))
            ->orWhere(
                fn (Builder $builder) => $builder->where('auditable_type', Invoice::class)->whereIn(
                    'auditable_id',
                    $invoiceCollectionIds,
                ),
            )
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
            ->orWhere(
                fn (Builder $builder) => $builder->where('auditable_type', SubscriptionMutation::class)->whereIn(
                    'auditable_id',
                    $mutations,
                ),
            )
            ->orderBy('id', 'desc')
            ->paginate($pageSize);
    }
}
