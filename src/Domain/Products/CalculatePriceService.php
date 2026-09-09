<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products;

use Illuminate\Support\Collection;
use Illuminate\Support\ItemNotFoundException;
use SandwaveIo\LighthouseAuthBase\Enum\Language;
use Waterfront\Domain\Cart\DTO\AppliedPrice;
use Waterfront\Domain\Cart\DTO\CartVoucher;
use Waterfront\Domain\Cart\DTO\RegularPrice;
use Waterfront\Domain\Cart\DTO\VoucherInformation;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Pricing\DTO\PriceComponents\PriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\VoucherPriceComponent;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Products\DTO\AddonRegistrationPriceRequest;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\DTO\PriceRequest;
use Waterfront\Domain\Products\DTO\ProductWithCalculatedPrice;
use Waterfront\Domain\Products\DTO\ProductWithPeriodsAndPrice;
use Waterfront\Domain\Products\DTO\ProlongationPriceRequest;
use Waterfront\Domain\Products\DTO\RegistrationPriceRequest;
use Waterfront\Domain\Products\DTO\TotalCollectionPrice;
use Waterfront\Domain\Products\DTO\UpgradePriceRequest;
use Waterfront\Domain\Products\Enums\ProductPriceType;
use Waterfront\Domain\Products\Enums\ProductSlug;
use Waterfront\Domain\Products\Enums\UsedProductPriceType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\ProductAllowedChangeRepository;
use Waterfront\Domain\Translations\Models\TranslationKey;
use Waterfront\Domain\Voucher\Models\Voucher;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Config\ApplicationConfig;
use Webmozart\Assert\Assert;

class CalculatePriceService
{
    public function __construct(
        private readonly PriceResolver $priceResolver,
        private readonly ProductRepository $productRepository,
        private readonly ApplicationConfig $applicationConfig,
        private readonly TranslatorInterface $translator,
        private readonly AuthenticationManager $authenticationManager,
        private readonly ProductAllowedChangeRepository $productAllowedChangeRepository,
    ) {
    }

