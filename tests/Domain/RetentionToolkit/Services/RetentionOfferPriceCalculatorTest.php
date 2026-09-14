<?php

declare(strict_types=1);

namespace Tests\Domain\RetentionToolkit\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use Tests\Factories\CustomerFactory;
use Tests\Factories\InvoiceFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\TestCase;
use Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceBatchCrediter\InvoiceToCreditBatch;
use Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceCrediter\InvoiceToCredit;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\DTO\PriceList;
use Waterfront\Domain\Products\DTO\PriceRequest;
use Waterfront\Domain\Products\DTO\Product as ProductDTO;
use Waterfront\Domain\Products\DTO\ProlongationPriceRequest;
use Waterfront\Domain\Products\DTO\SpecificationMap;
use Waterfront\Domain\Products\Enums\ProductPriceType;
use Waterfront\Domain\Products\Exceptions\PriceResolvingException;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Domain\RetentionToolkit\DTO\RetentionOfferEligibilityResultDTO;
use Waterfront\Domain\RetentionToolkit\DTO\RetentionOfferItemDTO;
use Waterfront\Domain\RetentionToolkit\DTO\RetentionOfferRequestDTO;
use Waterfront\Domain\RetentionToolkit\Enums\CustomerType;
use Waterfront\Domain\RetentionToolkit\Enums\ExecutionDate;
use Waterfront\Domain\RetentionToolkit\Enums\RetentionOfferCalculationStatus;
use Waterfront\Domain\RetentionToolkit\Enums\RetentionOfferEligibilityCode;
use Waterfront\Domain\RetentionToolkit\Enums\SelectedAction;
use Waterfront\Domain\RetentionToolkit\Exceptions\InvalidRetentionEffectiveDateException;
use Waterfront\Domain\RetentionToolkit\Factories\RetentionOfferItemCalculationFactory;
use Waterfront\Domain\RetentionToolkit\Services\RetentionEffectiveDateCalculator;
use Waterfront\Domain\RetentionToolkit\Services\RetentionOfferEligibilityService;
use Waterfront\Domain\RetentionToolkit\Services\RetentionOfferPriceCalculator;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\CreditSubscriptionService;

#[CoversClass(RetentionOfferPriceCalculator::class)]
class RetentionOfferPriceCalculatorTest extends TestCase
{
    private PriceResolver&Stub $priceResolver;

    private RetentionOfferPriceCalculator $calculator;

    private Subscription $subscription;

    private Price $price;

    private PriceList $priceList;

    private RetentionOfferRequestDTO $request;

    private Product $targetProduct;

    private ProductDTO $resolvedTargetProduct;

    private PriceList $targetPriceList;

    private RetentionOfferEligibilityService&Stub $eligibilityService;

    private RetentionEffectiveDateCalculator&Stub $effectiveDateCalculator;

    private CreditSubscriptionService&Stub $creditSubscriptionService;

    private RetentionOfferItemCalculationFactory $itemCalculationFactory;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(
            new CarbonImmutable('2026-07-01'),
        );

        $productGroup = ProductGroupFactory::new()->hosting()->makeOne();

        $product = ProductFactory::new()->hostingGold($productGroup)->makeOne();

        $this->targetProduct = ProductFactory::new()->hostingBrons($productGroup)->makeOne();
        $this->targetProduct->setRelation('productGroup', $productGroup);

        $customer = CustomerFactory::new()->makeOne(['id' => 1]);

        $this->subscription = SubscriptionFactory::new()->for($customer)->makeOne([
            'start_date' => new CarbonImmutable('2026-01-01'),
            'end_date' => new CarbonImmutable('2026-12-31'),
        ]);

        $this->subscription->setRelation('product', $product);
        $this->subscription->setRelation('customer', $customer);

        $this->request = new RetentionOfferRequestDTO(
            customer: $customer,
            customerType: CustomerType::CONSUMER,
            puzzelTicketId: '123456',
            items: [],
        );

        $this->price = new Price(
            type: ProductPriceType::REGISTRATION,
            billingPeriod: 12,
            productId: 1,
            productGroupUuid: 'uuid',
            regularPrice: 1000,
            contractPeriod: 12,
            orderable: true,
            is_default: false,
            calculatedPrice: 1000,
        );

        $resolvedProduct = new ProductDTO(
            uuid: $product->uuid,
            type: $productGroup->slug,
            name: $product->name,
            slug: $product->slug,
            description: $product->description ?? '',
            weight: $product->weight,
            orderable: $product->orderable,
            prices: new Collection([$this->price]),
            specifications: new SpecificationMap([]),
        );

        $this->priceList = new PriceList([
            $resolvedProduct,
        ]);

