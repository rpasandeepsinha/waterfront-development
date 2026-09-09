<?php

declare(strict_types=1);

namespace Waterfront\Domain\Cart\Services;

use Illuminate\Support\Collection;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Cart\DTO\Cart;
use Waterfront\Domain\Cart\DTO\CartItemWithoutPrice;
use Waterfront\Domain\Cart\DTO\CartItemWithPrice;
use Waterfront\Domain\Cart\DTO\CartPrice;
use Waterfront\Domain\Cart\DTO\CartWithPrices;
use Waterfront\Domain\Cart\DTO\VoucherInformation;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Invoices\Services\AdministrationFeesManager;
use Waterfront\Domain\Orders\DTO\CartOrder;
use Waterfront\Domain\Orders\DTO\CartOrderLines\LineItem;
use Waterfront\Domain\Orders\DTO\CartOrderLines\OneTimeServiceLineItem;
use Waterfront\Domain\Payments\Services\PaymentService;
use Waterfront\Domain\Products\CalculatePriceService;
use Waterfront\Domain\Products\DTO\ProductWithPeriodsAndPrice;
use Waterfront\Domain\Products\DTO\TotalCollectionPrice;
use Waterfront\Domain\Products\Enums\ProductPriceType;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Domain\Voucher\Models\Voucher;
use Waterfront\Domain\Voucher\Repository\VoucherRepository;
use Waterfront\Domain\Voucher\Services\VoucherService;
use Waterfront\Infra\Translation\Translator;

class CartService
{
    public function __construct(
        private readonly CalculatePriceService $calculatePriceService,
        private readonly VoucherRepository $voucherRepository,
        private readonly VoucherService $voucherService,
        private readonly ProductRepository $productRepository,
        private readonly AdministrationFeesManager $administrationFeesManager,
        private readonly Translator $translator,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly PaymentService $paymentService,
    ) {
    }

    public function checkVouchersAndCalculateAppliedVoucherAppliedAmount(Customer $customer, Cart $cartOrder): TotalCollectionPrice
    {
        $checkedVoucherCodes = $this->getValidVoucherCodes($cartOrder->vouchers ?? [], $customer);
        $vouchers =  $this->getVouchers($checkedVoucherCodes);

        if (count($cartOrder->cartItems) === 0) {
            $calculatedVoucherCart = new TotalCollectionPrice(
                items: new Collection(),
                vouchers: $checkedVoucherCodes,
                totalExclVatPrice: 0,
                totalInclVatPrice: 0
            );
            $calculatedVoucherCart->vouchers = $this->findNotUsedVouchers($calculatedVoucherCart->vouchers, $checkedVoucherCodes);

            return $calculatedVoucherCart;
        }

        $products = $this->convertCartOrderToProductsWithPeriodsAndPriceCollection($cartOrder);

        $calculatedVoucherCart = $this->calculatePriceService->calculatePrices($customer, $products, $vouchers);

        $calculatedVoucherCart = $this->setMissingInvalidVouchers($calculatedVoucherCart, $checkedVoucherCodes);

        if (count($vouchers) > 0) {
            $calculatedVoucherCart = $this->calculateAppliedVoucherAppliedAmount($calculatedVoucherCart);

            $calculatedVoucherCart->vouchers = $this->findNotUsedVouchers($calculatedVoucherCart->vouchers, $checkedVoucherCodes);
        }

        return $calculatedVoucherCart;
    }

    /**
     * @param array<string, VoucherInformation> $checkedVouchersCodes
     *
     */
    public function setMissingInvalidVouchers(TotalCollectionPrice $calculatedVoucherCart, array $checkedVouchersCodes): TotalCollectionPrice
    {
        foreach ($checkedVouchersCodes as $voucherCode => $voucherItem) {
            $calculatedVoucherCart->vouchers[$voucherCode] ??= $voucherItem;
        }
        return $calculatedVoucherCart;
    }

    /**
     * @return Collection<int, ProductWithPeriodsAndPrice>
     */
    public function convertCartOrderToProductsWithPeriodsAndPrice(CartOrder $cartOrder): Collection
    {
        $products = [];

        foreach (get_object_vars($cartOrder->subscriptions) as $lineItem) {
            if (! is_array($lineItem)) {
                continue;
            }

            foreach ($lineItem as $item) {
                assert($item instanceof LineItem);
                if ($item->oneTimeServices !== null) {
                    foreach ($item->oneTimeServices as $oneTimeService) {
                        $newOts = new OneTimeServiceLineItem(
                            uuid: $oneTimeService->uuid,
                            slug: $oneTimeService->slug,
                            parentItemUuid: $item->uuid,
                        );

                        $products[] = $this->convertCartItemToProductDTO($newOts);
                    }
                }

                $products[] = $this->convertCartItemToProductDTO($item);

                if ($item->children !== null) {
                    foreach (get_object_vars($item->children) as $children) {
                        if (! is_array($children)) {
                            continue;
                        }

                        foreach ($children as $child) {
                            assert($child instanceof LineItem);

                            $products[] = $this->convertCartItemToProductDTO($child);
                        }
                    }
                }
            }
        }

        return new Collection($products);
    }

