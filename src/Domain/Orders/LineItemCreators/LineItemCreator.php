<?php

declare(strict_types=1);

namespace Waterfront\Domain\Orders\LineItemCreators;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Waterfront\Domain\Orders\DTO\CartOrderLines\LineItem;
use Waterfront\Domain\Orders\Enums\OrderLineItemStatus;
use Waterfront\Domain\Orders\Models\Order;
use Waterfront\Domain\Orders\Models\OrderLineItem;
use Waterfront\Domain\Orders\Serializers\CartSerializerFactory;
use Waterfront\Domain\Pricing\Services\PricePersistService;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Exceptions\ProductNotFoundException;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Webmozart\Assert\Assert;

class LineItemCreator
{
    public function __construct(
        protected readonly ProductRepository $productRepository,
        private readonly CartSerializerFactory $cartSerializerFactory,
        private readonly PricePersistService $pricePersistService,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    public function create(LineItem $cartOrderItem, Order $order, Price $price): OrderLineItem
    {
        Assert::natural($price->calculatedPrice);

        $orderLineItem = new OrderLineItem();
        $orderLineItem->order()->associate($order);
        $orderLineItem->parent_subscription_uuid = $cartOrderItem->parentSubscriptionUuid;
        $orderLineItem->subscription_uuid = $cartOrderItem->subscriptionUuid;

        try {
            $product = $this->productRepository->findProductBySlug($cartOrderItem->slug);
        } catch (ModelNotFoundException $e) {
            throw new ProductNotFoundException(
                sprintf('Could not find product with slug %s', $cartOrderItem->slug),
                $e->getCode(),
                $e
            );
        }

        $orderLineItem->product()->associate($product);

        $orderLineItem->status = $cartOrderItem->status === null ? OrderLineItemStatus::REGISTRATION : OrderLineItemStatus::from($cartOrderItem->status->value);
        $orderLineItem->product_name = $product->name;
        $orderLineItem->domain = $cartOrderItem->domain ?? null;

        // When there's no domain supplied for an addon, take the one from the parent subscription.
        if ($cartOrderItem->parentSubscriptionUuid !== null && $cartOrderItem->domain === null) {
            $orderLineItem->domain = Subscription::where('uuid', $cartOrderItem->parentSubscriptionUuid)->firstOrFail()->domain;
        }

        $orderLineItem->gross_price = $price->regularPrice;
        $orderLineItem->billing_period = $cartOrderItem->billingPeriod;
        $orderLineItem->contract_period = $cartOrderItem->contractPeriod;
        $orderLineItem->net_price = $price->calculatedPrice;
        $orderLineItem->meta_data = $this->getMetaData($cartOrderItem, $product->productGroup->slug);
        $orderLineItem->experiment_slug = $cartOrderItem->experimentSlug;
        $orderLineItem->should_invoice = $this->shouldInvoice($cartOrderItem);
        $orderLineItem->save();

        $this->pricePersistService->persistOrderLineItemPrice($orderLineItem, $price);

        return $orderLineItem;
    }

    private function shouldInvoice(LineItem $cartOrderItem): bool
    {
        return $cartOrderItem->subscriptionUuid === null;
    }

    /**
     * @throws ExceptionInterface
     */
    private function getMetaData(LineItem $cartOrderItem, ProductGroupType $productGroupType): ?string
    {
        $data = $this->cartSerializerFactory->get()
            ->normalize(
                $cartOrderItem,
                null,
                [
                    'groups' => 'meta_data',
                    AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true,
                ]
            );

        assert(is_array($data));

        if (count($data) === 0) {
            return null;
        }

        $data['type'] = $productGroupType->value;

        return $this->cartSerializerFactory->get()->encode($data, 'json');
    }
}
