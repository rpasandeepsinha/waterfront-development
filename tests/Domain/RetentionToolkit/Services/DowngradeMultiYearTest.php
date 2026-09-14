<?php

declare(strict_types=1);

namespace Tests\Domain\RetentionToolkit\Services;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\Factories\CustomerFactory;
use Tests\Factories\InvoiceFactory;
use Tests\Factories\ProductAllowedChangeFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Harbor\Services\HarborApiClient\HarborApi;
use Waterfront\Domain\Lighthouse\DTO\IdentityMetadataDTO;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\RetentionToolkit\DTO\RetentionOfferItemDTO;
use Waterfront\Domain\RetentionToolkit\DTO\RetentionOfferRequestDTO;
use Waterfront\Domain\RetentionToolkit\Enums\CustomerType;
use Waterfront\Domain\RetentionToolkit\Enums\ExecutionDate;
use Waterfront\Domain\RetentionToolkit\Enums\RetentionOfferCalculationStatus;
use Waterfront\Domain\RetentionToolkit\Enums\SelectedAction;
use Waterfront\Domain\RetentionToolkit\Models\CustomerRetentionOffer;
use Waterfront\Domain\RetentionToolkit\Services\RetentionToolkitService;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionChangeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Models\SubscriptionChange;
use Waterfront\Domain\Subscriptions\Models\SubscriptionMutation;

#[CoversClass(RetentionToolkitService::class)]
class DowngradeMultiYearTest extends IntegrationTestCase
{
    private const string NOW = '2026-12-10 12:00:00';

    private Customer $customer;

    private Subscription $subscription;

    private Product $currentProduct;

    private Product $targetProduct;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(self::NOW);

        $this->customer = new CustomerFactory()->withAddress()->createOne();

        $productGroup = new ProductGroupFactory()->hosting()->createOne();

        $this->targetProduct = new ProductFactory()
            ->hostingBrons($productGroup)
            ->createOne();

        $this->currentProduct = new ProductFactory()
            ->hostingGold($productGroup)
            ->createOne();

        new ProductPriceComponentFactory()
            ->for($this->targetProduct)
            ->prolongation()
            ->createOne([
                'price' => 1000,
            ]);

        new ProductPriceComponentFactory()
            ->for($this->targetProduct)
            ->prolongation()
            ->createOne([
                'price' => 1800,
                'contract_period' => 24,
                'billing_period' => 24,
            ]);

        new ProductAllowedChangeFactory()
            ->downgradeChange()
            ->createOne([
                'from_product_id' => $this->currentProduct->id,
                'to_product_id' => $this->targetProduct->id,
            ]);

        $this->subscription = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->for($this->customer)
            ->for($this->currentProduct)
            ->withPrice()
            ->createOne([
                'start_date' => $startDate = new CarbonImmutable('2026-01-01'),
                'end_date' => $startDate->addYear(),
                'gross_price' => 3650,
                'net_price' => 3650,
            ]);