    /**
     * @param array<string> $voucherCodes
     *
     * @return array<string, VoucherInformation>
     */
    public function getValidVoucherCodes(array $voucherCodes, Customer $customer): array
    {
        $checkedVoucherCodes = [];

        foreach ($voucherCodes as $voucherCode) {
            $checkedVoucherCodes[$voucherCode] = new VoucherInformation(
                code: $voucherCode,
                claimedAmount: 0,
                valid: false,
                description: null,
                name: null,
                message: null,
            );

            if ($this->voucherRepository->doesVoucherExist($voucherCode) === false) {
                $checkedVoucherCodes[$voucherCode]->message = $this->translator->translate('voucher.voucher_not_found');
                continue;
            }
            $voucher = $this->voucherRepository->findByCode($voucherCode);

            $checkedVoucherCodes[$voucherCode]->name = $voucher->display_name;
            $checkedVoucherCodes[$voucherCode]->description = $voucher->description ?? '';

            if ($this->voucherService->isExpired($voucher)) {
                $checkedVoucherCodes[$voucherCode]->message = $this->translator->translate('voucher.voucher_has_expired');
            } elseif ($this->voucherService->noClaimsLeft($voucher)) {
                $checkedVoucherCodes[$voucherCode]->message = $this->translator->translate('voucher.no_claims_left');
            } elseif ($this->voucherService->cantBeClaimedAgain($voucher, $customer)) {
                $checkedVoucherCodes[$voucherCode]->message = $this->translator->translate('voucher.already_claimed');
            } else {
                $checkedVoucherCodes[$voucherCode]->valid = true;
            }
        }
        return $checkedVoucherCodes;
    }

    /**
     * @param array<string, VoucherInformation> $vouchers
     *
     * @return Voucher[]
     */
    public function getVouchers(array $vouchers): array
    {
        $validVouchers = [];
        foreach ($vouchers as $voucher) {
            if ($voucher->valid) {
                $validVouchers[] = $this->voucherRepository->findByCode($voucher->code);
            }
        }

        return $validVouchers;
    }

    public function convertTotalPriceCollectionToCartWithPricesStructure(
        TotalCollectionPrice $collection,
        Cart $cartOrder,
        Customer $customer
    ): CartWithPrices {
        $cartItems = [];
        $totalPriceInclVat = 0;
        $totalPriceExclVat = 0;

        foreach ($collection->items as $product) {
            $cartPrice = new CartPrice($product->regularPrice, $product->appliedPrice);
            $specificProductFromCartOrder = null;
            foreach ($cartOrder->cartItems as $cartItem) {
                if ($cartItem->itemUuid === $product->uuid) {
                    $specificProductFromCartOrder = $cartItem;
                }
            }
            assert($specificProductFromCartOrder instanceof CartItemWithoutPrice);

            $cartItems[] = new CartItemWithPrice(
                itemUuid: $product->uuid,
                parentItemUuid: $specificProductFromCartOrder->parentItemUuid ?? null,
                parentSubscriptionUuid: $specificProductFromCartOrder->parentSubscriptionUuid,
                productSlug: $product->slug,
                billingPeriod: $specificProductFromCartOrder->billingPeriod,
                contractPeriod: $specificProductFromCartOrder->contractPeriod,
                price: $cartPrice,
                priceType: $specificProductFromCartOrder->priceType,
                quantity: $specificProductFromCartOrder->quantity,
                metaData: $specificProductFromCartOrder->metaData
            );

            $totalPriceInclVat += $product->appliedPrice->priceInclVat;
            $totalPriceExclVat += $product->appliedPrice->priceExclVat;
        }

        $administrationFees = $this->getAdministrationFeesPrice($cartOrder, $customer);
        $totalPriceInclVat += $this->calculatePriceService->calculateVatPrice($customer, $administrationFees);
        $totalPriceExclVat += $administrationFees;

        return new CartWithPrices($cartItems, $totalPriceExclVat, $totalPriceInclVat, $administrationFees, $collection->vouchers);
    }

