<?php

declare(strict_types=1);

namespace Tests\Domain\RetentionToolkit\Services;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Tests\Factories\CustomerFactory;
use Tests\Factories\InvoiceFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\SubscriptionMutationFactory;
use Tests\Factories\SubscriptionPriceFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Harbor\Services\HarborApiClient\HarborApi;
use Waterfront\Domain\Invoices\Actions\CreateInvoiceAndSetNextBillingDateForSubscriptionAction;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Lighthouse\DTO\IdentityMetadataDTO;
use Waterfront\Domain\Pricing\Models\SubscriptionPrice;
use Waterfront\Domain\RetentionToolkit\DTO\RetentionOfferItemDTO;
use Waterfront\Domain\RetentionToolkit\DTO\RetentionOfferRequestDTO;
use Waterfront\Domain\RetentionToolkit\Enums\CustomerType;
use Waterfront\Domain\RetentionToolkit\Enums\ExecutionDate;
use Waterfront\Domain\RetentionToolkit\Enums\RetentionOfferCalculationStatus;
use Waterfront\Domain\RetentionToolkit\Enums\SelectedAction;
use Waterfront\Domain\RetentionToolkit\Models\CustomerRetentionOffer;
use Waterfront\Domain\RetentionToolkit\Services\RetentionToolkitService;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Models\SubscriptionMutation;
use Webmozart\Assert\Assert;

#[CoversClass(RetentionToolkitService::class)]
class ImmediateRetentionOfferTest extends IntegrationTestCase
{
    private const string NOW = '2026-07-01 12:00:00';

    private Customer $customer;

    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(self::NOW);

        $this->customer = new CustomerFactory()->withAddress()->createOne();

        $domainProduct = new ProductFactory()->nlDomain()->createOne();

        new ProductPriceComponentFactory()
            ->for($domainProduct)
            ->prolongation()
            ->createOne([
                'price' => 1000,
            ]);

