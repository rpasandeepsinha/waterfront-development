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

    public function determineDowngradeEligibility(
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

        if (! $this->productAllowedChangeRepository->isProductChangeAllowed(
            changeType: ProductChangeType::DOWNGRADE,
            fromProduct: $subscription->product,
            toProduct: $targetProduct,
        )) {
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
        if (! $subscription->product->isDomainProduct() && ! $subscription->product->isHostingProduct()) {
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
}
