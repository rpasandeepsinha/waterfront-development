<?php

declare(strict_types=1);

namespace Waterfront\Domain\RetentionToolkit\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\ItemNotFoundException;
use LogicException;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\DTO\PriceRequest;
use Waterfront\Domain\Products\DTO\ProlongationPriceRequest;
use Waterfront\Domain\Products\Exceptions\PriceResolvingException;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Domain\RetentionToolkit\DTO\RetentionCreditPreviewDTO;
use Waterfront\Domain\RetentionToolkit\DTO\RetentionOfferEligibilityResultDTO;
use Waterfront\Domain\RetentionToolkit\DTO\RetentionOfferItemCalculationDTO;
use Waterfront\Domain\RetentionToolkit\DTO\RetentionOfferItemDTO;
use Waterfront\Domain\RetentionToolkit\DTO\RetentionOfferPriceDTO;
use Waterfront\Domain\RetentionToolkit\DTO\RetentionOfferRequestDTO;
use Waterfront\Domain\RetentionToolkit\Enums\CustomerType;
use Waterfront\Domain\RetentionToolkit\Enums\RetentionOfferCalculationStatus;
use Waterfront\Domain\RetentionToolkit\Enums\RetentionOfferEligibilityCode;
use Waterfront\Domain\RetentionToolkit\Enums\SelectedAction;
use Waterfront\Domain\RetentionToolkit\Exceptions\InvalidRetentionEffectiveDateException;
use Waterfront\Domain\RetentionToolkit\Factories\RetentionOfferItemCalculationFactory;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\CreditSubscriptionService;
use Webmozart\Assert\Assert;

