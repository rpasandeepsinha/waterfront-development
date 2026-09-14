<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class HostingDeploymentRepository
{
    /**
     * Create a new hosting deployment or find the existing one in the database.
     */
    public function create(array $data, string $subscriptionUuid, Server $server): HostingDeployment
    {
        $hostingDeployment = HostingDeployment::where('subscription_uuid', $subscriptionUuid)->first();

        if ($hostingDeployment !== null) {
            return $hostingDeployment;
        }

        /** @var HostingDeployment $deployment */
        $deployment = $server->hostingDeployments()->make($data);
        $deployment->subscription_uuid = $subscriptionUuid;
        $deployment->save();

        return $deployment;
    }

    /**
     * @throws ModelNotFoundException
     */
    public function getByActiveDomain(string $domain): Subscription
    {
        return Subscription::where('domain', $domain)
            ->whereIn('administrative_status', [
                AdministrativeStatus::ACTIVE->value,
                AdministrativeStatus::CANCELED->value,
            ])
            ->whereHas('product.productGroup', function (Builder $productGroup): void {
                $productGroup->where('slug', ProductGroupType::HOSTING);
            })
            ->firstOrFail();
    }

    /**
     * @throws ModelNotFoundException
     */
    public function getByDomain(string $domain): Subscription
    {
        return Subscription::where('domain', $domain)
            ->whereHas('product.productGroup', function (Builder $productGroup): void {
                $productGroup->where('slug', ProductGroupType::HOSTING);
            })
            ->firstOrFail();
    }

    public function findByUuid(string $subscriptionUuid): ?HostingDeployment
    {
        return HostingDeployment::where('subscription_uuid', $subscriptionUuid)->first();
    }

    public function hasHostingDeploymentForDomain(string $domain): bool
    {
        return Subscription::query()
            ->where('domain', $domain)
            ->whereProductGroupType(ProductGroupType::HOSTING)
            ->whereNotIn('administrative_status', AdministrativeStatus::administrativelyEnded())
            ->whereHas('hostingDeployment')
            ->exists();
    }

    public function storeLastResult(string $subscriptionUuid, string $lastResult): void
    {
        $hostingDeployment = HostingDeployment::where('subscription_uuid', $subscriptionUuid)->first();

        if ($hostingDeployment instanceof HostingDeployment) {
            $hostingDeployment->last_created_result = $lastResult;
            $hostingDeployment->last_created_result_received = CarbonImmutable::now();
            $hostingDeployment->save();
        }
    }

    /**
     * @return Collection <int, HostingDeployment>
     */
    public function getActiveSharedByCustomer(Customer $customer): Collection
    {
        return HostingDeployment::query()
            ->whereHas('subscription', function (Builder $query) use ($customer): void {
                $query
                    ->where('customer_id', $customer->id)
                    ->where('administrative_status', AdministrativeStatus::ACTIVE->value)
                    ->whereHas('product.productGroup', function (Builder $query): void {
                        $query->where('slug', ProductGroupType::HOSTING);
                    });
            })
            ->whereHas('server')
            ->where(function (Builder $query) {
                $query->whereNotNull('directadmin_customer_username')->orWhereNotNull('plesk_customer_username');
            })
            ->with('subscription')
            ->get();
    }

    public function storeWpToolkitInstallationId(HostingDeployment $deployment, int $wpToolkitInstallationId): void
    {
        $deployment->wp_installation_id = $wpToolkitInstallationId;
        $deployment->save();
    }

    public function getServer(HostingDeployment $deployment): ?Server
    {
        return $deployment->subscription->product->isMailOnlyServer()
            ? $deployment->mailOnlyServer ?? $deployment->server
            : $deployment->server;
    }
}