        $this->resolvedTargetProduct = new ProductDTO(
            uuid: $this->targetProduct->uuid,
            type: $productGroup->slug,
            name: $this->targetProduct->name,
            slug: $this->targetProduct->slug,
            description: $this->targetProduct->description ?? '',
            weight: $this->targetProduct->weight,
            orderable: $this->targetProduct->orderable,
            prices: new Collection([$this->price]),
            specifications: new SpecificationMap([]),
        );

        $this->targetPriceList = new PriceList([
            $this->resolvedTargetProduct,
        ]);

        $this->eligibilityService = self::createStub(
            RetentionOfferEligibilityService::class,
        );
        $this->eligibilityService
            ->method('determineEligibility')
            ->willReturn(new RetentionOfferEligibilityResultDTO(
                code: RetentionOfferEligibilityCode::ELIGIBLE,
                reason: null,
            ));

        $this->effectiveDateCalculator = self::createStub(
            RetentionEffectiveDateCalculator::class,
        );
        $this->effectiveDateCalculator->method('calculate')->willReturn($this->subscription->end_date);

        $this->creditSubscriptionService = self::createStub(
            CreditSubscriptionService::class,
        );
        $this->creditSubscriptionService
            ->method('getInvoiceLinesToCreditBatchFromDate')
            ->willReturn(new InvoiceToCreditBatch());

        $this->priceResolver = self::createStub(PriceResolver::class);
        $this->priceResolver->method('getPriceList')->willReturn($this->priceList);

        $this->itemCalculationFactory = new RetentionOfferItemCalculationFactory();

        $this->calculator = new RetentionOfferPriceCalculator(
            eligibilityService: $this->eligibilityService,
            priceResolver: $this->priceResolver,
            effectiveDateCalculator: $this->effectiveDateCalculator,
            creditSubscriptionService: $this->creditSubscriptionService,
            itemCalculationFactory: $this->itemCalculationFactory,
        );
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function rejectsSubscriptionOutsideCustomerContext(): void
    {
        $otherCustomer = CustomerFactory::new()->makeOne(['id' => 2]);

        $subscription = SubscriptionFactory::new()->for($otherCustomer)->makeOne();

        $result = $this->calculator->calculateItem(
            request: $this->request,
            item: new RetentionOfferItemDTO(
                subscription: $subscription,
                selectedAction: SelectedAction::TK_OPTION_2,
                executionDate: ExecutionDate::IMMEDIATE,
                contractPeriod: 12,
                billingPeriod: 12,
                targetProduct: null,
                cancelReason: null,
                cancelReasonOther: null,
            ),
        );

        self::assertSame(
            RetentionOfferCalculationStatus::INELIGIBLE,
            $result->status,
        );
        self::assertSame(
            'The subscription is not available in the current customer context.',
            $result->reason,
        );
        self::assertNull($result->price);
    }

    #[Test]
    public function returnsIneligibleEligibilityResult(): void
    {
        $eligibilityService = self::createStub(
            RetentionOfferEligibilityService::class,
        );
        $eligibilityService
            ->method('determineEligibility')
            ->willReturn(new RetentionOfferEligibilityResultDTO(
                code: RetentionOfferEligibilityCode::INELIGIBLE_PRODUCT,
                reason: 'Eligibility failed.',
            ));

        $calculator = new RetentionOfferPriceCalculator(
            eligibilityService: $eligibilityService,
            priceResolver: $this->priceResolver,
            effectiveDateCalculator: $this->effectiveDateCalculator,
            creditSubscriptionService: $this->creditSubscriptionService,
            itemCalculationFactory: $this->itemCalculationFactory,
        );

        $result = $calculator->calculateItem(
            request: $this->request,
            item: new RetentionOfferItemDTO(
                subscription: $this->subscription,
                selectedAction: SelectedAction::TK_OPTION_2,
                executionDate: ExecutionDate::CONTRACT_END,
                contractPeriod: 12,
                billingPeriod: 12,
                targetProduct: null,
                cancelReason: null,
                cancelReasonOther: null,
            ),
        );

        self::assertSame(
            RetentionOfferCalculationStatus::INELIGIBLE,
            $result->status,
        );
        self::assertSame('Eligibility failed.', $result->reason);
    }