    /**
     * @param Collection<int, ProductWithPeriodsAndPrice> $products
     * @param Voucher[]                                   $vouchers
     */
    public function calculatePrices(Customer $customer, Collection $products, array $vouchers): TotalCollectionPrice
    {
        $priceRequest = $this->assemblePriceRequest($products, $customer, $vouchers);
        $priceList = $this->priceResolver->getPriceList($priceRequest);
        $prices = $priceList->getPrices();

        $usedVouchersOnOrder = [];
        $totalPriceExclVat = 0;
        $totalPriceInclVat = 0;
        $calculatedProducts = [];

        foreach ($products as $productDto) {
            if ($this->requiresProRata($productDto)) {
                $productModel = $this->productRepository->findProductBySlug($productDto->slug);
                $subscription = null;
                $productPriceRequest = null;

                // With the checks in requiresProRata() we can only reach this point for upgrades and adding addons
                if ($productDto->subscription !== null && $this->isUpgrade($productDto->subscription->product, $productModel)) {
                    $subscription = $productDto->subscription;
                    $productPriceRequest = new UpgradePriceRequest($productModel, $subscription->next_billing_date, $subscription->net_price);
                } else {
                    Assert::isInstanceOf($productDto->parentSubscription, Subscription::class);
                    $subscription = $productDto->parentSubscription;
                    $productPriceRequest = new AddonRegistrationPriceRequest($productModel, $subscription->next_billing_date);
                }

                $proRataPricelist = $this->priceResolver->getPriceList(new PriceRequest([$productPriceRequest], $customer, [], true));
                $productDto->price = $proRataPricelist->getProductPrice($productModel->slug, $subscription->contract_period, $subscription->billing_period);
            } else {
                $price = $this->findAndPop(
                    $prices,
                    fn (Price $price) => $price->billingPeriod === $productDto->billingPeriod && $price->contractPeriod === $productDto->contractPeriod && $price->type === ProductPriceType::REGISTRATION && $price->productId === $productDto->productId
                );

                if (! $price instanceof Price) {
                    throw new ItemNotFoundException(
                        sprintf('No base price found for product with slug %s, contract period %d, billing period %d', $productDto->slug, $productDto->contractPeriod, $productDto->billingPeriod),
                    );
                }

                $productDto->price = $price;
            }

            // Transfer service should be free if the parent hosting subscription has service plus product spec enabled
            if ($productDto->slug === ProductSlug::TRANSFER_SERVICE->value) {
                $parent = array_find($products->all(), fn (ProductWithPeriodsAndPrice $hostingProduct) => $hostingProduct->uuid->toString() === $productDto->parentItemUuid?->toString());

                if ($parent !== null && $this->productRepository->hasServicePlus($this->productRepository->findProductById($parent->productId))) {
                    $productDto->price->calculatedPrice = 0;
                }
            }

            Assert::notNull($productDto->price);
            Assert::natural($productDto->price->calculatedPrice);

            $voucherPriceComponent = array_find(
                $productDto->price->appliedPriceComponents,
                fn (PriceComponent $priceComponent): bool => $priceComponent->type === PriceComponentType::VOUCHER
            );
            $cartVoucher = null;
            $priceExplanation = null;
            $actionPeriod = null;
            $actionPeriodPrice = null;
            $priceType = UsedProductPriceType::REGULAR_PRICE;

            if ($voucherPriceComponent instanceof VoucherPriceComponent) {
                $voucher = $voucherPriceComponent->voucher;
                $cartVoucher = new CartVoucher(
                    $voucher->id,
                    $voucher->code,
                    $voucher->display_name,
                    $voucher->description ?? '',
                    $voucher->amount,
                    $voucher->amount_type,
                    true,
                    $voucherPriceComponent->appliedAmount,
                );

                $usedVouchersOnOrder[$voucher->code] = new VoucherInformation(
                    code: $voucher->code,
                    claimedAmount: 0,
                    valid: true,
                    description: $voucher->description ?? '',
                    name: $voucher->display_name,
                    message: null,
                );
                $priceType = UsedProductPriceType::VOUCHER_PRICE;
            } else {
                $priceExplanation = $this->translatePriceExplanation($productDto->price->priceExplanation);
                $actionPeriod = $productDto->price->actionPeriod;
                $actionPeriodPrice = $productDto->price->actionPeriodPrice;
            }

            $productWithPrice = new ProductWithCalculatedPrice(
                $productDto->uuid,
                $productDto->parentItemUuid,
                $productDto->slug,
                $productDto->productId,
                $productDto->billingPeriod,
                $productDto->contractPeriod,
                new RegularPrice(priceInclVat: $this->calculateVatPrice($customer, $productDto->price->regularPrice), priceExclVat: $productDto->price->regularPrice),
                new AppliedPrice(
                    priceInclVat: $this->calculateVatPrice($customer, $productDto->price->calculatedPrice),
                    priceExclVat: $productDto->price->calculatedPrice,
                    priceType: $priceType,
                    actionPeriod: $actionPeriod,
                    actionPeriodPrice: $actionPeriodPrice,
                    voucher: $cartVoucher,
                    priceExplanation: $priceExplanation
                ),
                $productDto->price,
            );
            $calculatedProducts[] = $productWithPrice;
            $totalPriceExclVat += $productWithPrice->appliedPrice->priceExclVat;
            $totalPriceInclVat += $productWithPrice->appliedPrice->priceInclVat;
        }

        Assert::natural($totalPriceExclVat);
        Assert::natural($totalPriceInclVat);

        return new TotalCollectionPrice(new Collection($calculatedProducts), $usedVouchersOnOrder, $totalPriceExclVat, $totalPriceInclVat);
    }

    public function calculateVatPrice(Customer $customer, int $price): int
    {
        if ($customer->vat_rate !== null) {
            return (int) round($price * (1 + ($customer->vat_rate / 100)));
        }

        return (int) round($price * (1 + ($this->applicationConfig->defaultTaxRate / 100)));
    }

