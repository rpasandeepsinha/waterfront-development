<?php

declare(strict_types=1);

namespace Waterfront\Domain\Transfers\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Transfers\Enums\TransferType;
use Waterfront\Domain\Transfers\Models\Transfer;

class ProductTransferRepository
{
    /**
     * @return LengthAwarePaginator<int,Transfer>
     */
    public function findBySubscriptionIdPaginated(int $subscriptionId, int $pageSize = 15): LengthAwarePaginator
    {
        $query = Transfer::whereHas('subscriptions', function ($q) use ($subscriptionId) {
            $q->where('id', $subscriptionId);
        })
            ->with(['subscriptions.product', 'toCustomer', 'fromCustomer'])
            ->orderByDesc('created_at');

        $paginator = $query->paginate($pageSize);
        $paginator->appends(['pageSize' => (string) $pageSize]);

        return $paginator;
    }

    /**
     * @return LengthAwarePaginator<int, Transfer>
     */
    public function findByTransferTypeAndCustomerPaginated(
        Customer $customer,
        ?TransferType $transferType,
        int $pageSize = 15,
    ): LengthAwarePaginator {
        $query = Transfer::query()->with(['subscriptions.product', 'toCustomer', 'fromCustomer']);

        if ($transferType === null) {
            $query->where(function ($q) use ($customer) {
                $q->where('from_customer_id', $customer->id)->orWhere('to_customer_id', $customer->id);
            });
        } elseif ($transferType === TransferType::INCOMING) {
            $query->where('to_customer_id', $customer->id);
        } elseif ($transferType === TransferType::OUTGOING) {
            $query->where('from_customer_id', $customer->id);
        }

        $query->orderByDesc('created_at');

        $paginator = $query->paginate($pageSize);
        $paginator->appends(['pageSize' => (string) $pageSize]);

        return $paginator;
    }
}
