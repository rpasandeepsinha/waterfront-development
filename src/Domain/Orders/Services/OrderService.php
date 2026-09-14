<?php

declare(strict_types=1);

namespace Waterfront\Domain\Orders\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Waterfront\Domain\Cart\DTO\CartVoucher;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Orders\DTO\CartOrder;
use Waterfront\Domain\Orders\DTO\CartOrderSubscription;
use Waterfront\Domain\Orders\Enums\OrderStatus;
use Waterfront\Domain\Orders\LineItemCreators\LineItemCreator;
use Waterfront\Domain\Orders\Models\Order;
use Waterfront\Domain\Orders\Models\OrderLineItem;
use Waterfront\Domain\Orders\Repositories\OrderRepository;
use Waterfront\Domain\Payments\Enums\PaymentMethod;
use Waterfront\Domain\Pricing\DTO\PriceComponents\CustomIndefinitePriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\PriceComponent;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Pricing\Services\PricePersistService;
use Waterfront\Domain\Products\DTO\ProductWithCalculatedPrice;
use Waterfront\Domain\Products\DTO\TotalCollectionPrice;
use Waterfront\Domain\Products\Enums\CustomPriceReasonType;
use Waterfront\Domain\Subscriptions\Actions\ExtendContractAction;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Jobs\ChangeProvisioningJob;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\ProductAllowedChangeRepository;
use Waterfront\Domain\Subscriptions\Services\SubscriptionChangeService;
use Waterfront\Domain\Voucher\Services\VoucherService;

class OrderService
{
    public function __construct(
        private readonly LineItemCreator $lineItemCreator,
        private readonly OrderRepository $orderRepository,
        private readonly VoucherService $voucherService,
        private readonly SubscriptionChangeService $subscriptionChangeService,
        private readonly ExtendContractAction $extendContractAction,
        private readonly ProductAllowedChangeRepository $productAllowedChangeRepository,
        private readonly PricePersistService $pricePersistService,
        private readonly Dispatcher $jobDispatcher,
    ) {
    }

    public function processMutations(Order $order): void
    {
        $order->loadMissing([
            'lineItems.subscription.product',
            'lineItems.subscription.product.productGroup',
            'lineItems.subscription.product.productSpecs',
            'lineItems.product',
            'lineItems.product.productGroup',
            'lineItems.product.productSpecs',
        ]);

        $mutationItems = $order->lineItems->filter(function (OrderLineItem $item) {
            if (! $item->subscription instanceof Subscription) {
                return false;
            }

            if ($item->processed_at !== null) {
                return false;
            }

            return true;
        });

        foreach ($mutationItems as $item) {
            assert($item->subscription instanceof Subscription);
            assert($item->billing_period > 0 && $item->contract_period > 0);

            if (is_null($item->product)) {
                continue;
            }

            if ($item->subscription->product->uuid !== $item->product_uuid) {
                $isUpgradeAllowed = $this->productAllowedChangeRepository->isProductChangeAllowed(
                    ProductChangeType::UPGRADE,
                    $item->subscription->product,
                    $item->product,
                );

                if ($isUpgradeAllowed) {
                    $customIndefinitePrice = $this->findCustomIndefinitePrice($item);

                    $mutation = $this->subscriptionChangeService->createUpgradeMutation(
                        $item->subscription,
                        $item->product,
                        $customIndefinitePrice,
                    );
                    $changeObject = $this->subscriptionChangeService->change(
                        changeType: ProductChangeType::UPGRADE,
                        subscription: $item->subscription,
                        newProduct: $item->product,
                        invoiceTheChange: $customIndefinitePrice === null,
                    );

                    // For subscriptions already created the persistCustomPrice will never happen in the processOrderJob
                    // Given the fact that this is an upgrade a subscription already created, but we found an orderline
                    // where a indefinite price was set/given, so we have set persist this custom price on the subscription
                    // somewhere.
                    if ($customIndefinitePrice !== null) {
                        $this->pricePersistService->persistCustomPrice(
                            $item->subscription,
                            $customIndefinitePrice->newPrice,
                            false,
                            CustomPriceReasonType::ORDER_LINE_CUSTOM_PRICE,
                        );
                    }

                    $this->jobDispatcher->dispatch(new ChangeProvisioningJob($changeObject));

                    $item->subscription_mutation_id = $mutation->id;
                    $item->processed_at = CarbonImmutable::now();
                    $item->save();
                    continue;
                }
            }

            $product = $item->subscription->product->uuid === $item->product_uuid ? null : $item->product;
            $mutation = $this->extendContractAction->execute(
                $item->subscription,
                $item->billing_period,
                $item->contract_period,
                null,
                $product,
            );

            $item->subscription_mutation_id = $mutation->id;
            $item->processed_at = CarbonImmutable::now();
            $item->save();
        }
    }

