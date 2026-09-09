<?php

declare(strict_types=1);

namespace Waterfront\Domain\RetentionToolkit\Services;

use Waterfront\Domain\Products\Enums\ProductSlug;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\RetentionToolkit\DTO\RetentionOfferEligibilityResultDTO;
use Waterfront\Domain\RetentionToolkit\Enums\RetentionOfferEligibilityCode;
use Waterfront\Domain\RetentionToolkit\Enums\SelectedAction;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\ProductAllowedChangeRepository;

class RetentionOfferActionEligibilityService
{
    private const int DG_OPTION_1A_CONTRACT_PERIOD = 12;

    private const array DG_OPTION_1D_CONTRACT_PERIODS = [24, 36];

    private const array TK_OPTION_1_PERIODS = [12, 24, 36];

    public function __construct(
        private readonly ProductAllowedChangeRepository $productAllowedChangeRepository,
    ) {
    }

    public function determineDmOptionOneEligibility(Subscription $subscription): RetentionOfferEligibilityResultDTO
    {
        if (
            ! $subscription->product->isDomainProduct()
            || ! in_array(
                $subscription->product->slug,
                [
                    ProductSlug::EXTENSION_NL->value,
                    ProductSlug::EXTENSION_COM->value,
                ],
                true,
            )
        ) {
            return new RetentionOfferEligibilityResultDTO(
                code: RetentionOfferEligibilityCode::INELIGIBLE_PRODUCT,
                reason: sprintf(
                    'The subscription product is not eligible for %s.',
                    SelectedAction::DM_OPTION_1->value,
                ),
            );
        }

        return new RetentionOfferEligibilityResultDTO(
            code: RetentionOfferEligibilityCode::ELIGIBLE,
            reason: null,
        );
    }

    public function determineDgOptionOneAEligibility(
        Subscription $subscription,
        int $contractPeriod,
        int $billingPeriod,
        ?Product $targetProduct,
    ): RetentionOfferEligibilityResultDTO {
        if ($contractPeriod !== self::DG_OPTION_1A_CONTRACT_PERIOD) {
            return new RetentionOfferEligibilityResultDTO(
                code: RetentionOfferEligibilityCode::INVALID_CONTRACT_PERIOD,
                reason: sprintf(
                    'The selected contract period is not valid for %s.',
                    SelectedAction::DG_OPTION_1A->value,
                ),
            );
        }

        if (
            $billingPeriod !== $subscription->billing_period
            || $billingPeriod > $contractPeriod
        ) {
            return new RetentionOfferEligibilityResultDTO(
                code: RetentionOfferEligibilityCode::INVALID_BILLING_PERIOD,
                reason: sprintf(
                    'The selected billing period is not valid for %s.',
                    SelectedAction::DG_OPTION_1A->value,
                ),
            );
        }

        return $this->determineDowngradeEligibility(
            subscription: $subscription,
            selectedAction: SelectedAction::DG_OPTION_1A,
            targetProduct: $targetProduct,
        );
    }

    public function determineDgOptionOneDEligibility(
        Subscription $subscription,
        int $contractPeriod,
        int $billingPeriod,
        ?Product $targetProduct,
    ): RetentionOfferEligibilityResultDTO {
        if (! in_array($contractPeriod, self::DG_OPTION_1D_CONTRACT_PERIODS, true)) {
            return new RetentionOfferEligibilityResultDTO(
                code: RetentionOfferEligibilityCode::INVALID_CONTRACT_PERIOD,
                reason: sprintf(
                    'The selected contract period is not valid for %s.',
                    SelectedAction::DG_OPTION_1D->value,
                ),
            );
        }

        if ($billingPeriod !== $contractPeriod) {
            return new RetentionOfferEligibilityResultDTO(
                code: RetentionOfferEligibilityCode::INVALID_BILLING_PERIOD,
                reason: sprintf(
                    'The selected billing period is not valid for %s.',
                    SelectedAction::DG_OPTION_1D->value,
                ),
            );
        }

        return $this->determineDowngradeEligibility(
            subscription: $subscription,
            selectedAction: SelectedAction::DG_OPTION_1D,
            targetProduct: $targetProduct,
        );
    }

    public function determineTkOptionOneEligibility(Subscription $subscription): RetentionOfferEligibilityResultDTO
    {
        if (! $subscription->product->isHostingProduct()) {
            return new RetentionOfferEligibilityResultDTO(
                code: RetentionOfferEligibilityCode::INELIGIBLE_PRODUCT,
                reason: sprintf(
                    'The subscription product is not eligible for %s.',
                    SelectedAction::TK_OPTION_1->value,
                ),
            );
        }

        if (
            $subscription->billing_period !== $subscription->contract_period
            || ! in_array(
                $subscription->billing_period,
                self::TK_OPTION_1_PERIODS,
                true,
            )
        ) {
            return new RetentionOfferEligibilityResultDTO(
                code: RetentionOfferEligibilityCode::INVALID_BILLING_PERIOD,
                reason: 'TK Option 1 requires matching 12, 24 or 36 month billing and contract periods.',
            );
        }

        return new RetentionOfferEligibilityResultDTO(
            code: RetentionOfferEligibilityCode::ELIGIBLE,
            reason: null,
        );
    }

    public function determineHostingEligibility(
        Subscription $subscription,
        SelectedAction $selectedAction,
    ): RetentionOfferEligibilityResultDTO {
        if (! $subscription->product->isHostingProduct()) {
            return new RetentionOfferEligibilityResultDTO(
                code: RetentionOfferEligibilityCode::INELIGIBLE_PRODUCT,
                reason: sprintf(
                    'The subscription product is not eligible for %s.',
                    $selectedAction->value,
                ),
            );
        }

        return new RetentionOfferEligibilityResultDTO(
            code: RetentionOfferEligibilityCode::ELIGIBLE,
            reason: null,
        );
    }

    public function determineDomainOrHostingEligibility(
        Subscription $subscription,
        SelectedAction $selectedAction,
    ): RetentionOfferEligibilityResultDTO {
        if (
            ! $subscription->product->isDomainProduct()
            && ! $subscription->product->isHostingProduct()
        ) {
            return new RetentionOfferEligibilityResultDTO(
                code: RetentionOfferEligibilityCode::INELIGIBLE_PRODUCT,
                reason: sprintf(
                    'The subscription product is not eligible for %s.',
                    $selectedAction->value,
                ),
            );
        }

        return new RetentionOfferEligibilityResultDTO(
            code: RetentionOfferEligibilityCode::ELIGIBLE,
            reason: null,
        );
    }

    private function determineDowngradeEligibility(
        Subscription $subscription,
        SelectedAction $selectedAction,
        ?Product $targetProduct,
    ): RetentionOfferEligibilityResultDTO {
        if ($targetProduct === null) {
            return new RetentionOfferEligibilityResultDTO(
                code: RetentionOfferEligibilityCode::INELIGIBLE_PRODUCT,
                reason: sprintf(
                    'Retention action %s requires a target product.',
                    $selectedAction->value,
                ),
            );
        }

        if (
            ! $this->productAllowedChangeRepository->isProductChangeAllowed(
                changeType: ProductChangeType::DOWNGRADE,
                fromProduct: $subscription->product,
                toProduct: $targetProduct,
            )
        ) {
            return new RetentionOfferEligibilityResultDTO(
                code: RetentionOfferEligibilityCode::INELIGIBLE_PRODUCT,
                reason: 'The selected product is not a configured downgrade.',
            );
        }

        return new RetentionOfferEligibilityResultDTO(
            code: RetentionOfferEligibilityCode::ELIGIBLE,
            reason: null,
        );
    }
}