    /**
     * The following logic is applied here:
     * Adding an addon:
     * Requires parentSubscription property to be filled
     * Processing: subscription is created directly
     * Prorata: should be applied
     *
     * Upgrading an existing subscription:
     * Requires subscription property to be filled
     * Processing: subscription is modified directly
     * Prorata: should be applied
     *
     * Downgrading an existing subscription:
     * Requires subscription property to be filled
     * Processing: mutation is created for the upcoming renewal
     * Prorata: should not be applied
     *
     * Period change:
     * Requires subscription property to be filled
     * Processing: mutation is created for the upcoming renewal
     * Prorata: should not be applied
     */
    private function requiresProRata(ProductWithPeriodsAndPrice $product): bool
    {
        if ($product->parentSubscription !== null) {
            return true;
        }

        if ($product->subscription !== null && $product->subscription->product->slug !== $product->slug) {
            $productModel = $this->productRepository->findProductBySlug($product->slug);

            if ($this->isUpgrade($product->subscription->product, $productModel)) {
                return true;
            }
        }

        return false;
    }

    private function isUpgrade(Product $fromProduct, Product $toProduct): bool
    {
        return $this->productAllowedChangeRepository->isProductChangeAllowed(
            ProductChangeType::UPGRADE,
            $fromProduct,
            $toProduct
        );
    }

    private function translatePriceExplanation(?TranslationKey $translationKey): string|null
    {
        if ($translationKey === null) {
            return null;
        }

        $displayLanguage = $this->authenticationManager->getAuthenticatedCustomer()->identitySchema->metadataPublic->displayLanguage ?? Language::NL;

        return $this->translator->translate($translationKey->key, locale: $displayLanguage === Language::NL ? 'nl' : 'en');
    }

    /**
     * @param Collection<int, ProductWithPeriodsAndPrice> $products
     * @param Voucher[]                                   $vouchers
     *
     * The products coming in can contain multiple copies of the same product. The price resolver
     * allows for (and understands) a request with a quantity. In other words: we need to convert a
     * list of products into a list of unique products with a quantity, to be processed by the
     * price resolver.
     */
    private function assemblePriceRequest(Collection $products, Customer $customer, array $vouchers): PriceRequest
    {
        $productIds = [];
        $quantities = [];

        foreach ($products as $productDto) {
            $productIds[] = $productDto->productId;
            $key = sprintf('%d-%s-%d-%d-%s', $productDto->productId, $productDto->priceType->value, $productDto->contractPeriod, $productDto->billingPeriod, $productDto->experimentSlug);

            if (array_key_exists($key, $quantities)) {
                $quantities[$key] = $quantities[$key] + 1;
            } else {
                $quantities[$key] = 1;
            }
        }

        $products = $this->productRepository->getProductsUsingIds(array_unique($productIds));
        $productPriceRequests = [];

        foreach ($quantities as $key => $quantity) {
            $keyParts = explode('-', $key);
            $type = ProductPriceType::from($keyParts[1]);
            $contractPeriod = (int) $keyParts[2];
            $billingPeriod = (int) $keyParts[3];
            $experimentSlug = $keyParts[4];
            $product = $products->findOrFail((int) $keyParts[0]);

            $productPriceRequests[] = match ($type) {
                ProductPriceType::REGISTRATION => new RegistrationPriceRequest($product, $quantity, $contractPeriod, $billingPeriod, $experimentSlug),
                ProductPriceType::PROLONGATION => new ProlongationPriceRequest($product, $quantity, $contractPeriod, $billingPeriod, $experimentSlug),
            };
        }

        return new PriceRequest($productPriceRequests, $customer, $vouchers, true);
    }

    /**
     * @template T
     *
     * @param array<int, T> $input
     *
     * @return T|null
     */
    private function findAndPop(array &$input, callable $filter): mixed
    {
        foreach ($input as $key => $value) {
            if (! $filter($value)) {
                continue;
            }

            $value = $input[$key];
            unset($input[$key]);

            return $value;
        }

        return null;
    }
}