        InvoiceFactory::new()
            ->sentToHarbor()
            ->for($this->customer)
            ->for($this->currentProduct)
            ->for($this->subscription)
            ->createOne([
                'start_date' => new CarbonImmutable('2026-01-01'),
                'end_date' => new CarbonImmutable('2027-01-01'),
                'gross_price' => 3650,
                'net_price' => 3650,
                'paid' => true,
            ]);
    }

    #[Test]
    public function calculateBusiness(): void
    {
        $results = self::resolve(RetentionToolkitService::class)->calculate($this->getRetentionOfferRequestDto());

        self::assertCount(1, $results);
        $result = $results[0];
        self::assertSame(
            RetentionOfferCalculationStatus::CALCULATED,
            $result->status,
        );
        self::assertSame(0, $result->creditTotal);
        self::assertNotNull($result->price);
        self::assertSame(2000, $result->price->grossPrice);
        self::assertSame(2000, $result->price->normalNetPrice);
        self::assertSame(1800, $result->payableAfterCredits);
        self::assertSame(200, $result->price->discountAmount);
        self::assertNotNull($result->effectiveDate);
        self::assertSame(
            $this->subscription->end_date->getTimestamp(),
            $result->effectiveDate->getTimestamp(),
        );
        self::assertNull($result->cancellationDate);
        self::assertFalse($result->requiresNewInvoice);
        self::assertFalse($result->replacesFutureInvoice);

        self::assertDatabaseEmpty(CustomerRetentionOffer::class);
        self::assertDatabaseEmpty(SubscriptionMutation::class);
    }

    #[Test]
    public function calculateConsumer(): void
    {
        $results = self::resolve(RetentionToolkitService::class)
            ->calculate($this->getRetentionOfferRequestDto(CustomerType::CONSUMER));

        self::assertCount(1, $results);
        $result = $results[0];
        self::assertSame(
            RetentionOfferCalculationStatus::CALCULATED,
            $result->status,
        );
        self::assertSame(220, $result->creditTotal);
        self::assertNotNull($result->price);
        self::assertSame(2000, $result->price->grossPrice);
        self::assertSame(2000, $result->price->normalNetPrice);
        self::assertSame(1580, $result->payableAfterCredits);
        self::assertSame(200, $result->price->discountAmount);
        self::assertNotNull($result->effectiveDate);
        self::assertSame(
            CarbonImmutable::today()->getTimestamp(),
            $result->effectiveDate->getTimestamp(),
        );
        self::assertNull($result->cancellationDate);
        self::assertTrue($result->requiresNewInvoice);
        self::assertFalse($result->replacesFutureInvoice);

        self::assertDatabaseEmpty(CustomerRetentionOffer::class);
        self::assertDatabaseEmpty(SubscriptionMutation::class);
    }

    #[Test]
    public function apply(): void
    {
        $this->app->bind(
            HarborApi::class,
            fn (): HarborApi => self::createStub(HarborApi::class),
        );

        $results = self::resolve(RetentionToolkitService::class)
            ->apply(
                request: $this->getRetentionOfferRequestDto(CustomerType::CONSUMER),
                createdByMetadata: new IdentityMetadataDTO(Uuid::uuid4(), 'employee@yourhosting.nl'),
            );

        self::assertCount(1, $results);
        $result = $results[0];
        self::assertSame(
            RetentionOfferCalculationStatus::CALCULATED,
            $result->status,
        );
        self::assertSame(220, $result->creditTotal);
        self::assertNotNull($result->price);
        self::assertSame(2000, $result->price->grossPrice);
        self::assertSame(200, $result->price->discountAmount);

        $this->subscription->refresh();

        self::assertSame(
            $this->targetProduct->id,
            $this->subscription->product->id,
        );
        self::assertSame(24, $this->subscription->billing_period);
        self::assertSame(24, $this->subscription->contract_period);
        self::assertSame(
            CarbonImmutable::today()->addMonths(24)->getTimestamp(),
            $this->subscription->end_date->getTimestamp(),
        );

        $subscriptionMutation = SubscriptionMutation::query()
            ->where('subscription_id', $this->subscription->id)
            ->sole();

        self::assertSame(
            $this->targetProduct->id,
            $subscriptionMutation->product_id,
        );
        self::assertSame(1800, $subscriptionMutation->gross_price);
        self::assertSame(1800, $subscriptionMutation->net_price);
        self::assertSame(24, $subscriptionMutation->billing_period);
        self::assertSame(24, $subscriptionMutation->contract_period);
        self::assertNotNull($subscriptionMutation->mutated_at);
        self::assertSame(
            CarbonImmutable::now()->getTimestamp(),
            $subscriptionMutation->mutated_at->getTimestamp(),
        );
        self::assertNotNull($subscriptionMutation->process_technical_at);
        self::assertSame(
            CarbonImmutable::today()->getTimestamp(),
            $subscriptionMutation->process_technical_at->getTimestamp(),
        );
        self::assertNull($subscriptionMutation->processed_technical_at);

        self::assertDatabaseHas(SubscriptionChange::class, [
            'subscription_uuid' => $this->subscription->uuid,
            'from_product_uuid' => $this->currentProduct->uuid,
            'to_product_uuid' => $this->targetProduct->uuid,
            'type' => ProductChangeType::DOWNGRADE->value,
            'status' => SubscriptionChangeStatus::REQUESTED->value,
        ]);
        self::assertDatabaseHas(CustomerRetentionOffer::class, [
            'customer_type' => CustomerType::CONSUMER->value,
            'subscription_id' => $this->subscription->id,
            'selected_action' => SelectedAction::DG_OPTION_1D->value,
            'puzzel_ticket_id' => '1337',
            'discount_amount' => 200,
            'credit_amount' => 220,
            'subscription_mutation_id' => $subscriptionMutation->id,
        ]);
    }

    private function getRetentionOfferRequestDto(CustomerType $customerType = CustomerType::BUSINESS): RetentionOfferRequestDTO
    {
        return new RetentionOfferRequestDTO(
            customer: $this->customer,
            customerType: $customerType,
            puzzelTicketId: '1337',
            items: [
                new RetentionOfferItemDTO(
                    subscription: $this->subscription,
                    selectedAction: SelectedAction::DG_OPTION_1D,
                    executionDate: $customerType === CustomerType::BUSINESS
                        ? ExecutionDate::CONTRACT_END
                        : ExecutionDate::IMMEDIATE,
                    contractPeriod: 24,
                    billingPeriod: 24,
                    targetProduct: $this->targetProduct,
                    cancelReason: null,
                    cancelReasonOther: null,
                ),
            ],
        );
    }
}
