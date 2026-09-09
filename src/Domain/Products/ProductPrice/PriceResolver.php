<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\ProductPrice;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Pricing\DTO\PriceComponents\IntroductionPriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\PriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\ProductGroupPriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\ProlongationPriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\ProlongationStaffelPriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\PromotionPriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\ProRatePriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\RegistrationPriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\RegistrationStaffelPriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\VoucherPriceComponent;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Products\DTO\AddonRegistrationPriceRequest;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\DTO\PriceList;
use Waterfront\Domain\Products\DTO\PriceRequest;
use Waterfront\Domain\Products\DTO\Product;
use Waterfront\Domain\Products\DTO\ProductPriceRequest;
use Waterfront\Domain\Products\DTO\ProlongationPriceRequest;
use Waterfront\Domain\Products\DTO\RegistrationPriceRequest;
use Waterfront\Domain\Products\DTO\UpgradePriceRequest;
use Waterfront\Domain\Products\Enums\ProductPriceType;
use Waterfront\Domain\Products\ProductPrice\PriceResolverHandler\BasePriceHandler;
use Waterfront\Domain\Products\ProductPrice\PriceResolverHandler\IntroDiscountPricePriceHandler;
use Waterfront\Domain\Products\ProductPrice\PriceResolverHandler\Microsoft365RemainingDurationComponentPriceHandler;
use Waterfront\Domain\Products\ProductPrice\PriceResolverHandler\ProductDiscountPriceComponentHandler;
use Waterfront\Domain\Products\ProductPrice\PriceResolverHandler\ProductGroupPriceComponentHandler;
use Waterfront\Domain\Products\ProductPrice\PriceResolverHandler\ProlongationPriceComponentHandler;
use Waterfront\Domain\Products\ProductPrice\PriceResolverHandler\PromotionPriceComponentHandler;
use Waterfront\Domain\Products\Services\PriceService;
use Waterfront\Domain\Translations\Models\TranslationKey;
use Waterfront\Domain\Voucher\Enum\VoucherAmountType;
use Waterfront\Domain\Voucher\Models\Voucher;
use Waterfront\Domain\Voucher\Services\VoucherService;
use Webmozart\Assert\Assert;

/**
 * For more extensive documentation on the pricing model, see confluence:.
 *
 * @see https://yh-jira.atlassian.net/wiki/spaces/SANDWAVE/pages/1232994530/Invoicing+Pricing+model
 */
