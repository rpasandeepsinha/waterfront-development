<?php

declare(strict_types=1);

namespace Waterfront\Domain\RetentionToolkit\Factories;

use Carbon\CarbonImmutable;
use Waterfront\Domain\RetentionToolkit\DTO\RetentionOfferItemCalculationDTO;
use Waterfront\Domain\RetentionToolkit\DTO\RetentionOfferItemDTO;
use Waterfront\Domain\RetentionToolkit\DTO\RetentionOfferPriceDTO;
use Waterfront\Domain\RetentionToolkit\Enums\ExecutionDate;
use Waterfront\Domain\RetentionToolkit\Enums\RetentionOfferCalculationStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class RetentionOfferItemCalculationFactory
{
    public function createFailedResult(
        RetentionOfferItemDTO $item,
        RetentionOfferCalculationStatus $status,
        ?string $reason,
        ?RetentionOfferPriceDTO $price,
        ?Subscription $subscription,
    ): RetentionOfferItemCalculationDTO {
        return new RetentionOfferItemCalculationDTO(
            subscription: $item->subscription,
            selectedAction: $item->selectedAction,
            status: $status,
            reason: $reason,
            price: $price,
            effectiveDate: null,
            oldContractStartDate: $subscription?->start_date,
            oldContractEndDate: $subscription?->end_date,
            newContractStartDate: null,
            newContractEndDate: null,
            cancellationDate: null,
            creditTotal: null,
            payableAfterCredits: null,
            requiresNewInvoice: false,
            replacesFutureInvoice: false,
        );
    }

    public function createRetentionWithoutOfferResult(
        RetentionOfferItemDTO $item,
        RetentionOfferPriceDTO $price,
    ): RetentionOfferItemCalculationDTO {
        $subscription = $item->subscription;

        return new RetentionOfferItemCalculationDTO(
            subscription: $subscription,
            selectedAction: $item->selectedAction,
            status: RetentionOfferCalculationStatus::CALCULATED,
            reason: null,
            price: $price,
            effectiveDate: null,
            oldContractStartDate: $subscription->start_date,
            oldContractEndDate: $subscription->end_date,
            newContractStartDate: null,
            newContractEndDate: null,
            cancellationDate: null,
            creditTotal: 0,
            payableAfterCredits: null,
            requiresNewInvoice: false,
            replacesFutureInvoice: false,
        );
    }

    public function createCancellationResult(
        RetentionOfferItemDTO $item,
        RetentionOfferPriceDTO $price,
        CarbonImmutable $effectiveDate,
        int $creditTotal,
    ): RetentionOfferItemCalculationDTO {
        $subscription = $item->subscription;

        return new RetentionOfferItemCalculationDTO(
            subscription: $subscription,
            selectedAction: $item->selectedAction,
            status: RetentionOfferCalculationStatus::CALCULATED,
            reason: null,
            price: $price,
            effectiveDate: $effectiveDate,
            oldContractStartDate: $subscription->start_date,
            oldContractEndDate: $subscription->end_date,
            newContractStartDate: null,
            newContractEndDate: null,
            cancellationDate: $effectiveDate,
            creditTotal: $creditTotal,
            payableAfterCredits: null,
            requiresNewInvoice: false,
            replacesFutureInvoice: false,
        );
    }

    public function createRetentionOfferResult(
        RetentionOfferItemDTO $item,
        RetentionOfferPriceDTO $price,
        CarbonImmutable $effectiveDate,
        int $offerNetPrice,
        int $creditTotal,
        bool $replacesFutureInvoice,
    ): RetentionOfferItemCalculationDTO {
        $subscription = $item->subscription;
        $requiresNewInvoice =
            $item->executionDate === ExecutionDate::IMMEDIATE
            || $replacesFutureInvoice;

        return new RetentionOfferItemCalculationDTO(
            subscription: $subscription,
            selectedAction: $item->selectedAction,
            status: RetentionOfferCalculationStatus::CALCULATED,
            reason: null,
            price: $price,
            effectiveDate: $effectiveDate,
            oldContractStartDate: $subscription->start_date,
            oldContractEndDate: $subscription->end_date,
            newContractStartDate: $effectiveDate,
            newContractEndDate: $effectiveDate->addMonths(
                $item->contractPeriod,
            ),
            cancellationDate: null,
            creditTotal: $creditTotal,
            payableAfterCredits: $offerNetPrice - $creditTotal,
            requiresNewInvoice: $requiresNewInvoice,
            replacesFutureInvoice: $replacesFutureInvoice,
        );
    }
}
