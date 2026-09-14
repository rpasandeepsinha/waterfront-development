<?php

declare(strict_types=1);

namespace Tests\Domain\Subscriptions\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductAllowedChangeFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\DNS\Actions\ChangeDnsAction;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;
use Waterfront\Domain\Pricing\Models\SubscriptionPrice;
use Waterfront\Domain\Pricing\Repositories\PriceRepository;
use Waterfront\Domain\Pricing\Services\PricePersistService;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Products\Repositories\ProductSpecRepository;
use Waterfront\Domain\Products\Services\PriceService;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Events\SubscriptionChangedEvent;
use Waterfront\Domain\Subscriptions\Exceptions\DowngradeCancelException;
use Waterfront\Domain\Subscriptions\Exceptions\SubscriptionChangeException;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\ProductAllowedChangeRepository;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Domain\Subscriptions\Services\CancellationService;
use Waterfront\Domain\Subscriptions\Services\SubscriptionChangeService;
use Waterfront\Domain\Subscriptions\Services\SubscriptionTerminateService;

#[CoversClass(SubscriptionChangeService::class)]
#[AllowMockObjectsWithoutExpectations]
class SubscriptionChangeServiceTest extends IntegrationTestCase
{
    public const string DOMAIN = 'testdomain.com';

    private Customer $customer;

    private Subscription $subscription;

    private Product $basicHostingProduct;

    private Product $premiumHostingProduct;

    private Product $superHostingProduct;

    private SubscriptionChangeService $subscriptionChangeService;

    private SubscriptionChangeService $subscriptionChangeServiceWithMocks;

    private ProductAllowedChangeRepository&MockObject $allowedChangeRepositoryMock;

    private ProductSpecRepository&MockObject $productSpecRepositoryMock;

    private ProductRepository&MockObject $productRepositoryMock;

    private LoggerInterface&MockObject $loggerMock;

    private ProductGroup $hostingProductGroup;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionChangeService = self::resolve(SubscriptionChangeService::class);

        $this->allowedChangeRepositoryMock = self::createMock(ProductAllowedChangeRepository::class);
        $this->productSpecRepositoryMock = self::createMock(ProductSpecRepository::class);
        $this->productRepositoryMock = self::createMock(ProductRepository::class);
        $this->loggerMock = self::createMock(LoggerInterface::class);

        $this->subscriptionChangeServiceWithMocks = new SubscriptionChangeService(
            eventDispatcher: self::createStub(Dispatcher::class),
            mailer: self::createStub(MailerInterface::class),
            productSpecRepository: $this->productSpecRepositoryMock,
            subscriptionRepo: self::createStub(SubscriptionRepository::class),
            allowedChangeRepository: $this->allowedChangeRepositoryMock,
            productRepository: $this->productRepositoryMock,
            logger: $this->loggerMock,
            priceService: self::resolve(PriceService::class),
            changeDnsAction: self::resolve(ChangeDnsAction::class),
            cancellationService: self::resolve(CancellationService::class),
            terminateService: self::resolve(SubscriptionTerminateService::class),
            priceResolver: self::resolve(PriceResolver::class),
            priceRepository: self::resolve(PriceRepository::class),
            pricePersistService: self::resolve(PricePersistService::class),
        );

        $this->customer = new CustomerFactory()->createOne();

        $this->hostingProductGroup = new ProductGroupFactory()->hosting()->createOne();

        // "Basic" hosting product.
        $this->basicHostingProduct = new ProductFactory()->for($this->hostingProductGroup)->createOne([
            'name' => 'basic',
        ]);
        new ProductPriceComponentFactory()
            ->for($this->basicHostingProduct)
            ->prolongation()
            ->createOne([
                'price' => 100,
                'billing_period' => 12,
                'contract_period' => 12,
            ]);
        new ProductPriceComponentFactory()
            ->for($this->basicHostingProduct)
            ->registration()
            ->createOne([
                'price' => 100,
                'billing_period' => 12,
                'contract_period' => 12,
            ]);
        new ProductPriceComponentFactory()->for($this->basicHostingProduct)->createOne([
            'type' => PriceComponentType::PROMOTION,
            'price' => 0,
        ]);

