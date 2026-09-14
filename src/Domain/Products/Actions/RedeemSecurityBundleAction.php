<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Actions;

use Illuminate\Contracts\Bus\Dispatcher;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Orders\Enums\OrderLineItemStatus;
use Waterfront\Domain\Orders\Enums\OrderStatus;
use Waterfront\Domain\Orders\Jobs\ProcessOrderJob;
use Waterfront\Domain\Orders\Models\Order;
use Waterfront\Domain\Orders\Models\OrderLineItem;
use Waterfront\Domain\Payments\Enums\PaymentMethod;
use Waterfront\Domain\Pricing\DTO\PriceComponents\CustomIndefinitePriceComponent;
use Waterfront\Domain\Pricing\Services\PricePersistService;
use Waterfront\Domain\Products\DTO\OfferedProductDTO;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\DTO\PriceRequest;
use Waterfront\Domain\Products\DTO\RegistrationPriceRequest;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductPriceType;
use Waterfront\Domain\Products\Exceptions\ProductExperimentOfferingAlreadyRedeemedException;
use Waterfront\Domain\Products\Exceptions\ProductExperimentOfferingNotClaimableException;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Domain\Products\Repositories\ProductExperimentOfferingRepository;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\Authentication\AuthenticationManager;

class RedeemSecurityBundleAction
{
    /**
     * The term the experiment sells its own subscriptions on, decided for this experiment rather than
     * read from the product group. DNS never uses this: those are upgrades, so they keep the periods of
     * the subscription already running on the domain.
     */
    private const int CONTRACT_PERIOD_IN_MONTHS = 12;

    private const int BILLING_PERIOD_IN_MONTHS = 12;

    public function __construct(
        private readonly ProductExperimentOfferingRepository $offeringRepository,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly PricePersistService $pricePersistService,
        private readonly PriceResolver $priceResolver,
        private readonly Dispatcher $jobDispatcher,
        private readonly AuthenticationManager $authManager,
    ) {
    }

    /**
     * @param non-empty-list<string> $selectedProductSlugs the offering products the customer wants
     *
     * @throws ProductExperimentOfferingAlreadyRedeemedException
     * @throws ProductExperimentOfferingNotClaimableException
     */
    public function execute(Customer $customer, array $selectedProductSlugs): void
    {
        $offering = $this->offeringRepository->findOfferingForCustomer($customer);

        if ($offering === null) {
            throw new ProductExperimentOfferingNotClaimableException(
                sprintf('Customer %d is not enrolled in a product experiment offering', $customer->id),
            );
        }

        if ($this->offeringRepository->isRedeemedByCustomer($offering, $customer)) {
            throw new ProductExperimentOfferingAlreadyRedeemedException(
                sprintf('Customer %d already redeemed offering %d', $customer->id, $offering->id),
            );
        }

        $offeredProducts = [];
        $newSubscriptionPrices = [];

        foreach ($selectedProductSlugs as $index => $selectedProductSlug) {
            $offeredProduct = $this->offeringRepository->findOfferedProductBySlug($offering, $selectedProductSlug);

            if ($offeredProduct === null) {
                throw new ProductExperimentOfferingNotClaimableException(
                    sprintf('Offering %d does not contain product %s', $offering->id, $selectedProductSlug),
                );
            }

            $offeredProduct->product->loadMissing('productGroup');
            $offeredProducts[$index] = $offeredProduct;

            if ($offeredProduct->product->productGroup->slug === ProductGroupType::DNS) {
                if (! $offeredProduct->isFree) {
                    throw new ProductExperimentOfferingNotClaimableException(
                        sprintf('Product %s can only be offered free of charge', $offeredProduct->product->slug),
                    );
                }

                continue;
            }

            $newSubscriptionPrices[$index] = $this->priceForNewSubscription($customer, $offeredProduct);
        }

        $this->offeringRepository->markRedeemedByCustomer($offering, $customer);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->status = OrderStatus::IN_PROGRESS;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->is_invoiced = false;
        // Known up front: the DNS lines are always free, so the paid products are the whole total.
        $order->total_price = array_sum(
            array_map(static fn (Price $price): int => $price->calculatedPrice ?? 0, $newSubscriptionPrices),
        );
        $order->administration_fees = 0;
        $order->customer()->associate($customer);

        $authCustomer = $this->authManager->getAuthenticatedCustomer();
        $identity = $authCustomer->identitySchema;

        $order->ordered_by_uuid = $identity->id;
        $order->ordered_by_metadata = json_encode([
            'email' => $identity->traits?->email,
            'schemaId' => $identity->schemaId->value,
        ], JSON_THROW_ON_ERROR);

        $order->save();

        foreach ($offeredProducts as $index => $offeredProduct) {
            if ($offeredProduct->product->productGroup->slug === ProductGroupType::DNS) {
                $this->addDnsUpgradeForEveryDomain($customer, $order, $offeredProduct);
                continue;
            }

            $this->addProductToOrder($order, $offeredProduct, $newSubscriptionPrices[$index]);
        }

        $this->jobDispatcher->dispatch(new ProcessOrderJob($order));
    }