        $this->subscription = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->for($this->customer)
            ->for($domainProduct)
            ->withPrice()
            ->createOne([
                'start_date' => new CarbonImmutable('2026-01-01'),
                'gross_price' => 365,
                'net_price' => 365,
            ]);
    }

    #[Test]
    public function appliesConsumerRetentionOfferImmediately(): void
    {
        $this->app->bind(
            HarborApi::class,
            fn (): HarborApi => self::createStub(HarborApi::class),
        );

        $currentInvoice = InvoiceFactory::new()
            ->sentToHarbor()
            ->for($this->customer)
            ->for($this->subscription->product)
            ->for($this->subscription)
            ->createOne([
                'start_date' => new CarbonImmutable('2026-01-01'),
                'end_date' => new CarbonImmutable('2027-01-01'),
                'gross_price' => 365,
                'net_price' => 365,
            ]);

        $results = self::resolve(RetentionToolkitService::class)
            ->apply(
                request: $this->getRetentionOfferRequest(),
                createdByMetadata: new IdentityMetadataDTO(
                    uuid: Uuid::uuid4(),
                    email: 'employee@yourhosting.nl',
                ),
            );

        self::assertCount(1, $results);
        $result = $results[0];

        self::assertSame(
            RetentionOfferCalculationStatus::CALCULATED,
            $result->status,
        );

        $this->subscription->refresh();

        self::assertSame(
            CarbonImmutable::today()->addYear()->getTimestamp(),
            $this->subscription->end_date->getTimestamp(),
        );
        self::assertSame(1000, $this->subscription->gross_price);
        self::assertSame(750, $this->subscription->net_price);

        $creditInvoice = Invoice::query()->where('parent_invoice_id', $currentInvoice->id)->sole();

        self::assertSame(-184, $creditInvoice->net_price);
        self::assertSame(
            CarbonImmutable::today()->getTimestamp(),
            $creditInvoice->start_date->getTimestamp(),
        );

        $newInvoice = Invoice::query()
            ->where('subscription_id', $this->subscription->id)
            ->whereNull('parent_invoice_id')
            ->where('id', '<>', $currentInvoice->id)
            ->sole();

        self::assertSame(1000, $newInvoice->gross_price);
        self::assertSame(750, $newInvoice->net_price);
        self::assertSame(
            CarbonImmutable::today()->getTimestamp(),
            $newInvoice->start_date->getTimestamp(),
        );
        self::assertSame(
            CarbonImmutable::today()->addYear()->getTimestamp(),
            $newInvoice->end_date->getTimestamp(),
        );
        self::assertSame(
            $newInvoice->end_date->getTimestamp(),
            $this->subscription->next_billing_date->getTimestamp(),
        );

        $subscriptionMutation = SubscriptionMutation::query()
            ->where('subscription_id', $this->subscription->id)
            ->sole();

        self::assertSame(1000, $subscriptionMutation->gross_price);
        self::assertSame(750, $subscriptionMutation->net_price);
        self::assertSame(12, $subscriptionMutation->billing_period);
        self::assertSame(12, $subscriptionMutation->contract_period);
        self::assertNotNull($subscriptionMutation->mutated_at);
        self::assertSame(
            CarbonImmutable::now()->getTimestamp(),
            $subscriptionMutation->mutated_at->getTimestamp(),
        );
        self::assertNull($subscriptionMutation->process_technical_at);

        $retentionOffer = CustomerRetentionOffer::query()
            ->where('subscription_id', $this->subscription->id)
            ->where('selected_action', SelectedAction::DM_OPTION_1->value)
            ->sole();

        self::assertSame(
            $subscriptionMutation->id,
            $retentionOffer->subscription_mutation_id,
        );
        self::assertSame(184, $retentionOffer->credit_amount);
    }

    #[Test]
    public function openMutationConflictsWithImmediateOfferAndPreventsApplication(): void
    {
        $openMutation = SubscriptionMutationFactory::new()->for($this->subscription)->for($this->subscription->product)->createOne();

        $results = self::resolve(RetentionToolkitService::class)
            ->apply(
                request: $this->getRetentionOfferRequest(),
                createdByMetadata: new IdentityMetadataDTO(
                    uuid: Uuid::uuid4(),
                    email: 'employee@yourhosting.nl',
                ),
            );

        self::assertCount(1, $results);
        self::assertSame(
            RetentionOfferCalculationStatus::CONFLICT,
            $results[0]->status,
        );

        self::assertDatabaseCount(SubscriptionMutation::class, 1);
        self::assertDatabaseHas(SubscriptionMutation::class, [
            'id' => $openMutation->id,
            'mutated_at' => null,
        ]);
        self::assertDatabaseEmpty(CustomerRetentionOffer::class);
    }

    #[Test]
    public function failedInvoiceCreationRollsBackImmediateOffer(): void
    {
        $harborApi = self::createMock(HarborApi::class);
        $harborApi->expects(self::never())->method('sendCredit');
        $this->app->bind(
            HarborApi::class,
            fn (): HarborApi => $harborApi,
        );

        $createInvoiceAction = self::createMock(
            CreateInvoiceAndSetNextBillingDateForSubscriptionAction::class,
        );
        $exception = new RuntimeException('Invoice creation failed.');

        $createInvoiceAction->expects(self::once())->method('execute')->willThrowException($exception);
        $this->app->bind(
            CreateInvoiceAndSetNextBillingDateForSubscriptionAction::class,
            fn (): CreateInvoiceAndSetNextBillingDateForSubscriptionAction => $createInvoiceAction,
        );

        InvoiceFactory::new()
            ->sentToHarbor()
            ->for($this->customer)
            ->for($this->subscription->product)
            ->for($this->subscription)
            ->createOne([
                'start_date' => new CarbonImmutable('2026-01-01'),
                'end_date' => new CarbonImmutable('2027-01-01'),
                'gross_price' => 365,
                'net_price' => 365,
            ]);

        $futurePrice = SubscriptionPriceFactory::new()->for($this->subscription)->createOne([
            'valid_from' => CarbonImmutable::today()->addMonth(),
            'net_price' => 900,
        ]);

        $originalEndDate = $this->subscription->end_date->getTimestamp();
        $originalNextBillingDate = $this->subscription->next_billing_date->getTimestamp();
        $originalGrossPrice = $this->subscription->gross_price;
        $originalNetPrice = $this->subscription->net_price;
        $originalSubscriptionPriceId = $this->subscription->subscription_price_id;

        self::expectExceptionObject($exception);

        try {
            self::resolve(RetentionToolkitService::class)
                ->apply(
                    request: $this->getRetentionOfferRequest(),
                    createdByMetadata: new IdentityMetadataDTO(
                        uuid: Uuid::uuid4(),
                        email: 'employee@yourhosting.nl',
                    ),
                );
        } finally {
            self::assertDatabaseEmpty(CustomerRetentionOffer::class);
            self::assertDatabaseEmpty(SubscriptionMutation::class);
            self::assertDatabaseCount(Invoice::class, 1);
            self::assertDatabaseHas(SubscriptionPrice::class, [
                'id' => $futurePrice->id,
            ]);

            $this->subscription->refresh();

            self::assertSame(
                $originalEndDate,
                $this->subscription->end_date->getTimestamp(),
            );
            self::assertSame(
                $originalNextBillingDate,
                $this->subscription->next_billing_date->getTimestamp(),
            );
            self::assertSame(
                $originalGrossPrice,
                $this->subscription->gross_price,
            );
            self::assertSame(
                $originalNetPrice,
                $this->subscription->net_price,
            );
            self::assertSame(
                $originalSubscriptionPriceId,
                $this->subscription->subscription_price_id,
            );
        }
    }

    private function getRetentionOfferRequest(): RetentionOfferRequestDTO
    {
        $contractPeriod = $this->subscription->contract_period;
        $billingPeriod = $this->subscription->billing_period;
        Assert::positiveInteger($contractPeriod);
        Assert::positiveInteger($billingPeriod);

        return new RetentionOfferRequestDTO(
            customer: $this->customer,
            customerType: CustomerType::CONSUMER,
            puzzelTicketId: '1337',
            items: [
                new RetentionOfferItemDTO(
                    subscription: $this->subscription,
                    selectedAction: SelectedAction::DM_OPTION_1,
                    executionDate: ExecutionDate::IMMEDIATE,
                    contractPeriod: $contractPeriod,
                    billingPeriod: $billingPeriod,
                    targetProduct: null,
                    cancelReason: null,
                    cancelReasonOther: null,
                ),
            ],
        );
    }
}