    #[Test]
    public function buildsBzResultWithoutChanges(): void
    {
        $eligibilityService = self::createStub(
            RetentionOfferEligibilityService::class,
        );
        $eligibilityService
            ->method('determineEligibility')
            ->willReturn(new RetentionOfferEligibilityResultDTO(
                code: RetentionOfferEligibilityCode::NO_PRICE_REQUIRED,
                reason: null,
            ));

        $calculator = new RetentionOfferPriceCalculator(
            eligibilityService: $eligibilityService,
            priceResolver: $this->priceResolver,
            effectiveDateCalculator: $this->effectiveDateCalculator,
            creditSubscriptionService: $this->creditSubscriptionService,
            itemCalculationFactory: $this->itemCalculationFactory,
        );

        $result = $calculator->calculateItem(
            request: $this->request,
            item: new RetentionOfferItemDTO(
                subscription: $this->subscription,
                selectedAction: SelectedAction::BZ,
                executionDate: ExecutionDate::CONTRACT_END,
                contractPeriod: 12,
                billingPeriod: 12,
                targetProduct: null,
                cancelReason: null,
                cancelReasonOther: null,
            ),
        );

        self::assertSame(
            RetentionOfferCalculationStatus::CALCULATED,
            $result->status,
        );

        self::assertNull($result->effectiveDate);
        self::assertSame(0, $result->creditTotal);
        self::assertFalse($result->requiresNewInvoice);
    }

    #[Test]
    public function requiresRfCancellationReason(): void
    {
        $eligibilityService = self::createStub(
            RetentionOfferEligibilityService::class,
        );
        $eligibilityService
            ->method('determineEligibility')
            ->willReturn(new RetentionOfferEligibilityResultDTO(
                code: RetentionOfferEligibilityCode::NO_PRICE_REQUIRED,
                reason: null,
            ));

        $calculator = new RetentionOfferPriceCalculator(
            eligibilityService: $eligibilityService,
            priceResolver: $this->priceResolver,
            effectiveDateCalculator: $this->effectiveDateCalculator,
            creditSubscriptionService: $this->creditSubscriptionService,
            itemCalculationFactory: $this->itemCalculationFactory,
        );

        $result = $calculator->calculateItem(
            request: $this->request,
            item: new RetentionOfferItemDTO(
                subscription: $this->subscription,
                selectedAction: SelectedAction::RF,
                executionDate: ExecutionDate::IMMEDIATE,
                contractPeriod: 12,
                billingPeriod: 12,
                targetProduct: null,
                cancelReason: null,
                cancelReasonOther: null,
            ),
        );

        self::assertSame(
            RetentionOfferCalculationStatus::INELIGIBLE,
            $result->status,
        );
        self::assertSame(
            'RF requires a cancellation reason.',
            $result->reason,
        );
    }

    #[Test]
    public function mapsInvalidEffectiveDateExceptionToIneligibleResult(): void
    {
        $effectiveDateCalculator = self::createStub(
            RetentionEffectiveDateCalculator::class,
        );
        $effectiveDateCalculator
            ->method('calculate')
            ->willThrowException(
                new InvalidRetentionEffectiveDateException('Invalid date.'),
            );

        $calculator = new RetentionOfferPriceCalculator(
            eligibilityService: $this->eligibilityService,
            priceResolver: $this->priceResolver,
            effectiveDateCalculator: $effectiveDateCalculator,
            creditSubscriptionService: $this->creditSubscriptionService,
            itemCalculationFactory: $this->itemCalculationFactory,
        );

        $request = new RetentionOfferRequestDTO(
            customer: $this->request->customer,
            customerType: CustomerType::BUSINESS,
            puzzelTicketId: '123456',
            items: [],
        );

        $result = $calculator->calculateItem(
            request: $request,
            item: new RetentionOfferItemDTO(
                subscription: $this->subscription,
                selectedAction: SelectedAction::TK_OPTION_2,
                executionDate: ExecutionDate::IMMEDIATE,
                contractPeriod: 12,
                billingPeriod: 12,
                targetProduct: null,
                cancelReason: null,
                cancelReasonOther: null,
            ),
        );

        self::assertSame(
            RetentionOfferCalculationStatus::INELIGIBLE,
            $result->status,
        );
        self::assertSame('Invalid date.', $result->reason);
    }