    public function shouldCreateDirectDebitMandate(Customer $customer, string $paymentMethod): bool
    {
        return ! $customer->has_direct_debit
            && $this->paymentService->isPaymentMethodThatSupportsDirectDebitCreation($paymentMethod);
    }

    private function convertCartItemToProductDTO(LineItem $cartItem): ProductWithPeriodsAndPrice
    {
        $productModel = $this->productRepository->findProductBySlug($cartItem->slug);

        $parentSubscriptionModel = null;
        if (! is_null($cartItem->parentSubscriptionUuid)) {
            $parentSubscriptionModel = $this->subscriptionRepository->getByUuid($cartItem->parentSubscriptionUuid);
        }

        $subscriptionModel = null;
        if (! is_null($cartItem->subscriptionUuid)) {
            $subscriptionModel = $this->subscriptionRepository->getByUuid($cartItem->subscriptionUuid);
        }

        return new ProductWithPeriodsAndPrice(
            uuid: $cartItem->uuid,
            parentItemUuid: $cartItem->parentSubscriptionUuid !== null ? Uuid::fromString($cartItem->parentSubscriptionUuid) : null,
            slug: $cartItem->slug,
            productId: $productModel->id,
            billingPeriod: $cartItem->billingPeriod,
            contractPeriod: $cartItem->contractPeriod,
            priceType: $this->convertProductPriceType($cartItem),
            price: null,
            parentSubscription: $parentSubscriptionModel,
            subscription: $subscriptionModel,
            experimentSlug: $cartItem->experimentSlug,
        );
    }

    private function getAdministrationFeesPrice(Cart $cart, Customer $customer): int
    {
        if (
            $this->administrationFeesManager->shouldBeChargedWithOrder(
                $customer,
                $cart->paymentMethod,
                $this->shouldCreateDirectDebitMandate($customer, $cart->paymentMethod ?? ''),
            )
        ) {
            return $this->administrationFeesManager->getAdministrationFees($customer)->price ?? 0;
        }

        return 0;
    }

    private function convertProductPriceType(LineItem $item): ProductPriceType
    {
        return $item->status ?? ProductPriceType::REGISTRATION;
    }

    private function calculateAppliedVoucherAppliedAmount(TotalCollectionPrice $calculatedCart): TotalCollectionPrice
    {
        foreach ($calculatedCart->vouchers as $key => $voucher) {
            foreach ($calculatedCart->items as $productDto) {
                if ($productDto->appliedPrice->voucher?->code === $voucher->code) {
                    $voucher->claimedAmount += $productDto->appliedPrice->voucher->appliedAmount;
                    $calculatedCart->vouchers[$key] = $voucher;
                }
            }
        }

        return $calculatedCart;
    }

    /**
     * @param array<string, VoucherInformation> $vouchers
     * @param array<string, VoucherInformation> $knownVouchers
     *
     * @return array<string, VoucherInformation>
     */
    private function findNotUsedVouchers(array $vouchers, array $knownVouchers): array
    {
        foreach ($knownVouchers as $voucher) {
            if (array_key_exists($voucher->code, $vouchers)) {
                if ($vouchers[$voucher->code]->claimedAmount === 0 && $voucher->valid) {
                    $voucher->message = $this->translator->translate('voucher.unused_voucher_found');
                }
                $voucher->claimedAmount = $vouchers[$voucher->code]->claimedAmount;
            }
        }
        return $knownVouchers;
    }

    /**
     * @return Collection<int, ProductWithPeriodsAndPrice>
     */
    private function convertCartOrderToProductsWithPeriodsAndPriceCollection(Cart $cartOrder): Collection
    {
        $products = [];

        foreach ($cartOrder->cartItems as $lineItem) {
            $productModel = $this->productRepository->findProductBySlug($lineItem->productSlug);

            $parentSubscription = $lineItem->parentSubscriptionUuid !== null ? $this->subscriptionRepository->getByUuid($lineItem->parentSubscriptionUuid) : null;
            $subscription = $lineItem->subscriptionUuid !== null ? $this->subscriptionRepository->getByUuid($lineItem->subscriptionUuid) : null;

            $products[] = new ProductWithPeriodsAndPrice(
                uuid: $lineItem->itemUuid,
                parentItemUuid: $lineItem->parentItemUuid,
                slug: $lineItem->productSlug,
                productId: $productModel->id,
                billingPeriod: $lineItem->billingPeriod,
                contractPeriod: $lineItem->contractPeriod,
                priceType: $lineItem->priceType,
                price: null,
                parentSubscription: $parentSubscription,
                subscription: $subscription,
                experimentSlug: $lineItem->metaData?->experimentSlug,
            );
        }

        return new Collection($products);
    }
}
