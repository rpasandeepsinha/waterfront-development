<?php

declare(strict_types=1);

namespace Waterfront\Domain\RetentionToolkit\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceBatchCrediter\InvoiceToCreditBatch;
use Waterfront\Domain\Lighthouse\DTO\IdentityMetadataDTO;
use Waterfront\Domain\Pricing\Repositories\PriceRepository;
use Waterfront\Domain\RetentionToolkit\Actions\ApplyRetentionCancellationAction;
use Waterfront\Domain\RetentionToolkit\DTO\RetentionOfferItemCalculationDTO;
use Waterfront\Domain\RetentionToolkit\DTO\RetentionOfferItemDTO;
use Waterfront\Domain\RetentionToolkit\DTO\RetentionOfferRequestDTO;
use Waterfront\Domain\RetentionToolkit\Enums\ExecutionDate;
use Waterfront\Domain\RetentionToolkit\Enums\RetentionOfferCalculationStatus;
use Waterfront\Domain\RetentionToolkit\Enums\RetentionOfferEligibilityCode;
use Waterfront\Domain\RetentionToolkit\Enums\SelectedAction;
use Waterfront\Domain\RetentionToolkit\Exceptions\RetentionOfferCannotBeAppliedException;
use Waterfront\Domain\RetentionToolkit\Models\CustomerRetentionOffer;
use Waterfront\Domain\Subscriptions\Actions\ExtendContractAction;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\CreditSubscriptionService;
use Waterfront\Domain\Subscriptions\Services\SubscriptionRenewService;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

