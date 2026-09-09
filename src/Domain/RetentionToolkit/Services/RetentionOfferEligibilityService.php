<?php

declare(strict_types=1);

namespace Waterfront\Domain\RetentionToolkit\Services;

use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\RetentionToolkit\DTO\RetentionOfferEligibilityResultDTO;
use Waterfront\Domain\RetentionToolkit\Enums\RetentionOfferEligibilityCode;
use Waterfront\Domain\RetentionToolkit\Enums\SelectedAction;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionMutationRepository;

class RetentionOfferEligibilityService
{
    public function __construct(
        private readonly SubscriptionMutationRepository $subscriptionMutationRepository,
        private readonly RetentionOfferActionEligibilityService $retentionOfferActionEligibilityService,
    ) {
    }

    public function determineEligibility(
        Subscription $subscription,
        SelectedAction $selectedAction,
        int $contractPeriod,
        int $billingPeriod,
        ?Product $targetProduct,
    ): RetentionOfferEligibilityResultDTO {
        if ($subscription->administrative_status !== AdministrativeStatus::ACTIVE->value) {
            return new RetentionOfferEligibilityResultDTO(
                code: RetentionOfferEligibilityCode::INACTIVE_SUBSCRIPTION,
                reason: sprintf(
                    'Retention action %s requires an active subscription.',
                    $selectedAction->value,
                ),
            );
        }

        if (
            $selectedAction === SelectedAction::RF
            || $selectedAction === SelectedAction::BZ
        ) {
            return new RetentionOfferEligibilityResultDTO(
                code: RetentionOfferEligibilityCode::NO_PRICE_REQUIRED,
                reason: sprintf(
                    'Retention action %s does not require a price.',
                    $selectedAction->value,
                ),
            );
        }

        if (
            $this->subscriptionMutationRepository
                ->findOpenMutation($subscription) !== null
        ) {
            return new RetentionOfferEligibilityResultDTO(
                code: RetentionOfferEligibilityCode::OPEN_MUTATION,
                reason: 'The subscription has an open mutation that must be reviewed first.',
            );
        }

        $isDowngrade = in_array(
            $selectedAction,
            [
                SelectedAction::DG_OPTION_1A,
                SelectedAction::DG_OPTION_1D,
            ],
            true,
        );

        if (! $isDowngrade && $contractPeriod !== $subscription->contract_period) {
            return new RetentionOfferEligibilityResultDTO(
                code: RetentionOfferEligibilityCode::INVALID_CONTRACT_PERIOD,
                reason: sprintf(
                    'Retention action %s must use the subscription\'s current contract period.',
                    $selectedAction->value,
                ),
            );
        }

        if (! $isDowngrade && $billingPeriod !== $subscription->billing_period) {
            return new RetentionOfferEligibilityResultDTO(
                code: RetentionOfferEligibilityCode::INVALID_BILLING_PERIOD,
                reason: sprintf(
                    'Retention action %s must use the subscription\'s current billing period.',
                    $selectedAction->value,
                ),
            );
        }

        $subscription->loadMissing('product.productGroup');

        return match ($selectedAction) {
            SelectedAction::DM_OPTION_1 => $this->retentionOfferActionEligibilityService
                ->determineDmOptionOneEligibility($subscription),
            SelectedAction::DG_OPTION_1A => $this->retentionOfferActionEligibilityService
                ->determineDgOptionOneAEligibility(
                    subscription: $subscription,
                    contractPeriod: $contractPeriod,
                    billingPeriod: $billingPeriod,
                    targetProduct: $targetProduct,
                ),
            SelectedAction::DG_OPTION_1D => $this->retentionOfferActionEligibilityService
                ->determineDgOptionOneDEligibility(
                    subscription: $subscription,
                    contractPeriod: $contractPeriod,
                    billingPeriod: $billingPeriod,
                    targetProduct: $targetProduct,
                ),
            SelectedAction::TK_OPTION_1 => $this->retentionOfferActionEligibilityService
                ->determineTkOptionOneEligibility($subscription),
            SelectedAction::TK_OPTION_2 => new RetentionOfferEligibilityResultDTO(
                code: RetentionOfferEligibilityCode::ELIGIBLE,
                reason: null,
            ),
            SelectedAction::TK_OPTION_3 => $this->retentionOfferActionEligibilityService
                ->determineHostingEligibility(
                    subscription: $subscription,
                    selectedAction: $selectedAction,
                ),
            SelectedAction::TK_OPTION_5,
            SelectedAction::TK_OPTION_6 => $this->retentionOfferActionEligibilityService
                ->determineDomainOrHostingEligibility(
                    subscription: $subscription,
                    selectedAction: $selectedAction,
                ),
        };
    }
}
