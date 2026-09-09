<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365ProcessStatus;
use Waterfront\Domain\Microsoft365\Helpers\Microsoft365Helper;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;

class Microsoft365CustomerInfoRepository
{
    public function findActiveByCustomer(Customer $customer): ?Microsoft365CustomerInfo
    {
        return Microsoft365CustomerInfo::query()
            ->where('customer_id', $customer->id)
            ->where('technical_status', Microsoft365ProcessStatus::ACTIVE)
            ->first();
    }

    /**
     * @return Collection<int, Microsoft365CustomerInfo>
     */
    public function findAllByCustomer(Customer $customer): Collection
    {
        return Microsoft365CustomerInfo::query()
            ->where('customer_id', $customer->id)
            ->with([
                'microsoft365Deployments.subscription.children.product',
                'microsoft365Deployments.subscription.product',
                'microsoft365Deployments.microsoft365CustomerInfo',
            ])
            ->orderBy('id')
            ->get();
    }

    public function findByCustomer(Customer $customer): ?Microsoft365CustomerInfo
    {
        return Microsoft365CustomerInfo::query()
            ->where('customer_id', $customer->id)
            ->first();
    }

    public function findByCustomerAndDomain(Customer $customer, ?string $domain): ?Microsoft365CustomerInfo
    {
        return Microsoft365CustomerInfo::query()
            ->where('customer_id', $customer->id)
            ->where('primary_domain', $domain)
            ->first();
    }

    public function findByKpnCustomerId(int $kpnCustomerId): ?Microsoft365CustomerInfo
    {
        return Microsoft365CustomerInfo::query()
            ->where('kpn_customer_id', $kpnCustomerId)
            ->orWhere('kpn_customer_id', Microsoft365Helper::customerIdToStringWithPrefix($kpnCustomerId))
            ->first();
    }

    public function findByTenantOrderId(int $tenantOrderId): ?Microsoft365CustomerInfo
    {
        return Microsoft365CustomerInfo::query()
            ->where('tenant_order_id', $tenantOrderId)
            ->first();
    }
}
