<?php

declare(strict_types=1);

namespace Waterfront\Domain\Orders\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Orders\DTO\OrderCountDTO;
use Waterfront\Domain\Orders\Enums\OrderStatus;
use Waterfront\Domain\Orders\Models\Order;
use Waterfront\Domain\Payments\Enums\PaymentStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class OrderRepository
{
    /** @return Collection<int, OrderCountDTO> */
    public function getCustomerOrderCountsByProductAndContractPeriod(Customer $customer): Collection
    {
        return Order::query()
            ->select('p.id AS productId', 'p.slug', 'contract_period', DB::raw('COUNT(product_uuid) AS count'))
            ->from('orders AS o')
            ->join('order_line_items AS oli', 'o.id', '=', 'oli.order_id')
            ->join('products AS p', 'p.uuid', '=', 'oli.product_uuid')
            ->where('o.customer_id', $customer->id)
            ->groupBy('productId', 'oli.contract_period')
            ->get()
            ->map(function ($resultRow) {
                /** @var array<string, int> $groupedAttributes */
                $groupedAttributes = $resultRow->toArray();

                return new OrderCountDTO(
                    productId: $groupedAttributes['productId'],
                    contractPeriod: $groupedAttributes['contract_period'],
                    count: $groupedAttributes['count'],
                );
            });
    }

    /**
     * @return Collection<int, Subscription>
     */
    public function getSubscriptionsByOrderUuid(UuidInterface $orderUuid): Collection
    {
        $order = Order::query()
            ->with(['lineItems.subscription.product.productGroup', 'lineItems.subscription.domainDeployment'])
            ->where('uuid', $orderUuid)
            ->first();

        if ($order === null) {
            return new Collection();
        }

        /** @var Collection<int, Subscription> $subscriptions */
        $subscriptions = $order->lineItems->pluck('subscription')->filter()->values();

        return $subscriptions;
    }

    /**
     * @param OrderStatus[] $status
     *
     * @return Collection<int, Order>
     */
    public function getOrdersByCustomerAndStatus(Customer $customer, array $status): Collection
    {
        return Order::where(['customer_id' => $customer->id])->whereIn('status', $status)->get();
    }

    public function getOrderByUuid(UuidInterface $orderUuid): ?Order
    {
        return Order::where('uuid', $orderUuid->toString())->first();
    }

    /**
     * @param Builder<Order> $query
     *
     * @return Builder<Order>
     */
    public function whereOrderedByMetadataSchemaId(Builder $query, string $schemaId): Builder
    {
        return $query->whereRaw("ordered_by_metadata->>'schemaId' = ?", [$schemaId]);
    }

    /**
     * @param Builder<Order> $query
     *
     * @return Builder<Order>
     */
    public function whereOrderedByMetadataEmailContains(Builder $query, string $email): Builder
    {
        return $query->whereRaw("lower(ordered_by_metadata->>'email') like ?", ['%' . strtolower($email) . '%']);
    }

    public function hasOrdersThatPreventAnonymization(Customer $customer): bool
    {
        return (
            Order::from('orders AS o')
                ->select('o.id')
                ->leftJoin('payments AS p', 'o.id', '=', 'p.order_id')
                ->join('order_line_items AS oli', 'o.id', '=', 'oli.order_id')
                ->where('o.customer_id', $customer->id)
                ->where(function (Builder $query) {
                    $query->where(function (Builder $subquery) {
                        $subquery->whereNotNull('p.id');
                        $subquery->whereNotIn('p.status', [
                            PaymentStatus::CANCELED,
                            PaymentStatus::EXPIRED,
                            PaymentStatus::FAILED,
                        ]);
                    });
                    $query->orWhereNull('p.id');
                })
                ->whereNull('oli.subscription_uuid')
                ->count() > 0
        );
    }
}
