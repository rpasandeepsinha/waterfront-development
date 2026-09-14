<?php

declare(strict_types=1);

namespace Waterfront\Domain\OneTimeServices\Services;

use Carbon\CarbonImmutable;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Waterfront\Domain\Invoices\DTO\OneTimeServiceContext;
use Waterfront\Domain\Notes\Actions\StoreNoteAction;
use Waterfront\Domain\OneTimeServices\Enums\OneTimeServiceStatus;
use Waterfront\Domain\OneTimeServices\Models\OneTimeService;
use Waterfront\Domain\OneTimeServices\Repositories\OneTimeServiceRepository;
use Waterfront\Domain\Orders\Models\Order;
use Waterfront\Domain\Orders\Models\OrderLineItem;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;

class OneTimeServiceCreator
{
    public function __construct(
        private readonly OneTimeServiceRepository $oneTimeServiceRepository,
        private readonly GrossPriceResolver $grossPriceResolver,
        private readonly LoggerInterface $logger,
        private readonly StoreNoteAction $storeNoteAction,
    ) {
    }

    public function createFromOrder(Order $order): void
    {
        $this->logger->info(
            'Creating one time services from order {order.id}',
            [
                LoggingContextKeys::ORDER_ID => $order->id,
            ],
        );

        foreach ($order->lineItems->sortBy('parent_id') as $orderLineItem) {
            $this->createFromOrderLineItem($orderLineItem);
        }
    }

    public function createFromOrderLineItem(OrderLineItem $orderLineItem): void
    {
        $orderLineItem->loadMissing(
            'order',
            'product.productGroup',
            'parentSubscription',
            'product.productSpecs',
            'parent',
        );
        $order = $orderLineItem->order;
        if ($orderLineItem->one_time_service_id !== null) {
            return;
        }

        if (! $orderLineItem->product instanceof Product) {
            $this->logger->error(
                'Product is required to create a one time service for order line #{order_line.id} for order #{order.id}.',
                [
                    LoggingContextKeys::ORDER_ID => $order->id,
                    LoggingContextKeys::ORDER_LINE_ID => $orderLineItem->id,
                ],
            );

            throw new RuntimeException(
                sprintf(
                    'Product is required to create a one time service for order line #%s for order #%s.',
                    $orderLineItem->id,
                    $order->id,
                ),
            );
        }

        if ($orderLineItem->product->productGroup->slug !== ProductGroupType::ONE_TIME_SERVICE) {
            return;
        }

        if (! $orderLineItem->parent instanceof OrderLineItem) {
            $this->logger->error(
                'Parent subscription missing for one time service for order line #{order_line.id} for order #{order.id}.',
                [
                    LoggingContextKeys::ORDER_ID => $order->id,
                    LoggingContextKeys::ORDER_LINE_ID => $orderLineItem->id,
                    LoggingContextKeys::PRODUCT_ID => $orderLineItem->product->id,
                    LoggingContextKeys::PRODUCT_SLUG => $orderLineItem->product->slug,
                ],
            );

            throw new RuntimeException(
                sprintf(
                    'Parent subscription missing for one time service for order line #%s for order #%s.',
                    $orderLineItem->id,
                    $order->id,
                ),
            );
        }

        $subscription = $orderLineItem->parent->refresh()->subscription;
        if (! $subscription instanceof Subscription) {
            $this->logger->error(
                'Could not create one time service for order line #{order_line.id} for order #{order.id} because of missing subscription. Subscription should be created first.',
                [
                    LoggingContextKeys::ORDER_ID => $order->id,
                    LoggingContextKeys::ORDER_LINE_ID => $orderLineItem->id,
                    LoggingContextKeys::PRODUCT_ID => $orderLineItem->product->id,
                    LoggingContextKeys::PRODUCT_SLUG => $orderLineItem->product->slug,
                ],
            );

            throw new RuntimeException(
                sprintf(
                    'Could not create one time service for order line #%s for order #%s because of missing subscription. Subscription should be created first.',
                    $orderLineItem->id,
                    $order->id,
                ),
            );
        }

        $oneTimeServiceContext = new OneTimeServiceContext(
            subscription: $subscription,
            product: $orderLineItem->product,
            amount: 1,
            discountPercentage: $this->calculateDiscount($orderLineItem->gross_price, $orderLineItem->net_price),
            status: OneTimeServiceStatus::OPEN,
            executionDate: CarbonImmutable::now(),
            comment: null,
            grossPrice: $orderLineItem->gross_price,
        );

        $oneTimeService = $this->createFromContextWithNote($oneTimeServiceContext);
        $orderLineItem->one_time_service_id = $oneTimeService->id;
        $orderLineItem->processed_at = CarbonImmutable::now();
        $orderLineItem->save();
    }

    public function createFromContextWithNote(OneTimeServiceContext $context): OneTimeService
    {
        $grossPrice = $context->grossPrice ?? $this->grossPriceResolver->getGrossPrice(
            $context->product,
            $context->subscription->product->id,
        );
        $ots = $this->oneTimeServiceRepository->create($context, $grossPrice);

        $noteMessage = sprintf(
            'One-time service created for %s x%s with %s discount: %s',
            $context->product->name,
            $context->amount,
            $context->discountPercentage . '%',
            $context->comment,
        );

        $this->storeNoteAction->execute($noteMessage, $context->subscription);

        $this->logger->notice(
            'One-time service created for subscription #{subscription.id}: {product.slug}',
            [
                LoggingContextKeys::SUBSCRIPTION_ID => $context->subscription->id,
                LoggingContextKeys::PRODUCT_SLUG => $context->product->slug,
            ],
        );

        return $ots;
    }

    private function calculateDiscount(int $grossPrice, int $netPrice): int
    {
        if ($grossPrice === 0) {
            return 0;
        }

        return (int) round((($grossPrice - $netPrice) / $grossPrice) * 100);
    }
}