    #[Test]
    public function calculatesCreditableBusinessRfUsingSelectedReason(): void
    {
        $eligibilityService = self::createStub(
            RetentionOfferEligibilityService::class,
        );
        $eligibilityService
            ->method('determineEligibility')
            ->willReturn(new RetentionOfferEligibilityResultDTO(
                code: RetentionOfferEligibilityCode::NO_PRICE_REQUIRED,
                reason: null,
            ));

        $effectiveDate = $this->subscription->end_date;

        $effectiveDateCalculator = self::createStub(
            RetentionEffectiveDateCalculator::class,
        );
        $effectiveDateCalculator->method('calculate')->willReturn($effectiveDate);

        $invoice = InvoiceFactory::new()->makeOne([
            'start_date' => CarbonImmutable::today(),
            'net_price' => 300,
        ]);

        $creditSubscriptionService = self::createMock(
            CreditSubscriptionService::class,
        );
        $creditSubscriptionService
            ->expects(self::once())
            ->method('getInvoiceLinesToCreditBatchFromDate')
            ->with(
                $this->subscription,
                $effectiveDate,
                SubscriptionCancelReason::REASON_DISSATISFIED,
            )
            ->willReturn(new InvoiceToCreditBatch([
                new InvoiceToCredit(invoice: $invoice),
            ]));

        $calculator = new RetentionOfferPriceCalculator(
            eligibilityService: $eligibilityService,
            priceResolver: $this->priceResolver,
            effectiveDateCalculator: $effectiveDateCalculator,
            creditSubscriptionService: $creditSubscriptionService,
            itemCalculationFactory: $this->itemCalculationFactory,
        );

        $request = new RetentionOfferRequestDTO(
            customer: $this->request->customer,
            customerType: CustomerType::BUSINESS,
            puzzelTicketId: '123456',
            items: [],
        );

        $result = $calculator->calculateItem(
            request: $request,
            item: new RetentionOfferItemDTO(
                subscription: $this->subscription,
                selectedAction: SelectedAction::RF,
                executionDate: ExecutionDate::CONTRACT_END,
                contractPeriod: 12,
                billingPeriod: 12,
                targetProduct: null,
                cancelReason: SubscriptionCancelReason::REASON_DISSATISFIED,
                cancelReasonOther: null,
            ),
        );

        self::assertNotNull($result->cancellationDate);
        self::assertSame(
            $effectiveDate->getTimestamp(),
            $result->cancellationDate->getTimestamp(),
        );
        self::assertSame(300, $result->creditTotal);
    }

    #[Test]
    public function doesNotPreviewCreditForNonCreditableRfReason(): void
    {
        $eligibilityService = self::createStub(
            RetentionOfferEligibilityService::class,
        );
        $eligibilityService
            ->method('determineEligibility')
            ->willReturn(new RetentionOfferEligibilityResultDTO(
                code: RetentionOfferEligibilityCode::NO_PRICE_REQUIRED,
                reason: null,
            ));

        $creditSubscriptionService = self::createMock(
            CreditSubscriptionService::class,
        );
        $creditSubscriptionService->expects(self::never())->method('getInvoiceLinesToCreditBatchFromDate');

        $calculator = new RetentionOfferPriceCalculator(
            eligibilityService: $eligibilityService,
            priceResolver: $this->priceResolver,
            effectiveDateCalculator: $this->effectiveDateCalculator,
            creditSubscriptionService: $creditSubscriptionService,
            itemCalculationFactory: $this->itemCalculationFactory,
        );

        $result = $calculator->calculateItem(
            request: $this->request,
            item: new RetentionOfferItemDTO(
                subscription: $this->subscription,
                selectedAction: SelectedAction::RF,
                executionDate: ExecutionDate::CONTRACT_END,
                contractPeriod: 12,
                billingPeriod: 12,
                targetProduct: null,
                cancelReason: SubscriptionCancelReason::REASON_TRANSFER,
                cancelReasonOther: null,
            ),
        );

        self::assertSame(0, $result->creditTotal);
    }

    #[Test]
    public function buildsImmediateConsumerOfferResult(): void
    {
        $debitInvoice = InvoiceFactory::new()->makeOne([
            'start_date' => CarbonImmutable::today(),
            'net_price' => 600,
        ]);

        $discountInvoice = InvoiceFactory::new()->makeOne([
            'start_date' => CarbonImmutable::today(),
            'net_price' => -100,
        ]);

        $effectiveDate = CarbonImmutable::today();

        $effectiveDateCalculator = self::createStub(
            RetentionEffectiveDateCalculator::class,
        );

        $effectiveDateCalculator->method('calculate')->willReturn($effectiveDate);

        $creditSubscriptionService = self::createStub(
            CreditSubscriptionService::class,
        );

        $creditSubscriptionService
            ->method('getInvoiceLinesToCreditBatchFromDate')
            ->willReturn(new InvoiceToCreditBatch([
                new InvoiceToCredit(invoice: $debitInvoice),
                new InvoiceToCredit(invoice: $discountInvoice),
            ]));

        $calculator = new RetentionOfferPriceCalculator(
            eligibilityService: $this->eligibilityService,
            priceResolver: $this->priceResolver,
            effectiveDateCalculator: $effectiveDateCalculator,
            creditSubscriptionService: $creditSubscriptionService,
            itemCalculationFactory: $this->itemCalculationFactory,
        );

        $result = $calculator->calculateItem(
            request: $this->request,
            item: new RetentionOfferItemDTO(
                subscription: $this->subscription,
                selectedAction: SelectedAction::TK_OPTION_5,
                executionDate: ExecutionDate::IMMEDIATE,
                contractPeriod: 12,
                billingPeriod: 12,
                targetProduct: null,
                cancelReason: null,
                cancelReasonOther: null,
            ),
        );

        self::assertNotNull($result->newContractStartDate);
        self::assertSame(
            $effectiveDate->getTimestamp(),
            $result->newContractStartDate->getTimestamp(),
        );

        self::assertNotNull($result->newContractEndDate);
        self::assertSame(
            $effectiveDate->addMonths(12)->getTimestamp(),
            $result->newContractEndDate->getTimestamp(),
        );

        self::assertSame(500, $result->creditTotal);
        self::assertSame(250, $result->payableAfterCredits);
        self::assertTrue($result->requiresNewInvoice);
        self::assertFalse($result->replacesFutureInvoice);
    }