readonly class RetentionToolkitService
{
    public function __construct(
        private RetentionOfferPriceCalculator $priceCalculator,
        private PriceRepository $priceRepository,
        private ApplyRetentionCancellationAction $applyRetentionCancellationAction,
        private ExtendContractAction $extendContractAction,
        private RetentionToolkitInvoiceService $invoiceService,
        private SubscriptionRenewService $subscriptionRenewService,
        private CreditSubscriptionService $creditSubscriptionService,
        private LoggerInterface $logger,
    ) {
    }

    /** @return list<RetentionOfferItemCalculationDTO> */
    public function calculate(RetentionOfferRequestDTO $request): array
    {
        $results = [];

        foreach ($request->items as $item) {
            $results[] = $this->priceCalculator->calculateItem(
                request: $request,
                item: $item,
            );
        }

        return $results;
    }

    /**
     * @throws RetentionOfferCannotBeAppliedException
     *
     * @return list<RetentionOfferItemCalculationDTO>
     */
    public function apply(
        RetentionOfferRequestDTO $request,
        IdentityMetadataDTO $createdByMetadata,
    ): array {
        $results = $this->calculate($request);

        $this->assertResultsCanBeApplied($request, $results);

        try {
            DB::beginTransaction();

            $invoiceLinesToCredit = new InvoiceToCreditBatch();
            $shouldCreditInvoiceLines = false;

            foreach ($request->items as $index => $item) {
                $result = $results[$index];

                $customerRetentionOffer = new CustomerRetentionOffer();
                $customerRetentionOffer->created_by_metadata = $createdByMetadata;
                $customerRetentionOffer->customer_type = $request->customerType;
                $customerRetentionOffer->subscription_id = $item->subscription->id;
                $customerRetentionOffer->selected_action = $item->selectedAction;
                $customerRetentionOffer->puzzel_ticket_id = $request->puzzelTicketId;

                switch ($item->selectedAction) {
                    case SelectedAction::BZ:
                        $customerRetentionOffer->effective_at = CarbonImmutable::today();
                        break;
                    case SelectedAction::RF:
                        Assert::notNull($result->cancellationDate);

                        $preparedInvoiceLinesToCredit =
                            $this->applyRetentionCancellationAction->execute(
                                customerType: $request->customerType,
                                item: $item,
                                result: $result,
                                subscription: $item->subscription,
                            );

                        if ($preparedInvoiceLinesToCredit !== null) {
                            $shouldCreditInvoiceLines = true;

                            $this->addInvoiceLinesToCredit(
                                target: $invoiceLinesToCredit,
                                source: $preparedInvoiceLinesToCredit,
                            );
                        }

                        $customerRetentionOffer->credit_amount = $result->creditTotal;
                        $customerRetentionOffer->effective_at = $result->cancellationDate;
                        break;
                    case SelectedAction::DM_OPTION_1:
                    case SelectedAction::TK_OPTION_1:
                    case SelectedAction::TK_OPTION_2:
                    case SelectedAction::TK_OPTION_3:
                    case SelectedAction::TK_OPTION_5:
                    case SelectedAction::TK_OPTION_6:
                    case SelectedAction::DG_OPTION_1A:
                    case SelectedAction::DG_OPTION_1D:
                        $effectiveDate = $result->effectiveDate;
                        $creditTotal = $result->creditTotal;

                        Assert::notNull($effectiveDate);
                        Assert::notNull($creditTotal);

                        if ($creditTotal > 0 || $result->replacesFutureInvoice) {
                            $shouldCreditInvoiceLines = true;

                            $this->addInvoiceLinesToCredit(
                                target: $invoiceLinesToCredit,
                                source: $this->creditSubscriptionService
                                    ->getInvoiceLinesToCreditBatchFromDate(
                                        subscription: $item->subscription,
                                        creditFromDate: $effectiveDate,
                                        cancelReason: SubscriptionCancelReason::REASON_CANCELLATION,
                                    ),
                            );
                        }

                        $customerRetentionOffer = $this->executeRetentionOffer(
                            item: $item,
                            result: $result,
                            subscription: $item->subscription,
                            customerRetentionOffer: $customerRetentionOffer,
                            effectiveDate: $effectiveDate,
                            creditTotal: $creditTotal,
                        );
                        break;
                    default:
                        throw new RetentionOfferCannotBeAppliedException(
                            'The selected retention action cannot be applied yet.',
                        );
                }

                $customerRetentionOffer->save();
            }

            if ($shouldCreditInvoiceLines) {
                $this->creditSubscriptionService
                    ->creditInvoiceLines($invoiceLinesToCredit);
            }

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();

            $this->logger->critical(
                'Retention Toolkit application failed; transaction rolled back.',
                [
                    LoggingContextKeys::CUSTOMER_ID => $request->customer->id,
                    LoggingContextKeys::IDENTITY_UUID => $createdByMetadata->uuid,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

            throw $exception;
        }

        return $results;
    }

    /**
     * @param list<RetentionOfferItemCalculationDTO> $results
     *
     * @throws RetentionOfferCannotBeAppliedException
     */
    private function assertResultsCanBeApplied(
        RetentionOfferRequestDTO $request,
        array $results,
    ): void {
        foreach ($results as $result) {
            if ($result->price?->eligibility->code === RetentionOfferEligibilityCode::OPEN_MUTATION) {
                throw new RetentionOfferCannotBeAppliedException(
                    $result->reason
                    ?? 'The subscription has an open mutation that must be reviewed first.',
                );
            }

            if ($result->status !== RetentionOfferCalculationStatus::CALCULATED) {
                throw new RetentionOfferCannotBeAppliedException(
                    'Every retention action must be calculated before it can be applied.',
                );
            }
        }

        foreach ($request->items as $item) {
            if (! in_array(
                $item->selectedAction,
                [
                    SelectedAction::RF,
                    SelectedAction::BZ,
                    SelectedAction::DM_OPTION_1,
                    SelectedAction::TK_OPTION_1,
                    SelectedAction::TK_OPTION_2,
                    SelectedAction::TK_OPTION_3,
                    SelectedAction::TK_OPTION_5,
                    SelectedAction::TK_OPTION_6,
                    SelectedAction::DG_OPTION_1A,
                    SelectedAction::DG_OPTION_1D,
                ],
                true,
            )) {
                throw new RetentionOfferCannotBeAppliedException(
                    'The selected retention action cannot be applied yet.',
                );
            }
        }
    }

    private function executeRetentionOffer(
        RetentionOfferItemDTO $item,
        RetentionOfferItemCalculationDTO $result,
        Subscription $subscription,
        CustomerRetentionOffer $customerRetentionOffer,
        CarbonImmutable $effectiveDate,
        int $creditTotal,
    ): CustomerRetentionOffer {
        $isImmediate = $item->executionDate === ExecutionDate::IMMEDIATE;
        $price = $result->price;

        Assert::notNull($price);

        $offerNetPrice = $price->offerNetPrice;
        $discountAmount = $price->discountAmount;

        Assert::notNull($offerNetPrice);
        Assert::notNull($discountAmount);

        if ($isImmediate) {
            $this->priceRepository->deleteFutureSubscriptionPrices($subscription);
        }

        $targetProduct = null;

        if ($item->selectedAction->isDowngrade()) {
            Assert::notNull($item->targetProduct);

            $targetProduct = $item->targetProduct;
        }

        $subscriptionMutation = $this->extendContractAction->execute(
            subscription: $subscription,
            billingPeriod: $item->billingPeriod,
            contractPeriod: $item->contractPeriod,
            renewalPrice: $offerNetPrice,
            product: $targetProduct,
        );
        $customerRetentionOffer->subscription_mutation_id = $subscriptionMutation->id;

        if ($isImmediate) {
            $subscription->end_date = $effectiveDate;
            $subscription->save();

            $this->subscriptionRenewService->renew($subscription);
        }

        if ($result->requiresNewInvoice) {
            $this->invoiceService->createInvoiceFromEffectiveDate(
                subscription: $subscription,
                effectiveDate: $effectiveDate,
            );
        }

        $customerRetentionOffer->credit_amount = $creditTotal;
        $customerRetentionOffer->discount_amount = $discountAmount;
        $customerRetentionOffer->effective_at = $effectiveDate;

        return $customerRetentionOffer;
    }

    private function addInvoiceLinesToCredit(
        InvoiceToCreditBatch $target,
        InvoiceToCreditBatch $source,
    ): void {
        foreach ($source->getInvoicesToCredit() as $invoiceToCredit) {
            $target->add($invoiceToCredit);
        }
    }
}