readonly class RetentionOfferPriceCalculator
{
    private const int ANNUAL_COMPARISON_PERIOD = 12;

    private const int TK_OPTION_ONE_PROMOTION_MONTHS = 3;

    private const int TK_OPTION_ONE_PROMOTION_MONTHLY_PRICE = 99;

    public function __construct(
        private RetentionOfferEligibilityService $eligibilityService,
        private PriceResolver $priceResolver,
        private RetentionEffectiveDateCalculator $effectiveDateCalculator,
        private CreditSubscriptionService $creditSubscriptionService,
        private RetentionOfferItemCalculationFactory $itemCalculationFactory,
    ) {
    }

    public function calculateItem(
        RetentionOfferRequestDTO $request,
        RetentionOfferItemDTO $item,
    ): RetentionOfferItemCalculationDTO {
        $subscription = $item->subscription;

        if ($subscription->customer_id !== $request->customer->id) {
            return $this->itemCalculationFactory->createFailedResult(
                item: $item,
                status: RetentionOfferCalculationStatus::INELIGIBLE,
                reason: 'The subscription is not available in the current customer context.',
                price: null,
                subscription: null,
            );
        }

        $price = $this->calculate(
            subscription: $subscription,
            selectedAction: $item->selectedAction,
            contractPeriod: $item->contractPeriod,
            billingPeriod: $item->billingPeriod,
            targetProduct: $item->targetProduct,
        );

        $calculationStatus = match ($price->eligibility->code) {
            RetentionOfferEligibilityCode::ELIGIBLE,
            RetentionOfferEligibilityCode::NO_PRICE_REQUIRED => RetentionOfferCalculationStatus::CALCULATED,
            RetentionOfferEligibilityCode::MISSING_PRICE => RetentionOfferCalculationStatus::MANUAL,
            RetentionOfferEligibilityCode::OPEN_MUTATION => RetentionOfferCalculationStatus::CONFLICT,
            RetentionOfferEligibilityCode::INELIGIBLE_PRODUCT,
            RetentionOfferEligibilityCode::INACTIVE_SUBSCRIPTION,
            RetentionOfferEligibilityCode::INVALID_CONTRACT_PERIOD,
            RetentionOfferEligibilityCode::INVALID_BILLING_PERIOD => RetentionOfferCalculationStatus::INELIGIBLE,
        };

        if ($calculationStatus !== RetentionOfferCalculationStatus::CALCULATED) {
            return $this->itemCalculationFactory->createFailedResult(
                item: $item,
                status: $calculationStatus,
                reason: $price->eligibility->reason,
                price: $price,
                subscription: $subscription,
            );
        }

        if ($item->selectedAction === SelectedAction::BZ) {
            return $this->itemCalculationFactory
                ->createRetentionWithoutOfferResult(
                    item: $item,
                    price: $price,
                );
        }

        $isCancellation = $item->selectedAction === SelectedAction::RF;
        $cancelReason = $item->cancelReason;

        if ($isCancellation && $cancelReason === null) {
            return $this->itemCalculationFactory->createFailedResult(
                item: $item,
                status: RetentionOfferCalculationStatus::INELIGIBLE,
                reason: 'RF requires a cancellation reason.',
                price: $price,
                subscription: $subscription,
            );
        }

        try {
            $effectiveDate = $this->effectiveDateCalculator->calculate(
                subscription: $subscription,
                customerType: $request->customerType,
                selectedAction: $item->selectedAction,
                executionDate: $item->executionDate,
            );
        } catch (InvalidRetentionEffectiveDateException $exception) {
            return $this->itemCalculationFactory->createFailedResult(
                item: $item,
                status: RetentionOfferCalculationStatus::INELIGIBLE,
                reason: $exception->getMessage(),
                price: $price,
                subscription: $subscription,
            );
        }

        if ($isCancellation) {
            return $this->calculateCancellationResult(
                item: $item,
                subscription: $subscription,
                price: $price,
                effectiveDate: $effectiveDate,
                cancelReason: $cancelReason,
            );
        }

        return $this->calculateRetentionOfferResult(
            customerType: $request->customerType,
            item: $item,
            subscription: $subscription,
            price: $price,
            effectiveDate: $effectiveDate,
        );
    }

    private function calculate(
        Subscription $subscription,
        SelectedAction $selectedAction,
        int $contractPeriod,
        int $billingPeriod,
        ?Product $targetProduct,
    ): RetentionOfferPriceDTO {
        $eligibility = $this->eligibilityService->determineEligibility(
            subscription: $subscription,
            selectedAction: $selectedAction,
            contractPeriod: $contractPeriod,
            billingPeriod: $billingPeriod,
            targetProduct: $targetProduct,
        );

        if ($eligibility->code !== RetentionOfferEligibilityCode::ELIGIBLE) {
            return $this->withoutPrice($eligibility);
        }

        $pricingProduct = $subscription->product;
        if ($selectedAction->isDowngrade()) {
            Assert::notNull(
                $targetProduct,
                'The target product should already have been validated.',
            );

            $targetProduct->loadMissing('productGroup');
            $pricingProduct = $targetProduct;
        }

        $subscription->loadMissing('customer');

        try {
            $priceList = $this->priceResolver->getPriceList(new PriceRequest(
                productPriceRequests: [
                    new ProlongationPriceRequest(
                        product: $pricingProduct,
                        contractPeriod: $contractPeriod,
                        billingPeriod: $billingPeriod,
                    ),
                ],
                customer: $subscription->customer,
            ));
            $price = $priceList->getProductPrice(
                productSlug: $pricingProduct->slug,
                contractPeriod: $contractPeriod,
                billingPeriod: $billingPeriod,
            );
            $comparisonPrice = match ($selectedAction) {
                SelectedAction::DG_OPTION_1D => $priceList->getProductPrice(
                    productSlug: $pricingProduct->slug,
                    contractPeriod: self::ANNUAL_COMPARISON_PERIOD,
                    billingPeriod: self::ANNUAL_COMPARISON_PERIOD,
                ),
                default => null,
            };
        } catch (ItemNotFoundException | PriceResolvingException) {
            return $this->withoutPrice(new RetentionOfferEligibilityResultDTO(
                code: RetentionOfferEligibilityCode::MISSING_PRICE,
                reason: 'No price could be resolved for the selected product and periods.',
            ));
        }

        if ($selectedAction === SelectedAction::DG_OPTION_1D) {
            return $this->calculateDgOptionOneDPrice(
                eligibility: $eligibility,
                price: $price,
                comparisonPrice: $comparisonPrice,
                contractPeriod: $contractPeriod,
            );
        }

        $calculatedPrice = $price->calculatedPrice;
        Assert::natural($calculatedPrice);

        $normalNetPrice = $selectedAction === SelectedAction::TK_OPTION_1
            ? $price->regularPrice
            : $calculatedPrice;

        /*
         * DG Option 1A switches to a configured downgrade product at its
         * resolved renewal price without an additional retention discount.
         */
        if ($selectedAction === SelectedAction::DG_OPTION_1A) {
            return new RetentionOfferPriceDTO(
                eligibility: $eligibility,
                grossPrice: $price->regularPrice,
                normalNetPrice: $normalNetPrice,
                offerNetPrice: $normalNetPrice,
                discountAmount: 0,
            );
        }

        if ($selectedAction === SelectedAction::TK_OPTION_1) {
            return $this->calculateTkOptionOnePrice(
                eligibility: $eligibility,
                price: $price,
            );
        }

        return $this->calculateFixedPercentagePrice(
            selectedAction: $selectedAction,
            eligibility: $eligibility,
            price: $price,
            normalNetPrice: $normalNetPrice,
        );
    }

    /*
     * DG Option 1D applies a discounted 24 or 36 month target product
     * price and compares it with the equivalent annual renewal cost.
     */
    private function calculateDgOptionOneDPrice(
        RetentionOfferEligibilityResultDTO $eligibility,
        Price $price,
        Price $comparisonPrice,
        int $contractPeriod,
    ): RetentionOfferPriceDTO {
        $calculatedPrice = $price->calculatedPrice;
        Assert::natural($calculatedPrice);

        $comparisonCalculatedPrice = $comparisonPrice->calculatedPrice;
        Assert::natural($comparisonCalculatedPrice);

        $termMultiplier = intdiv(
            $contractPeriod,
            self::ANNUAL_COMPARISON_PERIOD,
        );

        $grossPrice = $comparisonPrice->regularPrice * $termMultiplier;
        $normalNetPrice = $comparisonCalculatedPrice * $termMultiplier;

        if ($calculatedPrice >= $normalNetPrice) {
            return $this->withoutPrice(new RetentionOfferEligibilityResultDTO(
                code: RetentionOfferEligibilityCode::MISSING_PRICE,
                reason: 'The multiyear price does not provide a discount.',
            ));
        }

        $discountAmount = $normalNetPrice - $calculatedPrice;
        Assert::natural($grossPrice);
        Assert::natural($discountAmount);

        return new RetentionOfferPriceDTO(
            eligibility: $eligibility,
            grossPrice: $grossPrice,
            normalNetPrice: $normalNetPrice,
            offerNetPrice: $calculatedPrice,
            discountAmount: $discountAmount,
        );
    }

    /*
     * TK Option 1 gives eligible hosting subscriptions three months at
     * €0.99 and derives the remaining months from the selected term price.
     */
    private function calculateTkOptionOnePrice(
        RetentionOfferEligibilityResultDTO $eligibility,
        Price $price,
    ): RetentionOfferPriceDTO {
        $normalNetPrice = $price->regularPrice;

        $offerNetPrice = $this->calculateTkOptionOneOfferNetPrice(
            termRegularPrice: $normalNetPrice,
            billingPeriod: $price->billingPeriod,
        );

        if ($offerNetPrice >= $normalNetPrice) {
            return $this->withoutPrice(new RetentionOfferEligibilityResultDTO(
                code: RetentionOfferEligibilityCode::MISSING_PRICE,
                reason: 'The calculated TK Option 1 price does not provide a valid discount.',
            ));
        }

        $discountAmount = $normalNetPrice - $offerNetPrice;
        Assert::natural($offerNetPrice);
        Assert::natural($discountAmount);

        return new RetentionOfferPriceDTO(
            eligibility: $eligibility,
            grossPrice: $price->regularPrice,
            normalNetPrice: $normalNetPrice,
            offerNetPrice: $offerNetPrice,
            discountAmount: $discountAmount,
        );
    }

    /**
     * Fixed-percentage offers:
     * - DM Option 1 gives eligible .nl and .com domains a 25% discount.
     * - TK Option 2 gives active subscriptions a 15% discount.
     * - TK Option 3 gives eligible hosting subscriptions a 20% discount.
     * - TK Option 5 gives eligible domain or hosting subscriptions a 25% discount.
     * - TK Option 6 gives eligible domain or hosting subscriptions a 50% discount.
     *
     * @param non-negative-int $normalNetPrice
     */
    private function calculateFixedPercentagePrice(
        SelectedAction $selectedAction,
        RetentionOfferEligibilityResultDTO $eligibility,
        Price $price,
        int $normalNetPrice,
    ): RetentionOfferPriceDTO {
        $priceFactor = match ($selectedAction) {
            SelectedAction::DM_OPTION_1,
            SelectedAction::TK_OPTION_5 => 0.75,
            SelectedAction::TK_OPTION_2 => 0.85,
            SelectedAction::TK_OPTION_3 => 0.80,
            SelectedAction::TK_OPTION_6 => 0.50,
            default => throw new LogicException('Action does not have a calculated retention price.'),
        };
        $offerNetPrice = (int) round($normalNetPrice * $priceFactor);

        if ($offerNetPrice >= $normalNetPrice) {
            return $this->withoutPrice(new RetentionOfferEligibilityResultDTO(
                code: RetentionOfferEligibilityCode::MISSING_PRICE,
                reason: 'The calculated retention price does not provide a valid discount.',
            ));
        }

        $discountAmount = $normalNetPrice - $offerNetPrice;
        Assert::natural($offerNetPrice);
        Assert::natural($discountAmount);

        return new RetentionOfferPriceDTO(
            eligibility: $eligibility,
            grossPrice: $price->regularPrice,
            normalNetPrice: $normalNetPrice,
            offerNetPrice: $offerNetPrice,
            discountAmount: $discountAmount,
        );
    }

    private function withoutPrice(RetentionOfferEligibilityResultDTO $eligibility): RetentionOfferPriceDTO
    {
        return new RetentionOfferPriceDTO(
            eligibility: $eligibility,
            grossPrice: null,
            normalNetPrice: null,
            offerNetPrice: null,
            discountAmount: null,
        );
    }

    private function calculateTkOptionOneOfferNetPrice(
        int $termRegularPrice,
        int $billingPeriod,
    ): int {
        $regularMonthlyPrice = $termRegularPrice / $billingPeriod;
        $regularMonths =
            $billingPeriod - self::TK_OPTION_ONE_PROMOTION_MONTHS;

        return (int) round(
            ($regularMonthlyPrice * $regularMonths)
            + (
                self::TK_OPTION_ONE_PROMOTION_MONTHS
                * self::TK_OPTION_ONE_PROMOTION_MONTHLY_PRICE
            ),
        );
    }

    private function calculateCancellationResult(
        RetentionOfferItemDTO $item,
        Subscription $subscription,
        RetentionOfferPriceDTO $price,
        CarbonImmutable $effectiveDate,
        SubscriptionCancelReason $cancelReason,
    ): RetentionOfferItemCalculationDTO {
        $creditTotal = 0;

        if ($cancelReason->allowedToCredit()) {
            $creditTotal = $this->calculateCreditPreview(
                subscription: $subscription,
                effectiveDate: $effectiveDate,
                cancelReason: $cancelReason,
                includeCustomerCredit: true,
            )->creditTotal;
        }

        return $this->itemCalculationFactory->createCancellationResult(
            item: $item,
            price: $price,
            effectiveDate: $effectiveDate,
            creditTotal: $creditTotal,
        );
    }

    private function calculateRetentionOfferResult(
        CustomerType $customerType,
        RetentionOfferItemDTO $item,
        Subscription $subscription,
        RetentionOfferPriceDTO $price,
        CarbonImmutable $effectiveDate,
    ): RetentionOfferItemCalculationDTO {
        $creditPreview = $this->calculateCreditPreview(
            subscription: $subscription,
            effectiveDate: $effectiveDate,
            cancelReason: SubscriptionCancelReason::REASON_CANCELLATION,
            includeCustomerCredit: $customerType !== CustomerType::BUSINESS,
        );

        $offerNetPrice = $price->offerNetPrice;
        Assert::notNull($offerNetPrice);

        return $this->itemCalculationFactory->createRetentionOfferResult(
            item: $item,
            price: $price,
            effectiveDate: $effectiveDate,
            offerNetPrice: $offerNetPrice,
            creditTotal: $creditPreview->creditTotal,
            replacesFutureInvoice: $creditPreview->replacesFutureInvoice,
        );
    }

    private function calculateCreditPreview(
        Subscription $subscription,
        CarbonImmutable $effectiveDate,
        SubscriptionCancelReason $cancelReason,
        bool $includeCustomerCredit,
    ): RetentionCreditPreviewDTO {
        $creditBatch = $this->creditSubscriptionService
            ->getInvoiceLinesToCreditBatchFromDate(
                subscription: $subscription,
                creditFromDate: $effectiveDate,
                cancelReason: $cancelReason,
            );

        $creditTotal = 0;
        $replacesFutureInvoice = false;

        foreach ($creditBatch->getInvoicesToCredit() as $invoiceToCredit) {
            if ($includeCustomerCredit) {
                $creditTotal += $invoiceToCredit->getAmountToCredit();
            }

            $invoice = $invoiceToCredit->getInvoice();

            if ($invoice->start_date->greaterThan(CarbonImmutable::today())) {
                $replacesFutureInvoice = true;
            }
        }

        return new RetentionCreditPreviewDTO(
            creditTotal: $creditTotal,
            replacesFutureInvoice: $replacesFutureInvoice,
        );
    }
}