    #[Test]
    public function contractEndOfferDoesNotRequireNewInvoice(): void
    {
        $result = $this->calculator->calculateItem(
            request: $this->request,
            item: new RetentionOfferItemDTO(
                subscription: $this->subscription,
                selectedAction: SelectedAction::TK_OPTION_5,
                executionDate: ExecutionDate::CONTRACT_END,
                contractPeriod: 12,
                billingPeriod: 12,
                targetProduct: null,
                cancelReason: null,
                cancelReasonOther: null,
            ),
        );

        self::assertFalse($result->requiresNewInvoice);
    }

    #[Test]
    public function businessOfferReplacesFutureInvoiceWithoutCredit(): void
    {
        $futureInvoice = InvoiceFactory::new()->makeOne([
            'start_date' => new CarbonImmutable('2027-01-01'),
            'net_price' => 1000,
        ]);

        $creditSubscriptionService = self::createStub(
            CreditSubscriptionService::class,
        );
        $creditSubscriptionService
            ->method('getInvoiceLinesToCreditBatchFromDate')
            ->willReturn(new InvoiceToCreditBatch([
                new InvoiceToCredit(invoice: $futureInvoice),
            ]));

        $calculator = new RetentionOfferPriceCalculator(
            eligibilityService: $this->eligibilityService,
            priceResolver: $this->priceResolver,
            effectiveDateCalculator: $this->effectiveDateCalculator,
            creditSubscriptionService: $creditSubscriptionService,
            itemCalculationFactory: $this->itemCalculationFactory,
        );

        $request = new RetentionOfferRequestDTO(
            customer: $this->request->customer,
            customerType: CustomerType::BUSINESS,
            puzzelTicketId: '123456',
            items: [],
        );

        $result = $calculator->calculateItem(
            request: $request,
            item: new RetentionOfferItemDTO(
                subscription: $this->subscription,
                selectedAction: SelectedAction::TK_OPTION_5,
                executionDate: ExecutionDate::CONTRACT_END,
                contractPeriod: 12,
                billingPeriod: 12,
                targetProduct: null,
                cancelReason: null,
                cancelReasonOther: null,
            ),
        );

        self::assertSame(0, $result->creditTotal);
        self::assertSame(750, $result->payableAfterCredits);
        self::assertTrue($result->replacesFutureInvoice);
        self::assertTrue($result->requiresNewInvoice);
    }

    /**
     * @param non-negative-int $normalPrice
     * @param non-negative-int $expectedOfferPrice
     * @param non-negative-int $expectedDiscount
     */
    #[DataProvider('offerPriceProvider')]
    #[Test]
    public function appliesActionPercentageAndRounding(
        SelectedAction $selectedAction,
        int $normalPrice,
        int $expectedOfferPrice,
        int $expectedDiscount,
    ): void {
        $this->price->regularPrice = $normalPrice;
        $this->price->calculatedPrice = $normalPrice;

        $result = $this->calculator->calculateItem(
            request: $this->request,
            item: new RetentionOfferItemDTO(
                subscription: $this->subscription,
                selectedAction: $selectedAction,
                executionDate: ExecutionDate::CONTRACT_END,
                contractPeriod: 12,
                billingPeriod: 12,
                targetProduct: null,
                cancelReason: null,
                cancelReasonOther: null,
            ),
        );

        self::assertNotNull($result->price);
        self::assertSame($expectedOfferPrice, $result->price->offerNetPrice);
        self::assertSame($expectedDiscount, $result->price->discountAmount);
    }

    /**
     * @return iterable<string, array{SelectedAction, int, int, int}>
     */
    public static function offerPriceProvider(): iterable
    {
        yield 'DM Option 1 rounds 75 percent' => [
            SelectedAction::DM_OPTION_1,
            1001,
            751,
            250,
        ];
        yield 'TK Option 2 rounds 85 percent' => [
            SelectedAction::TK_OPTION_2,
            1001,
            851,
            150,
        ];
        yield 'TK Option 3 rounds 80 percent' => [
            SelectedAction::TK_OPTION_3,
            1001,
            801,
            200,
        ];
        yield 'TK Option 5 rounds 75 percent' => [
            SelectedAction::TK_OPTION_5,
            1001,
            751,
            250,
        ];
        yield 'TK Option 6 rounds 50 percent' => [
            SelectedAction::TK_OPTION_6,
            1001,
            501,
            500,
        ];
    }