        // "Premium" hosting product.
        $this->premiumHostingProduct = new ProductFactory()
            ->for($this->hostingProductGroup)
            ->has(
                new ProductSpecFactory()->state([
                    'name' => ProductSpecName::PRODUCT_DOWNGRADE_WHEN_CANCELED,
                    'value' => 'product-slug',
                ]),
            )
            ->createOne([
                'name' => 'premium',
            ]);
        new ProductPriceComponentFactory()
            ->for($this->premiumHostingProduct)
            ->prolongation()
            ->createOne([
                'price' => 105,
                'billing_period' => 12,
                'contract_period' => 12,
            ]);

        $currentProductPrice = new ProductPriceComponentFactory()
            ->for($this->premiumHostingProduct)
            ->registration()
            ->createOne([
                'price' => 105,
                'billing_period' => 12,
                'contract_period' => 12,
            ]);
        new ProductPriceComponentFactory()->for($this->premiumHostingProduct)->createOne([
            'type' => PriceComponentType::PROMOTION,
            'price' => 0,
        ]);

        // "Super" hosting product.
        $this->superHostingProduct = new ProductFactory()->for($this->hostingProductGroup)->createOne([
            'name' => 'super',
        ]);
        new ProductPriceComponentFactory()
            ->for($this->superHostingProduct)
            ->prolongation()
            ->createOne([
                'price' => 110,
                'billing_period' => 12,
                'contract_period' => 12,
            ]);
        new ProductPriceComponentFactory()
            ->for($this->superHostingProduct)
            ->registration()
            ->createOne([
                'price' => 110,
                'billing_period' => 12,
                'contract_period' => 12,
            ]);
        new ProductPriceComponentFactory()->for($this->superHostingProduct)->createOne([
            'type' => PriceComponentType::PROMOTION,
            'price' => 0,
        ]);

        $provider = new ProviderFactory()->createOne([
            'type' => ProviderType::HOSTING,
            'slug' => ProviderSlug::PLESK,
            'enabled' => true,
            'default' => true,
        ]);