    /**
     * A stakeholder decision: when the DNS product is ordered, every domain the customer owns moves to
     * it. Domains registered after this point keep whatever DNS product they are created with.
     */
    private function addDnsUpgradeForEveryDomain(
        Customer $customer,
        Order $order,
        OfferedProductDTO $offeredProduct,
    ): void {
        $product = $offeredProduct->product;

        foreach ($this->subscriptionRepository->getDnsSubscriptionsNotUsingProduct(
            $customer,
            $product,
        ) as $dnsSubscription) {
            $orderLine = $this->createOrderLine(
                $order,
                $product,
                $dnsSubscription->contract_period,
                $dnsSubscription->billing_period,
                isFree: true,
            );

            $orderLine->domain = $dnsSubscription->domain;
            $orderLine->subscription_uuid = $dnsSubscription->uuid;
            $orderLine->save();

            $this->pricePersistService->persistOrderLineItemPrice(
                $orderLine,
                $this->freePersistentPrice(
                    $product,
                    $dnsSubscription->contract_period,
                    $dnsSubscription->billing_period,
                ),
            );
        }
    }

    private function priceForNewSubscription(Customer $customer, OfferedProductDTO $offeredProduct): Price
    {
        $product = $offeredProduct->product;

        return $offeredProduct->isFree
            ? $this->freePersistentPrice($product, self::CONTRACT_PERIOD_IN_MONTHS, self::BILLING_PERIOD_IN_MONTHS)
            : $this->registrationPrice(
                $customer,
                $product,
                self::CONTRACT_PERIOD_IN_MONTHS,
                self::BILLING_PERIOD_IN_MONTHS,
            );
    }

    private function addProductToOrder(Order $order, OfferedProductDTO $offeredProduct, Price $price): void
    {
        $orderLine = $this->createOrderLine(
            $order,
            $offeredProduct->product,
            $price->contractPeriod,
            $price->billingPeriod,
            $offeredProduct->isFree,
        );

        $this->pricePersistService->persistOrderLineItemPrice($orderLine, $price);
    }

    private function freePersistentPrice(Product $product, int $contractPeriod, int $billingPeriod): Price
    {
        return new Price(
            type: ProductPriceType::REGISTRATION,
            billingPeriod: $billingPeriod,
            productId: $product->id,
            productGroupUuid: $product->productGroup->uuid,
            regularPrice: 0,
            contractPeriod: $contractPeriod,
            orderable: true,
            is_default: true,
            appliedPriceComponents: [new CustomIndefinitePriceComponent(0)],
            calculatedPrice: 0,
        );
    }

    private function registrationPrice(
        Customer $customer,
        Product $product,
        int $contractPeriod,
        int $billingPeriod,
    ): Price {
        $priceList = $this->priceResolver->getPriceList(
            new PriceRequest([new RegistrationPriceRequest($product)], $customer),
        );

        return $priceList->getProductPrice($product->slug, $contractPeriod, $billingPeriod);
    }

    private function createOrderLine(
        Order $order,
        Product $product,
        int $contractPeriod,
        int $billingPeriod,
        bool $isFree,
    ): OrderLineItem {
        $orderLine = new OrderLineItem();
        $orderLine->order()->associate($order);
        $orderLine->product()->associate($product);
        $orderLine->product_name = $product->name;
        $orderLine->status = OrderLineItemStatus::REGISTRATION;
        $orderLine->contract_period = $contractPeriod;
        $orderLine->billing_period = $billingPeriod;
        $orderLine->gross_price = 0;
        $orderLine->net_price = 0;
        $orderLine->should_invoice = ! $isFree;
        $orderLine->save();

        return $orderLine;
    }
}
