<?php

declare(strict_types=1);

namespace Tests\Apps\API\Compass\Controllers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Controllers\RetentionToolkitController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\RetentionToolkit\DTO\RetentionOfferEligibilityResultDTO;
use Waterfront\Domain\RetentionToolkit\DTO\RetentionOfferItemCalculationDTO;
use Waterfront\Domain\RetentionToolkit\DTO\RetentionOfferPriceDTO;
use Waterfront\Domain\RetentionToolkit\Enums\CustomerType;
use Waterfront\Domain\RetentionToolkit\Enums\ExecutionDate;
use Waterfront\Domain\RetentionToolkit\Enums\RetentionOfferCalculationStatus;
use Waterfront\Domain\RetentionToolkit\Enums\RetentionOfferEligibilityCode;
use Waterfront\Domain\RetentionToolkit\Enums\SelectedAction;
use Waterfront\Domain\RetentionToolkit\Services\RetentionToolkitService;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(RetentionToolkitController::class)]
class RetentionToolkitControllerTest extends IntegrationTestCase
{
    private RetentionToolkitService&MockObject $retentionToolkitService;

    private Customer $customer;

    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();

        $subscriptionProduct = new ProductFactory()->for(new ProductGroupFactory()->extension())->createOne();

        $this->subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($subscriptionProduct)
            ->createOne();

        $this->retentionToolkitService = self::createMock(RetentionToolkitService::class);
        $this->app->bind(RetentionToolkitService::class, fn () => $this->retentionToolkitService);
    }

    #[Test]
    public function calculateSuccessful(): void
    {
        $this->retentionToolkitService->expects($this->once())->method('calculate');

        $payload = $this->getPayload(
            subscription: $this->subscription,
        );
        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.customers.retention-toolkit.calculate', [
                'customer' => $this->customer->customer_number,
            ]), $payload)
            ->assertOk();
    }

    #[Test]
    public function calculateWithIncorrectSubscription(): void
    {
        $this->retentionToolkitService->expects($this->never())->method('calculate');

        $payload = $this->getPayload(
            subscription: new SubscriptionFactory()->makeOne(),
        );
        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.customers.retention-toolkit.calculate', [
                'customer' => $this->customer->customer_number,
            ]), $payload)
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['items.0.subscriptionUuid']]);
    }

    #[Test]
    public function calculateWithIncorrectCustomerType(): void
    {
        $this->retentionToolkitService->expects($this->never())->method('calculate');

        $payload = $this->getPayload(
            subscription: $this->subscription,
            customerType: 'incorrect-customer-type',
        );
        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.customers.retention-toolkit.calculate', [
                'customer' => $this->customer->customer_number,
            ]), $payload)
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['customerType']]);
    }

    #[Test]
    public function calculateServiceReturnsIneligible(): void
    {
        $this->retentionToolkitService
            ->expects($this->once())
            ->method('calculate')
            ->willReturn(
                [
                    new RetentionOfferItemCalculationDTO(
                        subscription: $this->subscription,
                        selectedAction: SelectedAction::DG_OPTION_1A,
                        status: RetentionOfferCalculationStatus::INELIGIBLE,
                        reason: 'Retention action dg_option_1a requires a target product.',
                        price: new RetentionOfferPriceDTO(
                            eligibility: new RetentionOfferEligibilityResultDTO(
                                code: RetentionOfferEligibilityCode::INELIGIBLE_PRODUCT,
                                reason: 'Retention action dg_option_1a requires a target product.',
                            ),
                            grossPrice: null,
                            normalNetPrice: null,
                            offerNetPrice: null,
                            discountAmount: null,
                        ),
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
                ],
            );

        $payload = $this->getPayload(
            subscription: $this->subscription,
            customerType: CustomerType::CONSUMER->value,
            selectedAction: SelectedAction::DG_OPTION_1A,
        );
        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.customers.retention-toolkit.calculate', [
                'customer' => $this->customer->customer_number,
            ]), $payload)
            ->assertUnprocessable()
            ->assertJson([
                'message' => 'One or more retention toolkit items could not be calculated.',
                'errors' => [
                    'items.0' => [
                        'Retention action dg_option_1a requires a target product.',
                    ],
                ],
            ]);
    }

    /**
     * @return array{customerType: string, puzzelTicketId: string, items: array<int, array<string, string|null|int>>}
     */
    private function getPayload(
        Subscription $subscription,
        string $customerType = CustomerType::CONSUMER->value,
        SelectedAction $selectedAction = SelectedAction::DM_OPTION_1,
    ): array {
        return [
            'customerType' => $customerType,
            'puzzelTicketId' => '1337',
            'items' => [
                [
                    'subscriptionUuid' => $subscription->uuid,
                    'selectedAction' => $selectedAction->value,
                    'executionDate' => ExecutionDate::IMMEDIATE->value,
                    'contractPeriod' => $this->subscription->contract_period,
                    'billingPeriod' => $this->subscription->billing_period,
                    'targetProductUuid' => null,
                    'cancelReason' => null,
                    'cancelReasonOther' => null,
                ],
            ],
        ];
    }
}