        // Initial subscription with the "Premium" hosting product.
        $this->subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->premiumHostingProduct)
            ->has(
                new HostingDeploymentFactory()->for($provider, 'provider')->for(new ServerFactory()),
            )
            ->administrativeStatusActive()
            ->createOne([
                'technical_status' => TechnicalStatus::OK->value,
                'domain' => self::DOMAIN,
                'product_uuid' => $this->premiumHostingProduct->uuid,
                'start_date' => CarbonImmutable::now(),
                'end_date' => CarbonImmutable::now()->addYear(),
                'contract_period' => 12,
                'net_price' => $currentProductPrice->price,
            ]);

        $this->subscription->refresh();
    }

    #[Test]
    public function shouldDowngradeSubscription(): void
    {
        $parentSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->for($this->hostingProductGroup))
            ->createOne();

        $withParentNoSpecSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->for($this->hostingProductGroup))
            ->for($parentSubscription, 'parent')
            ->createOne();

        $withParentWithSpecWithExpiredParentSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(
                new ProductFactory()->for($this->hostingProductGroup)->has(
                    new ProductSpecFactory()->state([
                        'name' => ProductSpecName::PRODUCT_DOWNGRADE_WHEN_CANCELED->value,
                        'value' => 'product-slug',
                    ]),
                ),
            )
            ->for(
                new SubscriptionFactory()
                    ->withCustomer()
                    ->for(new ProductFactory()->for($this->hostingProductGroup))
                    ->createOne([
                        'end_date' => CarbonImmutable::now()->subDay(),
                        'administrative_status' => AdministrativeStatus::EXPIRED->value,
                    ]),
                'parent',
            )
            ->createOne();

        $withParentWithSpecWithoutExpiredParentSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(
                new ProductFactory()->for($this->hostingProductGroup)->has(
                    new ProductSpecFactory()->state([
                        'name' => ProductSpecName::PRODUCT_DOWNGRADE_WHEN_CANCELED->value,
                        'value' => true,
                    ]),
                ),
            )
            ->for($parentSubscription, 'parent')
            ->createOne();

        self::assertFalse($this->subscriptionChangeService->shouldDowngradeSubscriptionWithParent($parentSubscription));
        self::assertFalse($this->subscriptionChangeService->shouldDowngradeSubscriptionWithParent(
            $withParentNoSpecSubscription,
        ));
        self::assertFalse($this->subscriptionChangeService->shouldDowngradeSubscriptionWithParent(
            $withParentWithSpecWithExpiredParentSubscription,
        ));
        self::assertTrue($this->subscriptionChangeService->shouldDowngradeSubscriptionWithParent(
            $withParentWithSpecWithoutExpiredParentSubscription,
        ));
    }

    #[Test]
    public function shouldDowngradeSubscriptionButNotMail(): void
    {
        $subscriptionChangeService = new SubscriptionChangeService(
            self::createMock(Dispatcher::class),
            $mailer = self::createMock(MailerInterface::class),
            self::createMock(ProductSpecRepository::class),
            self::createMock(SubscriptionRepository::class),
            $productAllowedChangeRepository = self::createMock(ProductAllowedChangeRepository::class),
            self::createMock(ProductRepository::class),
            self::createMock(LoggerInterface::class),
            self::resolve(PriceService::class),
            self::resolve(ChangeDnsAction::class),
            self::resolve(CancellationService::class),
            self::resolve(SubscriptionTerminateService::class),
            self::resolve(PriceResolver::class),
            self::resolve(PriceRepository::class),
            self::resolve(PricePersistService::class),
        );

        $productAllowedChangeRepository
            ->expects(self::once())
            ->method('getPotentialDowngrades')
            ->willReturn(new Collection([$this->basicHostingProduct]));

        $mailer->expects(self::never())->method('send');

        $subscriptionChangeService->change(
            changeType: ProductChangeType::DOWNGRADE,
            subscription: $this->subscription,
            newProduct: $this->basicHostingProduct,
            sendMail: false,
        );

        self::assertSame($this->subscription->product->uuid, $this->basicHostingProduct->uuid);
    }

    #[Test]
    public function shouldDowngradeSubscriptionButNotCreateCreditInvoice(): void
    {
        $subscriptionChangeService = new SubscriptionChangeService(
            $eventDispatcher = self::createMock(Dispatcher::class),
            self::createMock(MailerInterface::class),
            self::createMock(ProductSpecRepository::class),
            self::createMock(SubscriptionRepository::class),
            $productAllowedChangeRepository = self::createMock(ProductAllowedChangeRepository::class),
            self::createMock(ProductRepository::class),
            self::createMock(LoggerInterface::class),
            self::resolve(PriceService::class),
            self::resolve(ChangeDnsAction::class),
            self::resolve(CancellationService::class),
            self::resolve(SubscriptionTerminateService::class),
            self::resolve(PriceResolver::class),
            self::resolve(PriceRepository::class),
            self::resolve(PricePersistService::class),
        );

        $productAllowedChangeRepository
            ->expects(self::once())
            ->method('getPotentialDowngrades')
            ->willReturn(new Collection([$this->basicHostingProduct]));

        $eventDispatcher->expects(self::never())->method('dispatch');

        $subscriptionChangeService->change(
            changeType: ProductChangeType::DOWNGRADE,
            subscription: $this->subscription,
            newProduct: $this->basicHostingProduct,
            invoiceTheChange: false,
        );

        self::assertSame($this->subscription->product->uuid, $this->basicHostingProduct->uuid);
    }

    #[Test]
    public function changeSubscriptionFailedSameProduct(): void
    {
        $this->expectException(SubscriptionChangeException::class);
        $this->expectExceptionMessageIs(
            SubscriptionChangeException::noPotentialProducts(
                subscriptionUuid: $this->subscription->uuid,
                productName: $this->premiumHostingProduct->name,
            )->getMessage(),
        );

        // This will fail because the current subscription already has the "Premium" hosting product.
        $this->subscriptionChangeService->change(
            changeType: ProductChangeType::DOWNGRADE,
            subscription: $this->subscription,
            newProduct: $this->premiumHostingProduct,
        );

        self::assertDatabaseMissing('subscription_changes', [
            'subscription_uuid' => $this->subscription->uuid,
            'from_product_uuid' => $this->basicHostingProduct->uuid,
            'to_product_uuid' => $this->basicHostingProduct->uuid,
        ]);
    }

    /**
     * @throws SubscriptionChangeException
     */
    #[Test]
    public function downgradeChargeSuccessful(): void
    {
        ProductAllowedChangeFactory::new()->downgradeChange()->create([
            'from_product_id' => $this->subscription->product->id,
            'to_product_id' => $this->basicHostingProduct->id,
        ]);

        $newProductPrice = $this->subscriptionChangeService->getNewProductPrice(
            changeType: ProductChangeType::DOWNGRADE,
            subscription: $this->subscription,
            newProduct: $this->basicHostingProduct,
        );

        $result = $this->subscriptionChangeService->charge(
            changeType: ProductChangeType::DOWNGRADE,
            subscriptions: [$this->subscription],
            newProductPrice: $newProductPrice,
        );

        self::assertSame(0, $result);
    }

    #[DataProvider('chargeCorrectRatioData')]
    #[Test]
    public function chargeCorrectRatioPriceForUpgrade(
        int $billingPeriod,
        int $contractPeriod,
        CarbonImmutable $startDate,
        int $daysElapsed,
        int $expectedChargePrice,
    ): void {
        $oldPrice = 100;
        $newPrice = 1000;

        $endDate = $startDate->addMonths($contractPeriod);
        CarbonImmutable::setTestNow($startDate);

        $customer = new CustomerFactory()->createOne();

        $product1 = new ProductFactory()->for($this->hostingProductGroup)->createOne();

        $product2 = new ProductFactory()->for($this->hostingProductGroup)->createOne();

        new ProductPriceComponentFactory()
            ->for($product2)
            ->prolongation()
            ->createOne([
                'price' => $newPrice,
                'billing_period' => $billingPeriod,
                'contract_period' => $contractPeriod,
            ]);

        ProductAllowedChangeFactory::new()->upgradeChange()->create([
            'from_product_id' => $product1->id,
            'to_product_id' => $product2->id,
        ]);

        $subscription = new SubscriptionFactory()
            ->for($customer)
            ->for($product1)
            ->administrativeStatusActive()
            ->createOne([
                'start_date' => $startDate,
                'end_date' => $endDate,
                'contract_period' => $contractPeriod,
                'billing_period' => $billingPeriod,
                'gross_price' => $oldPrice,
                'net_price' => $oldPrice,
            ]);

        $subscriptionPrice = new SubscriptionPrice();
        $subscriptionPrice->subscription_id = $subscription->id;
        $subscriptionPrice->valid_from = $startDate;
        $subscriptionPrice->net_price = $oldPrice;
        $subscriptionPrice->save();

        $subscription->subscription_price_id = $subscriptionPrice->id;
        $subscription->save();

        $subscription->refresh();

        self::assertInstanceOf(SubscriptionPrice::class, $subscription->activePrice);
        $activePriceBeforeUpgrade = clone $subscription->activePrice;

        CarbonImmutable::setTestNow($startDate->addDays($daysElapsed));

        $this->subscriptionChangeService->change(
            changeType: ProductChangeType::UPGRADE,
            subscription: $subscription,
            newProduct: $product2,
        );

        self::assertSame($newPrice, $subscription->net_price);

        $invoice = $subscription->invoices()->latest()->firstOrFail();

        self::assertSame($expectedChargePrice, $invoice->net_price);
        self::assertSame($subscription->next_billing_date->toDateString(), $invoice->end_date->toDateString());

        $activePriceAfterUpgrade = clone $subscription->activePrice;

        self::assertNotSame($activePriceBeforeUpgrade, $activePriceAfterUpgrade);
        self::assertSame($newPrice, $activePriceAfterUpgrade->net_price);

        CarbonImmutable::setTestNow();
    }

    /**
     * @return iterable<string, mixed>
     */
    public static function chargeCorrectRatioData(): iterable
    {
        yield 'Upgrade year contract after 180 days in the period' => [
            'billingPeriod' => 12,
            'contractPeriod' => 12,
            'startDate' => CarbonImmutable::createFromFormat('Y-m-d', '2025-02-20'),
            'daysElapsed' => 180,
            'expectedChargePrice' => 456,
        ];

        yield 'Upgrade year contract after 180 days in the period (leap year)' => [
            'billingPeriod' => 12,
            'contractPeriod' => 12,
            'startDate' => CarbonImmutable::createFromFormat('Y-m-d', '2028-02-20'),
            'daysElapsed' => 180,
            'expectedChargePrice' => 457,
        ];

        yield 'Upgrade year contract after 100 days in the period' => [
            'billingPeriod' => 12,
            'contractPeriod' => 12,
            'startDate' => CarbonImmutable::createFromFormat('Y-m-d', '2025-02-20'),
            'daysElapsed' => 100,
            'expectedChargePrice' => 653,
        ];

        yield 'Upgrade year contract after 100 days in the period (leap year)' => [
            'billingPeriod' => 12,
            'contractPeriod' => 12,
            'startDate' => CarbonImmutable::createFromFormat('Y-m-d', '2028-02-20'),
            'daysElapsed' => 100,
            'expectedChargePrice' => 654,
        ];

        yield 'Upgrade month contract after 17 days of the period' => [
            'billingPeriod' => 1,
            'contractPeriod' => 1,
            'startDate' => CarbonImmutable::createFromFormat('Y-m-d', '2025-06-19'),
            'daysElapsed' => 17,
            'expectedChargePrice' => 390,
        ];

        yield 'Upgrade month contract after 20 days in the period (February no leap)' => [
            'billingPeriod' => 1,
            'contractPeriod' => 1,
            'startDate' => CarbonImmutable::createFromFormat('Y-m-d', '2025-02-19'),
            'daysElapsed' => 20,
            'expectedChargePrice' => 257,
        ];

        yield 'Upgrade month contract after 20 days in the period (February leap)' => [
            'billingPeriod' => 1,
            'contractPeriod' => 1,
            'startDate' => CarbonImmutable::createFromFormat('Y-m-d', '2028-02-19'),
            'daysElapsed' => 20,
            'expectedChargePrice' => 279,
        ];

        yield 'Upgrade 12/1 contract after 1 day' => [
            'billingPeriod' => 1,
            'contractPeriod' => 12,
            'startDate' => CarbonImmutable::createFromFormat('Y-m-d', '2026-09-09'),
            'daysElapsed' => 1,
            'expectedChargePrice' => 870,
        ];
    }

    /**
     * @throws SubscriptionChangeException
     */
    #[Test]
    public function getNewProductPrice(): void
    {
        ProductAllowedChangeFactory::new()->downgradeChange()->create([
            'from_product_id' => $this->subscription->product->id,
            'to_product_id' => $this->superHostingProduct->id,
        ]);

        $this->allowedChangeRepositoryMock
            ->expects(self::once())
            ->method('getPotentialUpgrades')
            ->willReturn(new Collection([
                $this->superHostingProduct,
                $this->basicHostingProduct,
            ]));

        $newPrice = $this->subscriptionChangeServiceWithMocks->getNewProductPrice(
            changeType: ProductChangeType::UPGRADE,
            subscription: $this->subscription,
            newProduct: $this->superHostingProduct,
        );

        self::assertSame(110, $newPrice->calculatedPrice);
    }

    #[Test]
    public function getNewProductPriceNoPotentialsFound(): void
    {
        $newProduct = new ProductFactory()->for($this->hostingProductGroup)->createOne();

        $this->allowedChangeRepositoryMock
            ->expects(self::once())
            ->method('getPotentialUpgrades')
            ->willReturn(new Collection([]));

        $this->expectException(SubscriptionChangeException::class);
        $this->expectExceptionMessageIs(
            sprintf(
                'Unable to change subscription with UUID "%s" to product "%s". No potential up- or downgrades found.',
                $this->subscription->uuid,
                $newProduct->name,
            ),
        );

        $this->subscriptionChangeServiceWithMocks->getNewProductPrice(
            changeType: ProductChangeType::UPGRADE,
            subscription: $this->subscription,
            newProduct: $newProduct,
        );
    }

    #[Test]
    public function getPotentialChanges(): void
    {
        $this->allowedChangeRepositoryMock
            ->expects(self::once())
            ->method('getPotentialUpgradesForCustomer')
            ->willReturn(new Collection([
                $this->basicHostingProduct,
                $this->superHostingProduct,
            ]));
        $this->allowedChangeRepositoryMock
            ->expects(self::exactly(2))
            ->method('getPotentialUpgrades')
            ->willReturn(new Collection([
                $this->basicHostingProduct,
                $this->superHostingProduct,
            ]));

        $basicPriceMock = ProductPriceComponent::where('product_id', $this->basicHostingProduct->id)
            ->where('type', PriceComponentType::PROLONGATION)
            ->first();

        $superPriceMock = ProductPriceComponent::where('product_id', $this->superHostingProduct->id)
            ->where('type', PriceComponentType::PROLONGATION)
            ->first();

        $this->loggerMock->expects(self::never())->method('info');

        $potentialChanges = $this->subscriptionChangeServiceWithMocks->getPotentialChanges(
            changeType: ProductChangeType::UPGRADE,
            subscription: $this->subscription,
        );

        /** @var array<int, array<string,int>> $priceInfo */
        $priceInfo = $potentialChanges->select('full_charge', 'charge');
        self::assertSame($priceInfo[0]['full_charge'], $basicPriceMock?->price);
        self::assertSame(0, $priceInfo[0]['charge']);
        self::assertSame($priceInfo[1]['full_charge'], $superPriceMock?->price);
        self::assertSame(5, $priceInfo[1]['charge']);
    }

    /**
     * @throws DowngradeCancelException
     */
    #[Test]
    public function getAvailableDowngradeWhenCanceled(): void
    {
        $testProduct = new ProductFactory()->for($this->hostingProductGroup)->createOne();
        $productSpecMock = ProductSpecFactory::new()->create([
            'product_id' => $this->subscription->product->id,
            'name' => ProductSpecName::PRODUCT_DOWNGRADE_WHEN_CANCELED,
            'value' => $testProduct->slug,
        ]);

        $this->productSpecRepositoryMock
            ->expects(self::once())
            ->method('findBySpecification')
            ->willReturn($productSpecMock);

        $this->productRepositoryMock
            ->expects(self::once())
            ->method('findProductBySlug')
            ->with($testProduct->slug)
            ->willReturn($testProduct);

        $this->allowedChangeRepositoryMock->expects(self::once())->method('isProductChangeAllowed')->willReturn(true);

        $productResult = $this->subscriptionChangeServiceWithMocks->getAvailableDowngradeWhenCanceled($this->subscription);
        self::assertSame($testProduct, $productResult);
    }

    #[Test]
    public function getAvailableDowngradeWhenCanceledFailedMissingSpec(): void
    {
        $this->productSpecRepositoryMock->expects(self::once())->method('findBySpecification')->willReturn(null);

        $this->expectException(DowngradeCancelException::class);
        $this->expectExceptionMessageIs(
            sprintf(
                'Downgrade not possible for product "%s" with %d due to the absence of the correct product spec %s',
                $this->subscription->product->slug,
                $this->subscription->product->id,
                ProductSpecName::PRODUCT_DOWNGRADE_WHEN_CANCELED->value,
            ),
        );
        $this->subscriptionChangeServiceWithMocks->getAvailableDowngradeWhenCanceled($this->subscription);
    }

    /**
     * @throws DowngradeCancelException
     */
    #[Test]
    public function getAvailableDowngradeWhenCanceledFailedProductNotExists(): void
    {
        ProductSpecFactory::new()->create([
            'product_id' => $this->subscription->product->id,
            'name' => ProductSpecName::PRODUCT_DOWNGRADE_WHEN_CANCELED,
            'value' => 'unknown-slug',
        ]);

        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessageIs('No query results for model [Waterfront\Domain\Products\Models\Product].');
        $this->subscriptionChangeService->getAvailableDowngradeWhenCanceled($this->subscription);
    }

    #[Test]
    public function getAvailableDowngradeWhenCanceledFailedDowngradeNotAllowed(): void
    {
        $testProduct = new ProductFactory()->for($this->hostingProductGroup)->createOne();
        $productSpecMock = ProductSpecFactory::new()->create([
            'product_id' => $this->subscription->product->id,
            'name' => ProductSpecName::PRODUCT_DOWNGRADE_WHEN_CANCELED,
            'value' => $testProduct->slug,
        ]);

        $this->productSpecRepositoryMock
            ->expects(self::once())
            ->method('findBySpecification')
            ->willReturn($productSpecMock);

        $this->productRepositoryMock
            ->expects(self::once())
            ->method('findProductBySlug')
            ->with($testProduct->slug)
            ->willReturn($testProduct);

        $this->allowedChangeRepositoryMock->expects(self::once())->method('isProductChangeAllowed')->willReturn(false);

        $this->expectException(DowngradeCancelException::class);
        $this->expectExceptionMessageIs(
            sprintf(
                'Downgrade not possible for product "%s": target product "%s" is not a downgrade possibility',
                $this->subscription->product->slug,
                $testProduct->slug,
            ),
        );

        $this->subscriptionChangeServiceWithMocks->getAvailableDowngradeWhenCanceled($this->subscription);
    }

    /**
     * @throws Exception
     * @throws SubscriptionChangeException
     */
    #[Test]
    public function changeSubscriptionFiresEvent(): void
    {
        $eventDispatcher = self::createMock(Dispatcher::class);
        $eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(SubscriptionChangedEvent::class));

        $this->allowedChangeRepositoryMock
            ->expects(self::once())
            ->method('getPotentialUpgrades')
            ->willReturn(new Collection([$this->superHostingProduct]));

        $service = new SubscriptionChangeService(
            $eventDispatcher,
            self::createMock(MailerInterface::class),
            self::createMock(ProductSpecRepository::class),
            self::createMock(SubscriptionRepository::class),
            $this->allowedChangeRepositoryMock,
            self::createMock(ProductRepository::class),
            logger: $this->loggerMock,
            priceService: self::resolve(PriceService::class),
            changeDnsAction: self::resolve(ChangeDnsAction::class),
            cancellationService: self::resolve(CancellationService::class),
            terminateService: self::resolve(SubscriptionTerminateService::class),
            priceResolver: self::resolve(PriceResolver::class),
            priceRepository: self::resolve(PriceRepository::class),
            pricePersistService: self::resolve(PricePersistService::class),
        );

        $service->change(
            changeType: ProductChangeType::UPGRADE,
            subscription: $this->subscription,
            newProduct: $this->superHostingProduct,
        );
    }

    #[Test]
    public function terminatePreviouslyPaidSubscriptionForProductThatIsFreeAfterUpgrade(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::create(2023));

        $servicePlusProduct = new ProductFactory()->for($this->hostingProductGroup)->createOne();
        $premiumHostingProduct = new ProductFactory()->for($this->hostingProductGroup)->createOne();
        $premiumPrice = new ProductPriceComponentFactory()
            ->prolongation()
            ->for($premiumHostingProduct)
            ->createOne(['price' => 40000]);
        new ProductSpecFactory()->for($premiumHostingProduct)->create([
            'name' => ProductSpecName::COMES_WITH_FREE_PRODUCT_SLUG,
            'value' => $servicePlusProduct->slug,
        ]);
        $hostingSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->basicHostingProduct)
            ->createOne([
                'billing_period' => 12,
                'contract_period' => 12,
                'net_price' => 18000,
            ]);
        $serviceSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($servicePlusProduct)
            ->createOne([
                'billing_period' => 12,
                'contract_period' => 12,
                'net_price' => 11988,
            ]);
        $hostingSubscription->children()->save($serviceSubscription);
        new ProductAllowedChangeFactory()->createOne([
            'change_type' => ProductChangeType::UPGRADE,
            'from_product_id' => $this->basicHostingProduct->id,
            'to_product_id' => $premiumHostingProduct->id,
        ]);

        $this->travel(4)->months();

        $mailer = self::createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send');

        $eventDispatcher = self::createMock(Dispatcher::class);
        $eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(
                self::callback(
                    fn (SubscriptionChangedEvent $event) => (
                        $event->subscription->id === $hostingSubscription->id
                        && $event->charge === 33570
                        && $event->changeType === ProductChangeType::UPGRADE
                    ),
                ),
            );

        $subscriptionChangeService = new SubscriptionChangeService(
            $eventDispatcher,
            $mailer,
            self::resolve(ProductSpecRepository::class),
            self::resolve(SubscriptionRepository::class),
            self::resolve(ProductAllowedChangeRepository::class),
            self::resolve(ProductRepository::class),
            self::resolve(LoggerInterface::class),
            self::resolve(PriceService::class),
            self::resolve(ChangeDnsAction::class),
            self::resolve(CancellationService::class),
            self::resolve(SubscriptionTerminateService::class),
            self::resolve(PriceResolver::class),
            self::resolve(PriceRepository::class),
            self::resolve(PricePersistService::class),
        );

        $subscriptionChangeService->change(ProductChangeType::UPGRADE, $hostingSubscription, $premiumHostingProduct);

        self::assertSame(AdministrativeStatus::ARCHIVED->value, $serviceSubscription->refresh()->administrative_status);
        self::assertSame($premiumHostingProduct->id, $hostingSubscription->product->id);
        // Let's make sure that the new product price is on the subscription, and not some variation of remaining price.
        self::assertSame($premiumPrice->price, $hostingSubscription->net_price);
    }

    #[Test]
    public function downgradeCanceled(): void
    {
        $freeDnsProduct = new ProductFactory()->freeDns()->createOne();

        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for(new ProductFactory()->nlDomain())
            ->administrativeStatusActive()
            ->createOne();

        $childSubscription = new SubscriptionFactory()
            ->forDomain($subscription->domain ?? '')
            ->for($this->customer)
            ->for(new ProductFactory()->premiumDns($freeDnsProduct->productGroup))
            ->administrativeStatusCancelled()
            ->createOne([
                'parent_subscription_id' => $subscription->id,
                'end_date' => new CarbonImmutable('yesterday'),
            ]);
        new ProductSpecFactory()->createOne([
            'name' => ProductSpecName::PRODUCT_DOWNGRADE_WHEN_CANCELED,
            'value' => $freeDnsProduct->slug,
            'product_id' => $childSubscription->product->id,
        ]);
        new ProductAllowedChangeFactory()->createOne([
            'change_type' => ProductChangeType::DOWNGRADE,
            'from_product_id' => $childSubscription->product->id,
            'to_product_id' => $freeDnsProduct->id,
        ]);
        new ProductPriceComponentFactory()
            ->prolongation()
            ->for($freeDnsProduct)
            ->createOne();

        $dnsAction = self::createMock(ChangeDnsAction::class);
        $dnsAction->expects(self::once())->method('execute');
        $eventDispatcher = self::createMock(Dispatcher::class);
        $eventDispatcher->expects(self::once())->method('dispatch');
        $mailer = self::createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send');

        $subscriptionChangeService = new SubscriptionChangeService(
            eventDispatcher: $eventDispatcher,
            mailer: $mailer,
            productSpecRepository: self::resolve(ProductSpecRepository::class),
            subscriptionRepo: self::resolve(SubscriptionRepository::class),
            allowedChangeRepository: self::resolve(ProductAllowedChangeRepository::class),
            productRepository: self::resolve(ProductRepository::class),
            logger: self::resolve(LoggerInterface::class),
            priceService: self::resolve(PriceService::class),
            changeDnsAction: $dnsAction,
            cancellationService: self::resolve(CancellationService::class),
            terminateService: self::resolve(SubscriptionTerminateService::class),
            priceResolver: self::resolve(PriceResolver::class),
            priceRepository: self::resolve(PriceRepository::class),
            pricePersistService: self::resolve(PricePersistService::class),
        );

        $subscriptionChangeService->downgradeCanceled($childSubscription->refresh());

        self::assertSame(AdministrativeStatus::ACTIVE->value, $subscription->administrative_status);
        self::assertSame(AdministrativeStatus::ACTIVE->value, $childSubscription->administrative_status);
        self::assertSame($freeDnsProduct->id, $childSubscription->product->id);
    }
}
