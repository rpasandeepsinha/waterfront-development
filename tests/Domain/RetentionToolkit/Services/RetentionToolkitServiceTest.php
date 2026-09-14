<?php

declare(strict_types=1);

namespace Tests\Domain\RetentionToolkit\Services;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Tests\Factories\CustomerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\TestCase;
use Waterfront\Domain\Lighthouse\DTO\IdentityMetadataDTO;
use Waterfront\Domain\Pricing\Repositories\PriceRepository;
use Waterfront\Domain\RetentionToolkit\Actions\ApplyRetentionCancellationAction;
use Waterfront\Domain\RetentionToolkit\DTO\RetentionOfferItemCalculationDTO;
use Waterfront\Domain\RetentionToolkit\DTO\RetentionOfferItemDTO;
use Waterfront\Domain\RetentionToolkit\DTO\RetentionOfferRequestDTO;
use Waterfront\Domain\RetentionToolkit\Enums\CustomerType;
use Waterfront\Domain\RetentionToolkit\Enums\ExecutionDate;
use Waterfront\Domain\RetentionToolkit\Enums\RetentionOfferCalculationStatus;
use Waterfront\Domain\RetentionToolkit\Enums\SelectedAction;
use Waterfront\Domain\RetentionToolkit\Services\RetentionOfferPriceCalculator;
use Waterfront\Domain\RetentionToolkit\Services\RetentionToolkitInvoiceService;
use Waterfront\Domain\RetentionToolkit\Services\RetentionToolkitService;
use Waterfront\Domain\Subscriptions\Actions\ExtendContractAction;
use Waterfront\Domain\Subscriptions\Services\CreditSubscriptionService;
use Waterfront\Domain\Subscriptions\Services\SubscriptionRenewService;

#[CoversClass(RetentionToolkitService::class)]
class RetentionToolkitServiceTest extends TestCase
{
    #[Test]
    public function calculateDelegatesItemsInOrder(): void
    {
        $customer = CustomerFactory::new()->makeOne([
            'id' => 1,
        ]);
        $subscription = SubscriptionFactory::new()->makeOne([
            'customer_id' => $customer->id,
        ]);

        $firstItem = new RetentionOfferItemDTO(
            subscription: $subscription,
            selectedAction: SelectedAction::BZ,
            executionDate: ExecutionDate::CONTRACT_END,
            contractPeriod: 12,
            billingPeriod: 12,
            targetProduct: null,
            cancelReason: null,
            cancelReasonOther: null,
        );
        $secondItem = new RetentionOfferItemDTO(
            subscription: $subscription,
            selectedAction: SelectedAction::TK_OPTION_2,
            executionDate: ExecutionDate::IMMEDIATE,
            contractPeriod: 12,
            billingPeriod: 12,
            targetProduct: null,
            cancelReason: null,
            cancelReasonOther: null,
        );

        $request = new RetentionOfferRequestDTO(
            customer: $customer,
            customerType: CustomerType::CONSUMER,
            puzzelTicketId: '123456',
            items: [$firstItem, $secondItem],
        );

        $createResult = static fn (
            RetentionOfferItemDTO $item,
        ): RetentionOfferItemCalculationDTO => new RetentionOfferItemCalculationDTO(
            subscription: $item->subscription,
            selectedAction: $item->selectedAction,
            status: RetentionOfferCalculationStatus::CALCULATED,
            reason: null,
            price: null,
            effectiveDate: null,
            oldContractStartDate: null,
            oldContractEndDate: null,
            newContractStartDate: null,
            newContractEndDate: null,
            cancellationDate: null,
            creditTotal: null,
            payableAfterCredits: null,
            requiresNewInvoice: false,
            replacesFutureInvoice: false,
        );

        $firstResult = $createResult($firstItem);
        $secondResult = $createResult($secondItem);
        $calculatedItems = [];

        $priceCalculator = self::createMock(
            RetentionOfferPriceCalculator::class,
        );
        $priceCalculator
            ->expects(self::exactly(2))
            ->method('calculateItem')
            ->with(
                self::identicalTo($request),
                self::callback(function (RetentionOfferItemDTO $item) use (&$calculatedItems): bool {
                    $calculatedItems[] = $item;

                    return true;
                }),
            )
            ->willReturnOnConsecutiveCalls(
                $firstResult,
                $secondResult,
            );

        $service = new RetentionToolkitService(
            priceCalculator: $priceCalculator,
            priceRepository: self::createStub(PriceRepository::class),
            applyRetentionCancellationAction: self::createStub(ApplyRetentionCancellationAction::class),
            extendContractAction: self::createStub(
                ExtendContractAction::class,
            ),
            invoiceService: self::createStub(
                RetentionToolkitInvoiceService::class,
            ),
            subscriptionRenewService: self::createStub(
                SubscriptionRenewService::class,
            ),
            creditSubscriptionService: self::createStub(CreditSubscriptionService::class),
            logger: self::createStub(LoggerInterface::class),
        );

        $results = $service->calculate($request);

        self::assertSame(
            [$firstItem, $secondItem],
            $calculatedItems,
        );
        self::assertSame(
            [$firstResult, $secondResult],
            $results,
        );
    }