    /**
     * @param positive-int $contractPeriod
     * @param positive-int $billingPeriod
     */
    #[DataProvider('dgOptionOneAPeriodProvider')]
    #[Test]
    public function dgOptionOneAUsesTargetProductPrice(
        int $contractPeriod,
        int $billingPeriod,
    ): void {
        $this->price->billingPeriod = $billingPeriod;
        $this->price->regularPrice = 1000;
        $this->price->contractPeriod = $contractPeriod;
        $this->price->calculatedPrice = 800;

        $priceResolver = self::createMock(PriceResolver::class);
        $priceResolver
            ->expects(self::once())
            ->method('getPriceList')
            ->with(self::callback(function (PriceRequest $priceRequest) use ($contractPeriod, $billingPeriod): bool {
                $productPriceRequest = $priceRequest->productPriceRequests[0];

                self::assertSame(
                    $this->targetProduct,
                    $productPriceRequest->product,
                );
                self::assertSame($contractPeriod, $productPriceRequest->contractPeriod);
                self::assertSame($billingPeriod, $productPriceRequest->billingPeriod);

                return true;
            }))
            ->willReturn($this->targetPriceList);

        $calculator = new RetentionOfferPriceCalculator(
            eligibilityService: $this->eligibilityService,
            priceResolver: $priceResolver,
            effectiveDateCalculator: $this->effectiveDateCalculator,
            creditSubscriptionService: $this->creditSubscriptionService,
            itemCalculationFactory: $this->itemCalculationFactory,
        );

        $result = $calculator->calculateItem(
            request: $this->request,
            item: new RetentionOfferItemDTO(
                subscription: $this->subscription,
                selectedAction: SelectedAction::DG_OPTION_1A,
                executionDate: ExecutionDate::CONTRACT_END,
                contractPeriod: $contractPeriod,
                billingPeriod: $billingPeriod,
                targetProduct: $this->targetProduct,
                cancelReason: null,
                cancelReasonOther: null,
            ),
        );

        self::assertNotNull($result->price);
        self::assertSame(1000, $result->price->grossPrice);
        self::assertSame(800, $result->price->normalNetPrice);
        self::assertSame(800, $result->price->offerNetPrice);
        self::assertSame(0, $result->price->discountAmount);
    }

    /** @return iterable<string, array{positive-int, positive-int}> */
    public static function dgOptionOneAPeriodProvider(): iterable
    {
        yield '12 month contract billed monthly' => [12, 1];
        yield '12 month contract billed annually' => [12, 12];
        yield '24 month contract billed monthly' => [24, 1];
        yield '24 month contract billed annually' => [24, 12];
        yield '24 month contract billed all at once' => [24, 24];
        yield '36 month contract billed monthly' => [36, 1];
        yield '36 month contract billed annually' => [36, 12];
        yield '36 month contract billed all at once' => [36, 36];
    }

    /**
     * @param positive-int     $contractPeriod
     * @param positive-int     $billingPeriod
     * @param non-negative-int $annualRegularPrice
     * @param non-negative-int $annualCalculatedPrice
     * @param non-negative-int $termPrice
     * @param non-negative-int $expectedGrossPrice
     * @param non-negative-int $expectedNormalNetPrice
     * @param non-negative-int $expectedDiscount
     */
    #[DataProvider('validDgOptionOneDProvider')]
    #[Test]
    public function dgOptionOneDComparesBillingPeriodPriceWithAnnualRenewal(
        int $contractPeriod,
        int $billingPeriod,
        int $annualRegularPrice,
        int $annualCalculatedPrice,
        int $termPrice,
        int $expectedGrossPrice,
        int $expectedNormalNetPrice,
        int $expectedDiscount,
    ): void {
        $this->price->regularPrice = $annualRegularPrice;
        $this->price->calculatedPrice = $annualCalculatedPrice;
        $this->resolvedTargetProduct->prices->push(
            new Price(
                type: ProductPriceType::REGISTRATION,
                billingPeriod: $billingPeriod,
                productId: 1,
                productGroupUuid: 'uuid',
                regularPrice: $termPrice,
                contractPeriod: $contractPeriod,
                orderable: true,
                is_default: false,
                calculatedPrice: $termPrice,
            ),
        );

        $priceResolver = self::createMock(PriceResolver::class);
        $priceResolver
            ->expects(self::once())
            ->method('getPriceList')
            ->with(self::callback(
                function (PriceRequest $priceRequest) use ($contractPeriod, $billingPeriod): bool {
                    self::assertSame($this->targetProduct, $priceRequest->productPriceRequests[0]->product);
                    self::assertSame($contractPeriod, $priceRequest->productPriceRequests[0]->contractPeriod);
                    self::assertSame($billingPeriod, $priceRequest->productPriceRequests[0]->billingPeriod);

                    return true;
                },
            ))
            ->willReturn($this->targetPriceList);

        $calculator = new RetentionOfferPriceCalculator(
            eligibilityService: $this->eligibilityService,
            priceResolver: $priceResolver,
            effectiveDateCalculator: $this->effectiveDateCalculator,
            creditSubscriptionService: $this->creditSubscriptionService,
            itemCalculationFactory: $this->itemCalculationFactory,
        );

        $result = $calculator->calculateItem(
            request: $this->request,
            item: new RetentionOfferItemDTO(
                subscription: $this->subscription,
                selectedAction: SelectedAction::DG_OPTION_1D,
                executionDate: ExecutionDate::CONTRACT_END,
                contractPeriod: $contractPeriod,
                billingPeriod: $billingPeriod,
                targetProduct: $this->targetProduct,
                cancelReason: null,
                cancelReasonOther: null,
            ),
        );

        self::assertNotNull($result->price);
        self::assertSame($expectedGrossPrice, $result->price->grossPrice);
        self::assertSame($expectedNormalNetPrice, $result->price->normalNetPrice);
        self::assertSame($termPrice, $result->price->offerNetPrice);
        self::assertSame($expectedDiscount, $result->price->discountAmount);
    }