readonly class PriceResolver
{
    public function __construct(
        private PriceListFactory $priceListFactory,
        private PriceService $priceService,
        private BasePriceHandler $basePriceHandler,
        private ProlongationPriceComponentHandler $prolongationPriceComponentHandler,
        private PromotionPriceComponentHandler $promotionPriceComponentHandler,
        private IntroDiscountPricePriceHandler $introDiscountPricePriceHandler,
        private Microsoft365RemainingDurationComponentPriceHandler $microsoft365RemainingDurationPricesPriceHandler,
        private ProductGroupPriceComponentHandler $productGroupDiscountPriceHandler,
        private ProductDiscountPriceComponentHandler $productDiscountPriceHandler,
        private VoucherService $voucherService,
    ) {
    }

    /**
     * @return PriceList<int, Product>
     */
    public function getPriceList(PriceRequest $request): PriceList
    {
        $vouchers = $request->vouchers;
        if ($request->customer !== null) {
            $vouchers = $this->filterUsableVouchers($vouchers, $request->customer);
        } else {
            $vouchers = [];
        }

        if (count($request->productPriceRequests) === 0) {
            return new PriceList();
        }

        $products = new Collection(array_column($request->productPriceRequests, 'product'));

        $productMap = [];
        foreach ($request->productPriceRequests as $productPriceRequest) {
            $productMap[$productPriceRequest->product->id] = $productPriceRequest;
        }

        // Get all price components and parse them as Price DTO.
        $prices = $this->getActivePrices($productMap);

        // The basePriceHandler creates a base price in case one doesn't exist. It should therefor always run first.
        // If no "public" prices are found, the basePriceHandler queries the prices table.
        $prices = $this->basePriceHandler->handle($prices);

        if ($prices->count() === 0) {
            return new PriceList();
        }

        // Based on the "public" and base prices fetched before, all calculations will be done.
        // We also fetch the products from the database to prevent lazy loading during expensive calculations.
        $prices = $this->prolongationPriceComponentHandler->handle($prices);
        $prices = $this->promotionPriceComponentHandler->handle($prices);
        $prices = $this->introDiscountPricePriceHandler->handle($prices, $request->customer);

        if ($request->customer !== null) {
            $prices = $this->microsoft365RemainingDurationPricesPriceHandler->handle($prices, $productMap, $request->customer);
            $prices = $this->productGroupDiscountPriceHandler->handle($prices, $productMap, $request->customer);
            $prices = $this->productDiscountPriceHandler->handle($prices, $request->customer);
        }

        $prices = $prices->map(fn (Price $price) => $this->calculateFinalPrice($price, $productMap[$price->productId], $request->requestingForOrder));
        $prices = $this->processQuantities($prices, $request->productPriceRequests, $request->requestingForOrder);
        $prices = $this->applyVouchers($prices, $vouchers);

        // Using the final price list and the pre-fetched product data, a complete price list is compiled.
        // All objects in this list are DTO's from this module.
        return $this->priceListFactory->fromProductsAndPrices($products, $prices);
    }

    /**
     *
     * @param array<int, ProductPriceRequest> $productMap
     *
     * @return Collection<int, Price>
     */
    private function getActivePrices(array $productMap): Collection
    {
        $productIds = array_unique(array_map(static fn (ProductPriceRequest $productPriceRequest): int => $productPriceRequest->product->id, $productMap));
        $productIdsString = implode(',', array_map(static fn ($id) => strval($id), $productIds));
        $prices = new Collection();

        $registrationAndProlongationPrices = DB::select(<<<SQL
            select distinct on (product_price_components.product_id, product_price_components.type, product_price_components.contract_period, product_price_components.billing_period)
                product_price_components.product_id,
                product_price_components.type,
                product_price_components.id,
                product_price_components.contract_period,
                product_price_components.billing_period,
                product_price_components.price,
                product_price_components.orderable,
                product_groups.uuid as product_group_uuid,
                product_periods.action_period,
                product_periods.action_period_price,
                product_periods.translation_key_id,
                product_periods.is_default,
                product_price_alternatives.price_alternatives
            from product_price_components
            join products on product_price_components.product_id = products.id
            join product_groups on products.product_group_id = product_groups.id
            left join product_periods on products.id = product_periods.product_id and product_price_components.contract_period = product_periods.contract_period and product_price_components.billing_period = product_periods.billing_period
            left join (
                select
                    product_id,
                    billing_period,
                    contract_period,
                    jsonb_object_agg(alternative_product_id, gross_price) as price_alternatives
                from product_price_alternatives
                group by product_id,
                    billing_period,
                    contract_period
            ) product_price_alternatives on product_price_alternatives.product_id = product_price_components.product_id
                and product_price_alternatives.billing_period = product_price_components.billing_period
                and product_price_alternatives.contract_period = product_price_components.contract_period
            where product_price_components.product_id in ({$productIdsString})
                and product_price_components.starts_at <= :currentDate
                and (product_price_components.expires_at is null or product_price_components.expires_at > :currentDate)
                and (product_price_components.type = :registrationPriceType or product_price_components.type = :prolongationPriceType)
            order by product_price_components.product_id, product_price_components.type, product_price_components.contract_period, product_price_components.billing_period, product_price_components.starts_at desc
            SQL, ['currentDate' => CarbonImmutable::now(), 'registrationPriceType' => PriceComponentType::REGISTRATION->value, 'prolongationPriceType' => PriceComponentType::PROLONGATION->value]);

        foreach ($registrationAndProlongationPrices as $registrationAndProlongationPrice) {
            // Let's try to map the translation key here. Yes, this is in theory an N+1 query.
            // With the low amount of prices with a translation this shouldn't be an issue.
            // As soon as we get rid of the product prices this can be rewritten.
            $translationKey = is_int($registrationAndProlongationPrice->translation_key_id) ? TranslationKey::find($registrationAndProlongationPrice->translation_key_id) : null;

            $price = new Price(
                type: ProductPriceType::from($registrationAndProlongationPrice->type),
                billingPeriod: $registrationAndProlongationPrice->billing_period,
                productId: $registrationAndProlongationPrice->product_id,
                productGroupUuid: $registrationAndProlongationPrice->product_group_uuid,
                regularPrice: $registrationAndProlongationPrice->price,
                contractPeriod: $registrationAndProlongationPrice->contract_period,
                orderable: $registrationAndProlongationPrice->orderable,
                is_default: is_bool($registrationAndProlongationPrice->is_default) && $registrationAndProlongationPrice->is_default,
                priceExplanation: $translationKey,
                actionPeriod: $registrationAndProlongationPrice->action_period,
                actionPeriodPrice: $registrationAndProlongationPrice->action_period_price,
                alternativeGrossPrices: $priceAlternatives ?? []
            );

            $prices->add($price);

            // Alternative prices should only be linked to a registration price component. Ignore any others.
            if ($registrationAndProlongationPrice->type !== PriceComponentType::REGISTRATION->value) {
                continue;
            }

            if ($registrationAndProlongationPrice->price_alternatives !== null && json_validate($registrationAndProlongationPrice->price_alternatives)) {
                /** @var array<int, int> $priceAlternatives */
                $priceAlternatives = json_decode($registrationAndProlongationPrice->price_alternatives, true, 512, JSON_THROW_ON_ERROR);
                $price->alternativeGrossPrices = $priceAlternatives;
            }
        }
        return $prices;
    }

    private function calculateFinalPrice(Price $price, ProductPriceRequest $request, bool $requestingForOrder): Price
    {
        $registrationPriceComponent = array_find(
            $price->possiblePriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent->type === PriceComponentType::REGISTRATION
        );

        if ($registrationPriceComponent instanceof RegistrationPriceComponent) {
            $price->calculatedPrice = $registrationPriceComponent->price;
            $price->appliedPriceComponents = [$registrationPriceComponent];
        }

        $price = match ($request::class) {
            RegistrationPriceRequest::class => $this->calculateFinalPriceForRegistration($price, $requestingForOrder),
            AddonRegistrationPriceRequest::class => $this->calculateFinalPriceForAddonRegistration($price, $request, $requestingForOrder),
            UpgradePriceRequest::class => $this->calculateFinalPriceForUpgrade($price, $request),
            ProlongationPriceRequest::class => $this->calculateFinalPriceForProlongation($price),
            default => throw new RuntimeException(),
        };

        $applyProrate = match ($request::class) {
            RegistrationPriceRequest::class,
            AddonRegistrationPriceRequest::class,
            UpgradePriceRequest::class => true,
            ProlongationPriceRequest::class => false,
        };

        $proRatePriceComponent = array_find(
            $price->possiblePriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent->type === PriceComponentType::PRO_RATE
        );

        if ($proRatePriceComponent instanceof ProRatePriceComponent && $applyProrate) {
            return $this->applyProrate($price, $proRatePriceComponent);
        }

        return $price;
    }

    private function applyProrate(Price $price, ProRatePriceComponent $proRatePriceComponent): Price
    {
        $price->regularPrice = $this->priceService->calculateProRate($proRatePriceComponent->amountPaid, $price->regularPrice, $proRatePriceComponent->until, $price->billingPeriod);
        $price->discountPrice = $price->discountPrice !== null ? $this->priceService->calculateProRate($proRatePriceComponent->amountPaid, $price->discountPrice, $proRatePriceComponent->until, $price->billingPeriod) : null;

        Assert::natural($price->calculatedPrice);

        $newPrice = $this->priceService->calculateProRate($proRatePriceComponent->amountPaid, $price->calculatedPrice, $proRatePriceComponent->until, $price->billingPeriod);
        $proRateAmount = $price->calculatedPrice - $newPrice;
        assert($proRateAmount >= 0);
        $proRatePriceComponent->fixedDiscount = $proRateAmount;
        $proRatePriceComponent->newPrice = $newPrice;
        $price->calculatedPrice = $newPrice;
        $proRatePriceComponent->appliedOrder = $this->getNextPriceComponentOrderNumber($price);
        $price->appliedPriceComponents[] = $proRatePriceComponent;

        return $price;
    }

    private function calculateFinalPriceForRegistration(Price $price, bool $requestingForOrder): Price
    {
        $amountAppliedPriceComponents = 1;

        $staffelPriceComponent = array_find(
            $price->possiblePriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent->type === PriceComponentType::REGISTRATION_STAFFEL
        );

        if ($staffelPriceComponent instanceof RegistrationStaffelPriceComponent) {
            $staffelPriceComponent->appliedOrder = ++$amountAppliedPriceComponents;
            $price->appliedPriceComponents[] = $staffelPriceComponent;
            $price->calculatedPrice = $staffelPriceComponent->newPrice;

            // This has nothing to do with priceComponents, and is done for backwards compatibility reasons. Eventually the
            // discount, introduction and promotion price fields will go, because with the provided context we have the
            // 'calculated_price', which is enough.
            $price->discountPrice = null;
            $price->regularPrice = $staffelPriceComponent->newPrice;

            return $price;
        }

        if ($requestingForOrder && $price->type === ProductPriceType::REGISTRATION) {
            $introductionPriceComponent = array_find(
                $price->possiblePriceComponents,
                fn (PriceComponent $priceComponent): bool => $priceComponent instanceof IntroductionPriceComponent && ($priceComponent->maxUsesPerCustomer === null || $priceComponent->remainingUses > 0)
            );

            if ($introductionPriceComponent instanceof IntroductionPriceComponent) {
                $introductionPriceComponent->appliedOrder = ++$amountAppliedPriceComponents;
                $introductionPriceComponent->remainingUses = $introductionPriceComponent->remainingUses === null ? null : max(0, $introductionPriceComponent->remainingUses - 1);
                $price->appliedPriceComponents[] = $introductionPriceComponent;
                $price->calculatedPrice = $introductionPriceComponent->newPrice;
                $price->actionPeriod = $introductionPriceComponent->firstMonthsDiscountPeriod;
                $price->actionPeriodPrice = $introductionPriceComponent->fixedPrice;

                return $price;
            }
        }

        $promotionPriceComponent = array_find(
            $price->possiblePriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent->type === PriceComponentType::PROMOTION
        );

        if ($promotionPriceComponent instanceof PromotionPriceComponent) {
            $promotionPriceComponent->appliedOrder = ++$amountAppliedPriceComponents;
            $price->appliedPriceComponents[] = $promotionPriceComponent;
            $price->calculatedPrice = $promotionPriceComponent->newPrice;

            return $price;
        }

        $groupPriceComponent = array_find(
            $price->possiblePriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent->type === PriceComponentType::PRODUCT_GROUP
        );

        if ($groupPriceComponent instanceof ProductGroupPriceComponent) {
            Assert::true($groupPriceComponent->percentageDiscount !== null && $groupPriceComponent->percentageDiscount >= 0);
            $price->calculatedPrice = $this->priceService->calculatePercentageDiscount($price->regularPrice, $groupPriceComponent->percentageDiscount);
            Assert::natural($price->calculatedPrice);
            $groupPriceComponent->newPrice = $price->calculatedPrice;
            $groupPriceComponent->appliedOrder = ++$amountAppliedPriceComponents;
            $price->appliedPriceComponents[] = $groupPriceComponent;

            // This has nothing to do with priceComponents, and is done for backwards compatibility reasons. Eventually the
            // discount price field will go, because with the provided context we have the 'calculated_price', which is enough.
            Assert::natural($price->calculatedPrice);
            $price->discountPrice = $price->calculatedPrice;

            return $price;
        }

        $price->calculatedPrice = $price->regularPrice;

        return $price;
    }

    private function calculateFinalPriceForProlongation(Price $price): Price
    {
        $amountAppliedPriceComponents = 1;

        $prolongationPriceComponent = array_find(
            $price->possiblePriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent->type === PriceComponentType::PROLONGATION
        );

        if ($prolongationPriceComponent instanceof ProlongationPriceComponent) {
            $price->appliedPriceComponents[] = $prolongationPriceComponent;
            $prolongationPriceComponent->appliedOrder = ++$amountAppliedPriceComponents;
            $price->calculatedPrice = $prolongationPriceComponent->newPrice;
            $price->regularPrice = $prolongationPriceComponent->newPrice;
        } else {
            $price->calculatedPrice = $price->regularPrice;
        }

        $staffelPriceComponent = array_find(
            $price->possiblePriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent->type === PriceComponentType::PROLONGATION_STAFFEL
        );

        if ($staffelPriceComponent instanceof ProlongationStaffelPriceComponent) {
            $staffelPriceComponent->appliedOrder = ++$amountAppliedPriceComponents;
            $price->appliedPriceComponents[] = $staffelPriceComponent;
            $price->calculatedPrice = $staffelPriceComponent->newPrice;

            // This has nothing to do with priceComponents, and is done for backwards compatibility reasons. Eventually the
            // discount, introduction and promotion price fields will go, because with the provided context we have the
            // 'calculated_price', which is enough.
            $price->discountPrice = null;
            $price->regularPrice = $staffelPriceComponent->newPrice;

            return $price;
        }

        $groupPriceComponent = array_find(
            $price->possiblePriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent->type === PriceComponentType::PRODUCT_GROUP
        );

        if ($groupPriceComponent instanceof ProductGroupPriceComponent) {
            Assert::true($groupPriceComponent->percentageDiscount !== null && $groupPriceComponent->percentageDiscount >= 0);
            $price->calculatedPrice = $this->priceService->calculatePercentageDiscount($price->regularPrice, $groupPriceComponent->percentageDiscount);
            Assert::natural($price->calculatedPrice);
            $groupPriceComponent->newPrice = $price->calculatedPrice;
            $groupPriceComponent->appliedOrder = ++$amountAppliedPriceComponents;
            $price->appliedPriceComponents[] = $groupPriceComponent;

            // This has nothing to do with priceComponents, and is done for backwards compatibility reasons. Eventually the
            // discount price field will go, because with the provided context we have the 'calculated_price', which is enough.
            Assert::natural($price->calculatedPrice);
            $price->discountPrice = $price->calculatedPrice;
        }

        return $price;
    }

    private function calculateFinalPriceForUpgrade(Price $price, UpgradePriceRequest $request): Price
    {
        $price = $this->calculateFinalPriceForProlongation($price);
        $price->possiblePriceComponents[] = new ProRatePriceComponent($request->endOfBillingCycle, $request->amountPaid);

        return $price;
    }

    private function calculateFinalPriceForAddonRegistration(Price $price, AddonRegistrationPriceRequest $request, bool $requestingForOrder): Price
    {
        $price = $this->calculateFinalPriceForRegistration($price, $requestingForOrder);
        $price->possiblePriceComponents[] = new ProRatePriceComponent($request->endOfBillingCycle, 0);

        return $price;
    }

    /**
     * @param Voucher[] $vouchers
     *
     * @return Voucher[]
     */
    private function filterUsableVouchers(array $vouchers, Customer $customer): array
    {
        return array_filter($vouchers, fn ($voucher) => $this->voucherService->checkVoucher($voucher, $customer));
    }

    /**
     * @param Collection<int, Price> $prices
     * @param ProductPriceRequest[]  $requests
     *
     * @return Collection<int, Price>
     */
    private function processQuantities(Collection $prices, array $requests, bool $requestingForOrder): Collection
    {
        $priceRequests = array_filter($requests, fn (ProductPriceRequest $request) => $request->quantity > 1);

        foreach ($priceRequests as $priceRequest) {
            $price = $prices
                ->where('productId', $priceRequest->product->id)
                ->where('contractPeriod', $priceRequest->contractPeriod)
                ->where('billingPeriod', $priceRequest->billingPeriod)
                ->where('type', ProductPriceType::REGISTRATION)
                ->firstOrFail();

            for ($i = 0; $i < $priceRequest->quantity - 1; $i++) {
                $priceCopy = new Price(
                    $price->type,
                    $price->billingPeriod,
                    $price->productId,
                    $price->productGroupUuid,
                    $price->regularPrice,
                    $price->contractPeriod,
                    $price->orderable,
                    $price->is_default,
                    appliedPriceComponents: [],
                    possiblePriceComponents: $price->possiblePriceComponents,
                );

                $priceCopy = $this->calculateFinalPrice($priceCopy, $priceRequest, $requestingForOrder);
                $prices->push($priceCopy);
            }
        }

        return $prices;
    }

    /**
     * @param Collection<int, Price> $prices
     * @param Voucher[]              $vouchers
     *
     * @return Collection<int, Price>
     */
    private function applyVouchers(Collection $prices, array $vouchers): Collection
    {
        // Make sure higher amount vouchers are applied first. If a price already has a voucher applied we skip the next,
        // effectively making sure that the highest applicable amount voucher is applied.
        usort($vouchers, fn (Voucher $a, Voucher $b) => $b->amount <=> $a->amount);
        $productVouchers = array_filter($vouchers, fn (Voucher $voucher) => $voucher->product_uuid !== null);
        $productGroupVouchers = array_filter($vouchers, fn (Voucher $voucher) => $voucher->product_uuid === null);

        foreach ($productVouchers as $productVoucher) {
            $price = array_find($prices->all(), fn (Price $price) =>
                $price->type === ProductPriceType::REGISTRATION &&
                $productVoucher->product !== null &&
                $productVoucher->product->id === $price->productId &&
                ($productVoucher->contract_period === null || $productVoucher->contract_period === $price->contractPeriod) &&
                ($productVoucher->billing_period === null || $productVoucher->billing_period === $price->billingPeriod));

            if ($price === null) {
                continue;
            }

            $voucherPriceComponent = array_find(
                $price->appliedPriceComponents,
                fn (PriceComponent $priceComponent): bool => $priceComponent->type === PriceComponentType::VOUCHER
            );

            if ($voucherPriceComponent !== null) {
                continue;
            }

            $groupPriceComponent = array_find(
                $price->appliedPriceComponents,
                fn (PriceComponent $priceComponent): bool => $priceComponent->type === PriceComponentType::PRODUCT_GROUP
            );

            if (! $productVoucher->apply_with_discount && $groupPriceComponent !== null) {
                continue;
            }

            Assert::natural($productVoucher->amount);
            Assert::natural($price->calculatedPrice);

            $oldPrice = $price->calculatedPrice;
            $newPrice = null;
            $fixedDiscount = null;
            $percentageDiscount = null;

            switch ($productVoucher->amount_type) {
                case VoucherAmountType::FIXED:
                    $newPrice = max($price->calculatedPrice - $productVoucher->amount, 0);
                    $fixedDiscount = $productVoucher->amount;
                    break;
                case VoucherAmountType::PERCENTAGE:
                    $newPrice = $this->priceService->calculatePercentageDiscount($price->calculatedPrice, $productVoucher->amount);
                    $percentageDiscount = $productVoucher->amount;
                    break;
                default:
                    throw new RuntimeException();
            }

            $newPrice = max($newPrice, 0);
            $appliedAmount = max($oldPrice - $newPrice, 0);
            $voucherPriceComponent = new VoucherPriceComponent($fixedDiscount, $percentageDiscount, $newPrice, $appliedAmount, $productVoucher, $this->getNextPriceComponentOrderNumber($price));
            $price->appliedPriceComponents[] = $voucherPriceComponent;
            $price->calculatedPrice = $newPrice;
        }

        foreach ($productGroupVouchers as $productGroupVoucher) {
            // A fixed amount voucher for a product group should be spreadable over multiple product prices.
            $remainingAmount = $productGroupVoucher->amount;
            $applicablePrices = array_filter($prices->all(), fn (Price $price) =>
                $price->type === ProductPriceType::REGISTRATION &&
                $productGroupVoucher->product_group_uuid === $price->productGroupUuid &&
                ($productGroupVoucher->contract_period === null || $productGroupVoucher->contract_period === $price->contractPeriod) &&
                ($productGroupVoucher->billing_period === null || $productGroupVoucher->billing_period === $price->billingPeriod));

            foreach ($applicablePrices as $applicablePrice) {
                $voucherPriceComponent = array_find(
                    $applicablePrice->appliedPriceComponents,
                    fn (PriceComponent $priceComponent): bool => $priceComponent->type === PriceComponentType::VOUCHER
                );

                if ($voucherPriceComponent !== null) {
                    continue;
                }

                $groupPriceComponent = array_find(
                    $applicablePrice->appliedPriceComponents,
                    fn (PriceComponent $priceComponent): bool => $priceComponent->type === PriceComponentType::PRODUCT_GROUP
                );

                if (! $productGroupVoucher->apply_with_discount && $groupPriceComponent !== null) {
                    continue;
                }

                Assert::natural($productGroupVoucher->amount);
                Assert::natural($applicablePrice->calculatedPrice);

                $oldPrice = $applicablePrice->calculatedPrice;
                $newPrice = null;
                $fixedDiscount = null;
                $percentageDiscount = null;

                switch ($productGroupVoucher->amount_type) {
                    case VoucherAmountType::FIXED:
                        $newPrice = max($applicablePrice->calculatedPrice - $remainingAmount, 0);
                        $fixedDiscount = $productGroupVoucher->amount;
                        break;
                    case VoucherAmountType::PERCENTAGE:
                        $newPrice = $this->priceService->calculatePercentageDiscount($applicablePrice->calculatedPrice, $productGroupVoucher->amount);
                        $percentageDiscount = $productGroupVoucher->amount;
                        break;
                    default:
                        throw new RuntimeException();
                }

                $newPrice = max($newPrice, 0);
                $appliedAmount = max($oldPrice - $newPrice, 0);
                $remainingAmount -= $appliedAmount;

                $voucherPriceComponent = new VoucherPriceComponent($fixedDiscount, $percentageDiscount, $newPrice, $appliedAmount, $productGroupVoucher, $this->getNextPriceComponentOrderNumber($applicablePrice));
                $applicablePrice->appliedPriceComponents[] = $voucherPriceComponent;
                $applicablePrice->calculatedPrice = $newPrice;
            }
        }

        return $prices;
    }

    /** @return positive-int */
    private function getNextPriceComponentOrderNumber(Price $price): int
    {
        $appliedPriceComponentOrders = array_column($price->appliedPriceComponents, 'appliedOrder');

        return $appliedPriceComponentOrders !== [] ? (int) max($appliedPriceComponentOrders) + 1 : 1;
    }
}