    #[Test]
    public function applyReturnsAllResultsWithoutStartingTransactionWhenCalculationFails(): void
    {
        $customer = CustomerFactory::new()->makeOne([
            'id' => 1,
        ]);
        $subscription = SubscriptionFactory::new()->makeOne([
            'customer_id' => $customer->id,
        ]);
        $items = [
            new RetentionOfferItemDTO(
                subscription: $subscription,
                selectedAction: SelectedAction::TK_OPTION_2,
                executionDate: ExecutionDate::IMMEDIATE,
                contractPeriod: 12,
                billingPeriod: 12,
                targetProduct: null,
                cancelReason: null,
                cancelReasonOther: null,
            ),
            new RetentionOfferItemDTO(
                subscription: $subscription,
                selectedAction: SelectedAction::TK_OPTION_3,
                executionDate: ExecutionDate::IMMEDIATE,
                contractPeriod: 12,
                billingPeriod: 12,
                targetProduct: null,
                cancelReason: null,
                cancelReasonOther: null,
            ),
        ];
        $request = new RetentionOfferRequestDTO(
            customer: $customer,
            customerType: CustomerType::CONSUMER,
            puzzelTicketId: '123456',
            items: $items,
        );
        $results = [
            new RetentionOfferItemCalculationDTO(
                subscription: $items[0]->subscription,
                selectedAction: $items[0]->selectedAction,
                status: RetentionOfferCalculationStatus::INELIGIBLE,
                reason: 'The subscription is not eligible.',
                price: null,
                effectiveDate: null,
                oldContractStartDate: null,
                oldContractEndDate: null,
                newContractStartDate: null,
                newContractEndDate: null,
                cancellationDate: null,
                creditTotal: null,
                payableAfterCredits: null,
                requiresNewInvoice: false,
                replacesFutureInvoice: false,
            ),
            new RetentionOfferItemCalculationDTO(
                subscription: $items[1]->subscription,
                selectedAction: $items[1]->selectedAction,
                status: RetentionOfferCalculationStatus::CALCULATED,
                reason: null,
                price: null,
                effectiveDate: null,
                oldContractStartDate: null,
                oldContractEndDate: null,
                newContractStartDate: null,
                newContractEndDate: null,
                cancellationDate: null,
                creditTotal: null,
                payableAfterCredits: null,
                requiresNewInvoice: false,
                replacesFutureInvoice: false,
            ),
        ];

        $priceCalculator = self::createMock(RetentionOfferPriceCalculator::class);
        $priceCalculator->expects(self::exactly(2))->method('calculateItem')->willReturnOnConsecutiveCalls(...$results);

        DB::shouldReceive('beginTransaction')->never();

        $service = new RetentionToolkitService(
            priceCalculator: $priceCalculator,
            priceRepository: self::createStub(PriceRepository::class),
            applyRetentionCancellationAction: self::createStub(ApplyRetentionCancellationAction::class),
            extendContractAction: self::createStub(ExtendContractAction::class),
            invoiceService: self::createStub(RetentionToolkitInvoiceService::class),
            subscriptionRenewService: self::createStub(SubscriptionRenewService::class),
            creditSubscriptionService: self::createStub(CreditSubscriptionService::class),
            logger: self::createStub(LoggerInterface::class),
        );

        $actualResults = $service->apply(
            request: $request,
            createdByMetadata: new IdentityMetadataDTO(
                uuid: Uuid::uuid4(),
                email: 'employee@example.test',
            ),
        );

        self::assertSame($results, $actualResults);
    }
}