    /**
     * @throws ExceptionInterface
     */
    public function processCartToOrder(
        CartOrder $cartOrder,
        TotalCollectionPrice $totalPrices,
        int $administrationFees,
        Customer $customer,
        PaymentMethod $paymentMethod = PaymentMethod::MOLLIE,
    ): Order {
        $order = $this->createOrder($paymentMethod, $customer, $totalPrices->totalExclVatPrice, $administrationFees);

        $this->createOrderLineItems($cartOrder->subscriptions, $order, $totalPrices);

        Log::info(sprintf(
            'Order %d created for customer %d',
            $order->id,
            $customer->id,
        ));

        return $order->refresh();
    }

    public function markNonProcessedAsAbuseForCustomer(Customer $customer): void
    {
        $orders = $this->orderRepository->getOrdersByCustomerAndStatus($customer, [
            OrderStatus::IN_PROGRESS,
            OrderStatus::ON_HOLD,
        ]);
        foreach ($orders as $order) {
            $order->status = OrderStatus::ABUSE;
            $order->save();
        }

        $customer->refresh();
    }

    public function orderContainsOnlyUpgradesOrAddonsOrMutations(Order $order): bool
    {
        $order->loadMissing('lineItems.parent.subscription');

        return $order->lineItems->every(
            fn (OrderLineItem $item) => (
                $item->parent_subscription_uuid !== null
                || $item->subscription_uuid !== null ||
                // Upgrade with addon
                $item->parent?->subscription instanceof Subscription
            ),
        );
    }

    /**
     * A custom indefinite component on an order line means the line was priced deliberately rather than
     * by the product's price list, and that price does not expire.
     *
     * The stored OrderLinePriceComponent is an Eloquent row, not the DTO the pricing code passes around,
     * so it is rebuilt into one here.
     */
    private function findCustomIndefinitePrice(OrderLineItem $orderLineItem): ?PriceComponent
    {
        $orderLineItem->loadMissing('prices.components');

        foreach ($orderLineItem->prices as $orderLinePrice) {
            foreach ($orderLinePrice->components as $component) {
                if ($component->type === PriceComponentType::CUSTOM_INDEFINITE) {
                    return new CustomIndefinitePriceComponent($component->new_price);
                }
            }
        }

        return null;
    }

    /**
     * @throws ExceptionInterface
     */
    private function createOrderLineItems(
        CartOrderSubscription $cartOrders,
        Order $order,
        TotalCollectionPrice $totalCollectionPrice,
        ?OrderLineItem $parentOrderLineItem = null,
    ): void {
        foreach ($cartOrders as $cartLineItem) {
            $calculatedPrice = $this->getActualNetPriceIfVoucherIsApplied($totalCollectionPrice, $cartLineItem->uuid);
            $orderLineItem = $this->lineItemCreator->create($cartLineItem, $order, $calculatedPrice->price);

            foreach ($totalCollectionPrice->items as $productWithCalculatedPrice) {
                if (
                    $productWithCalculatedPrice->uuid->toString() === $cartLineItem->uuid->toString()
                    && $productWithCalculatedPrice->appliedPrice->voucher instanceof CartVoucher
                ) {
                    $voucher = $productWithCalculatedPrice->appliedPrice->voucher;
                    $this->voucherService->claimVoucher($voucher->id, $orderLineItem->id, $voucher->appliedAmount);
                }
            }

            if ($cartLineItem->children !== null) {
                $this->createOrderLineItems($cartLineItem->children, $order, $totalCollectionPrice, $orderLineItem);
            }

            if ($cartLineItem->oneTimeServices !== null) {
                foreach ($cartLineItem->oneTimeServices as $oneTimeService) {
                    $calculatedPrice = $this->getActualNetPriceIfVoucherIsApplied(
                        $totalCollectionPrice,
                        $oneTimeService->uuid,
                    );

                    $oneTimeServiceLineItem = $this->lineItemCreator->create(
                        $oneTimeService,
                        $order,
                        $calculatedPrice->price,
                    );
                    $oneTimeServiceLineItem->parent()->associate($orderLineItem);
                    $oneTimeServiceLineItem->save();
                }
            }

            if ($parentOrderLineItem !== null) {
                $orderLineItem->parent()->associate($parentOrderLineItem);
                $orderLineItem->save();
            }
        }
    }

    private function createOrder(
        PaymentMethod $paymentMethod,
        Customer $customer,
        int $totalPrice,
        int $administrationFees,
    ): Order {
        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->status = OrderStatus::IN_PROGRESS;
        $order->payment_method = $paymentMethod;
        $order->is_invoiced = false;
        $order->total_price = $totalPrice + $administrationFees;
        $order->administration_fees = $administrationFees;
        $order->customer()->associate($customer);
        $order->save();

        return $order;
    }

    private function getActualNetPriceIfVoucherIsApplied(
        TotalCollectionPrice $totalCollectionPrice,
        UuidInterface $uuid,
    ): ProductWithCalculatedPrice {
        foreach ($totalCollectionPrice->items as $productWithCalculatedPrice) {
            if ($productWithCalculatedPrice->uuid->toString() === $uuid->toString()) {
                return $productWithCalculatedPrice;
            }
        }

        throw new RuntimeException(
            "This should never happen, as there's always a cart item with the requested uuid after validation.",
        );
    }
}