    /** @return iterable<string, array{int, int, int, int, int, int, int, int}> */
    public static function validDgOptionOneDProvider(): iterable
    {
        yield '12 month contract billed monthly rounds cents' => [
            12,
            1,
            1001,
            1001,
            75,
            83,
            83,
            8,
        ];
        yield '24 month contract billed annually' => [
            24,
            12,
            1000,
            1000,
            900,
            1000,
            1000,
            100,
        ];
        yield '24 month contract billed all at once' => [
            24,
            24,
            1000,
            1000,
            1800,
            2000,
            2000,
            200,
        ];
        yield '36 month contract billed all at once' => [
            36,
            36,
            1000,
            1000,
            2500,
            3000,
            3000,
            500,
        ];
        yield 'annual calculated price forms the comparison basis' => [
            24,
            24,
            1200,
            1000,
            1800,
            2400,
            2000,
            200,
        ];
    }

    #[Test]
    public function dgOptionOneDRequiresAnnualComparisonPrice(): void
    {
        $this->price->billingPeriod = 24;
        $this->price->regularPrice = 1800;
        $this->price->contractPeriod = 24;
        $this->price->calculatedPrice = 1800;

        $priceResolver = self::createStub(PriceResolver::class);
        $priceResolver->method('getPriceList')->willReturn($this->targetPriceList);

        $calculator = new RetentionOfferPriceCalculator(
            eligibilityService: $this->eligibilityService,
            priceResolver: $priceResolver,
            effectiveDateCalculator: $this->effectiveDateCalculator,
            creditSubscriptionService: $this->creditSubscriptionService,
            itemCalculationFactory: $this->itemCalculationFactory,
        );

        $result = $calculator->calculateItem(
            request: $this->request,
            item: new RetentionOfferItemDTO(
                subscription: $this->subscription,
                selectedAction: SelectedAction::DG_OPTION_1D,
                executionDate: ExecutionDate::CONTRACT_END,
                contractPeriod: 24,
                billingPeriod: 24,
                targetProduct: $this->targetProduct,
                cancelReason: null,
                cancelReasonOther: null,
            ),
        );

        self::assertSame(
            RetentionOfferCalculationStatus::MANUAL,
            $result->status,
        );
    }

    #[Test]
    public function dgOptionOneDRequiresPositiveDiscount(): void
    {
        $this->resolvedTargetProduct->prices->push(new Price(
            type: ProductPriceType::REGISTRATION,
            billingPeriod: 24,
            productId: 1,
            productGroupUuid: 'uuid',
            regularPrice: 2000,
            contractPeriod: 24,
            orderable: true,
            is_default: false,
            calculatedPrice: 2000,
        ));
        $priceResolver = self::createStub(PriceResolver::class);
        $priceResolver->method('getPriceList')->willReturn($this->targetPriceList);

        $calculator = new RetentionOfferPriceCalculator(
            eligibilityService: $this->eligibilityService,
            priceResolver: $priceResolver,
            effectiveDateCalculator: $this->effectiveDateCalculator,
            creditSubscriptionService: $this->creditSubscriptionService,
            itemCalculationFactory: $this->itemCalculationFactory,
        );

        $result = $calculator->calculateItem(
            request: $this->request,
            item: new RetentionOfferItemDTO(
                subscription: $this->subscription,
                selectedAction: SelectedAction::DG_OPTION_1D,
                executionDate: ExecutionDate::CONTRACT_END,
                contractPeriod: 24,
                billingPeriod: 24,
                targetProduct: $this->targetProduct,
                cancelReason: null,
                cancelReasonOther: null,
            ),
        );

        self::assertSame(
            RetentionOfferCalculationStatus::MANUAL,
            $result->status,
        );
    }

    #[Test]
    public function returnsMissingPriceWhenExactDowngradeCombinationIsUnavailable(): void
    {
        $priceResolver = self::createStub(PriceResolver::class);
        $priceResolver->method('getPriceList')->willReturn($this->targetPriceList);

        $calculator = new RetentionOfferPriceCalculator(
            eligibilityService: $this->eligibilityService,
            priceResolver: $priceResolver,
            effectiveDateCalculator: $this->effectiveDateCalculator,
            creditSubscriptionService: $this->creditSubscriptionService,
            itemCalculationFactory: $this->itemCalculationFactory,
        );

        $result = $calculator->calculateItem(
            request: $this->request,
            item: new RetentionOfferItemDTO(
                subscription: $this->subscription,
                selectedAction: SelectedAction::DG_OPTION_1A,
                executionDate: ExecutionDate::CONTRACT_END,
                contractPeriod: 36,
                billingPeriod: 12,
                targetProduct: $this->targetProduct,
                cancelReason: null,
                cancelReasonOther: null,
            ),
        );

        self::assertSame(
            RetentionOfferCalculationStatus::MANUAL,
            $result->status,
        );
        self::assertSame(
            RetentionOfferEligibilityCode::MISSING_PRICE,
            $result->price?->eligibility->code,
        );
    }

    #[Test]
    public function returnsMissingPriceWhenPriceResolutionFails(): void
    {
        $priceResolver = self::createStub(PriceResolver::class);
        $priceResolver
            ->method('getPriceList')
            ->willThrowException(
                new PriceResolvingException('Unable to resolve price.'),
            );

        $calculator = new RetentionOfferPriceCalculator(
            eligibilityService: $this->eligibilityService,
            priceResolver: $priceResolver,
            effectiveDateCalculator: $this->effectiveDateCalculator,
            creditSubscriptionService: $this->creditSubscriptionService,
            itemCalculationFactory: $this->itemCalculationFactory,
        );

        $result = $calculator->calculateItem(
            request: $this->request,
            item: new RetentionOfferItemDTO(
                subscription: $this->subscription,
                selectedAction: SelectedAction::TK_OPTION_2,
                executionDate: ExecutionDate::CONTRACT_END,
                contractPeriod: 12,
                billingPeriod: 12,
                targetProduct: null,
                cancelReason: null,
                cancelReasonOther: null,
            ),
        );

        self::assertSame(
            RetentionOfferCalculationStatus::MANUAL,
            $result->status,
        );
    }

    #[Test]
    public function resolvesPriceForSelectedContractAndBillingPeriods(): void
    {
        $this->subscription->contract_period = 24;
        $this->price->contractPeriod = 24;

        $priceResolver = self::createMock(PriceResolver::class);
        $priceResolver
            ->expects(self::once())
            ->method('getPriceList')
            ->with(self::callback(function (PriceRequest $priceRequest): bool {
                $productPriceRequest = $priceRequest->productPriceRequests[0];

                self::assertSame(
                    $this->subscription->customer,
                    $priceRequest->customer,
                );
                self::assertInstanceOf(
                    ProlongationPriceRequest::class,
                    $productPriceRequest,
                );
                self::assertSame(
                    $this->subscription->product,
                    $productPriceRequest->product,
                );
                self::assertSame(24, $productPriceRequest->contractPeriod);
                self::assertSame(12, $productPriceRequest->billingPeriod);

                return true;
            }))
            ->willReturn($this->priceList);

        $calculator = new RetentionOfferPriceCalculator(
            eligibilityService: $this->eligibilityService,
            priceResolver: $priceResolver,
            effectiveDateCalculator: $this->effectiveDateCalculator,
            creditSubscriptionService: $this->creditSubscriptionService,
            itemCalculationFactory: $this->itemCalculationFactory,
        );

        $calculator->calculateItem(
            request: $this->request,
            item: new RetentionOfferItemDTO(
                subscription: $this->subscription,
                selectedAction: SelectedAction::TK_OPTION_5,
                executionDate: ExecutionDate::CONTRACT_END,
                contractPeriod: 24,
                billingPeriod: 12,
                targetProduct: null,
                cancelReason: null,
                cancelReasonOther: null,
            ),
        );
    }
}
