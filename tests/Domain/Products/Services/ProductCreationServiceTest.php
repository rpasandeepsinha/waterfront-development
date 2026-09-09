<?php

declare(strict_types=1);

namespace Tests\Domain\Products\Services;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Products\DTO\Configuration\AdditionalPriceDTO;
use Waterfront\Domain\Products\DTO\Configuration\AllowedChange;
use Waterfront\Domain\Products\DTO\Configuration\CreateProductDTO;
use Waterfront\Domain\Products\DTO\Configuration\IntroductionPriceConfigurationDTO;
use Waterfront\Domain\Products\DTO\Configuration\ProductAddonDTO;
use Waterfront\Domain\Products\DTO\Configuration\ProductPriceEntryDTO;
use Waterfront\Domain\Products\DTO\Configuration\Promotion;
use Waterfront\Domain\Products\DTO\Configuration\PromotionCallToAction;
use Waterfront\Domain\Products\DTO\Configuration\ShopConfig;
use Waterfront\Domain\Products\DTO\Configuration\Specifications;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductPromotionPlatform;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Products\Services\ProductCreationService;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Infra\Authentication\AuthorizationChecker;

#[CoversClass(ProductCreationService::class)]
class ProductCreationServiceTest extends IntegrationTestCase
{
    private ProductCreationService $productCreationService;

    private ProductGroup $productGroup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsEmployee();
        $this->productCreationService = self::resolve(ProductCreationService::class);

        $this->productGroup = new ProductGroupFactory()->extension()->createOne();
    }

    #[Test]
    public function creationSuccess(): void
    {
        $dto = new CreateProductDTO('test', 'test', null, ProductGroupType::EXTENSION, new ShopConfig(true), null, null, null, null, null, null);

        $this->productCreationService->storeProductLine($dto);

        self::assertDatabaseHas('products', [
            'slug' => 'test',
            'name' => 'test',
        ]);
    }

    #[Test]
    public function creationWithSpecificationsSuccess(): void
    {
        $dto = new CreateProductDTO('test', 'test', null, ProductGroupType::EXTENSION, new ShopConfig(true), [new Specifications('test.key', 'test.value')], null, null, null, null, null);

        $product = $this->productCreationService->storeProductLine($dto);

        self::assertDatabaseHas('products', [
            'slug' => 'test',
            'name' => 'test',
        ]);
        self::assertDatabaseHas('product_specs', [
            'product_id' => $product->id,
            'name' => 'test.key',
            'value' => 'test.value',
        ]);
    }

    #[Test]
    public function creationWithChangesSuccess(): void
    {
        $allowedUpgrade = new ProductFactory()->for($this->productGroup)->createOne(['slug' => 'wooop']);
        $allowedDowngrade = new ProductFactory()->for($this->productGroup)->createOne(['slug' => 'wooop2']);

        $upgradeDto = new AllowedChange('test', $allowedUpgrade->id, ProductChangeType::UPGRADE, 1, true);
        $downgradeDto = new AllowedChange('test', $allowedDowngrade->id, ProductChangeType::DOWNGRADE, 1, true);
        $dto = new CreateProductDTO(
            'test',
            'test',
            null,
            ProductGroupType::EXTENSION,
            new ShopConfig(true),
            [new Specifications('test.key', 'test.value')],
            [
                $upgradeDto,
                $downgradeDto,
            ],
            null,
            null,
            null,
            null
        );

        $product = $this->productCreationService->storeProductLine($dto);

        self::assertDatabaseHas('products', [
            'slug' => 'test',
            'name' => 'test',
        ]);
        self::assertDatabaseHas('product_allowed_changes', [
            'from_product_id' => $product->id,
            'to_product_id' => $allowedUpgrade->id,
            'change_type' => ProductChangeType::UPGRADE->value,
            'display_order' => $upgradeDto->order,
            'is_available_for_customer' => $upgradeDto->availabeForCustomer,
        ]);
        self::assertDatabaseHas('product_allowed_changes', [
            'from_product_id' => $product->id,
            'to_product_id' => $allowedDowngrade->id,
            'change_type' => ProductChangeType::DOWNGRADE->value,
            'display_order' => $downgradeDto->order,
            'is_available_for_customer' => $downgradeDto->availabeForCustomer,
        ]);
    }

    #[Test]
    public function creationWithSinglePromotionSuccess(): void
    {
        $callToAction = new PromotionCallToAction(
            title: 'Buy now',
            buttonText: 'Order',
            description: 'Great deal',
            destinationUrl: 'https://example.com',
            priceDescription: 'From €9.99',
        );

        $promotion = new Promotion(
            uuid: null,
            platform: ProductPromotionPlatform::CUSTOMER_PANEL,
            placementUrl: 'https://example.com/banner.png',
            startDate: CarbonImmutable::parse('2025-01-01'),
            endDate: CarbonImmutable::parse('2025-12-31'),
            callToAction: $callToAction,
            weight: 5,
        );

        $dto = new CreateProductDTO('test', 'test', null, ProductGroupType::EXTENSION, new ShopConfig(true), null, null, [$promotion], null, null, null);

        $product = $this->productCreationService->storeProductLine($dto);

        self::assertDatabaseHas('product_promotions', [
            'product_id' => $product->id,
            'platform' => ProductPromotionPlatform::CUSTOMER_PANEL->value,
            'placement_url' => 'https://example.com/banner.png',
            'weight' => 5,
        ]);
    }

    #[Test]
    public function creationWithMultiplePromotionsStoresAll(): void
    {
        $callToAction = new PromotionCallToAction(
            title: 'Buy now',
            buttonText: 'buyyy',
            description: 'Great deal',
            destinationUrl: 'https://example.com',
            priceDescription: 'From €9.99',
        );

        $firstPromotion = new Promotion(
            uuid: null,
            platform: ProductPromotionPlatform::CUSTOMER_PANEL,
            placementUrl: 'https://example.com',
            startDate: CarbonImmutable::parse('2025-01-01'),
            endDate: CarbonImmutable::parse('2025-06-30'),
            callToAction: $callToAction,
            weight: 1,
        );

        $secondPromotion = new Promotion(
            uuid: null,
            platform: ProductPromotionPlatform::CUSTOMER_PANEL,
            placementUrl: 'https://example.com',
            startDate: CarbonImmutable::parse('2025-07-01'),
            endDate: CarbonImmutable::parse('2025-12-31'),
            callToAction: $callToAction,
            weight: 2,
        );

        $dto = new CreateProductDTO('test', 'test', null, ProductGroupType::EXTENSION, new ShopConfig(true), null, null, [$firstPromotion, $secondPromotion], null, null, null);

        $product = $this->productCreationService->storeProductLine($dto);

        self::assertDatabaseHas('product_promotions', [
            'product_id' => $product->id,
            'placement_url' => 'https://example.com',
            'weight' => 1,
        ]);
        self::assertDatabaseHas('product_promotions', [
            'product_id' => $product->id,
            'placement_url' => 'https://example.com',
            'weight' => 2,
        ]);
        self::assertDatabaseCount('product_promotions', 2);
    }

    #[Test]
    public function creationWithSingleProductPriceSuccess(): void
    {
        $priceEntry = new ProductPriceEntryDTO(
            billingPeriod: 1,
            contractPeriod: 12,
            registrationPrice: 999,
            additionalPrices: null,
        );

        $dto = new CreateProductDTO('test', 'test', null, ProductGroupType::EXTENSION, new ShopConfig(true), null, null, null, [$priceEntry], null, null);

        $product = $this->productCreationService->storeProductLine($dto);

        self::assertDatabaseHas('product_price_components', [
            'product_id' => $product->id,
            'billing_period' => 1,
            'contract_period' => 12,
            'type' => PriceComponentType::REGISTRATION->value,
            'price' => 999,
            'orderable' => true,
        ]);
    }

    #[Test]
    public function creationWithProductPriceAndAdditionalPricesSuccess(): void
    {
        $priceEntry = new ProductPriceEntryDTO(
            billingPeriod: 12,
            contractPeriod: 24,
            registrationPrice: 1999,
            additionalPrices: [
                new AdditionalPriceDTO(type: PriceComponentType::INTRODUCTION, price: 499),
                new AdditionalPriceDTO(type: PriceComponentType::PROMOTION, price: 899),
                new AdditionalPriceDTO(type: PriceComponentType::PROLONGATION, price: 1499),
            ],
        );

        $dto = new CreateProductDTO('test', 'test', null, ProductGroupType::EXTENSION, new ShopConfig(true), null, null, null, [$priceEntry], null, null);

        $product = $this->productCreationService->storeProductLine($dto);

        self::assertDatabaseHas('product_price_components', [
            'product_id' => $product->id,
            'billing_period' => 12,
            'contract_period' => 24,
            'type' => PriceComponentType::REGISTRATION->value,
            'price' => 1999,
        ]);
        self::assertDatabaseHas('product_price_components', [
            'product_id' => $product->id,
            'billing_period' => 12,
            'contract_period' => 24,
            'type' => PriceComponentType::INTRODUCTION->value,
            'price' => 499,
        ]);
        self::assertDatabaseHas('product_price_components', [
            'product_id' => $product->id,
            'billing_period' => 12,
            'contract_period' => 24,
            'type' => PriceComponentType::PROMOTION->value,
            'price' => 899,
        ]);
        self::assertDatabaseHas('product_price_components', [
            'product_id' => $product->id,
            'billing_period' => 12,
            'contract_period' => 24,
            'type' => PriceComponentType::PROLONGATION->value,
            'price' => 1499,
        ]);
    }

    #[Test]
    public function creationWithMultipleProductPricesStoresAll(): void
    {
        $firstEntry = new ProductPriceEntryDTO(
            billingPeriod: 1,
            contractPeriod: 1,
            registrationPrice: 500,
            additionalPrices: null,
        );

        $secondEntry = new ProductPriceEntryDTO(
            billingPeriod: 12,
            contractPeriod: 12,
            registrationPrice: 5000,
            additionalPrices: null,
        );

        $dto = new CreateProductDTO('test', 'test', null, ProductGroupType::EXTENSION, new ShopConfig(true), null, null, null, [$firstEntry, $secondEntry], null, null);

        $product = $this->productCreationService->storeProductLine($dto);

        self::assertDatabaseHas('product_price_components', [
            'product_id' => $product->id,
            'billing_period' => 1,
            'price' => 500,
        ]);
        self::assertDatabaseHas('product_price_components', [
            'product_id' => $product->id,
            'billing_period' => 12,
            'price' => 5000,
        ]);
        self::assertDatabaseCount('product_price_components', 2);
    }

    #[Test]
    public function creationWithMultipleProductPricesStoresAllUniquePeriods(): void
    {
        $firstEntry = new ProductPriceEntryDTO(
            billingPeriod: 1,
            contractPeriod: 1,
            registrationPrice: 500,
            additionalPrices: null,
        );

        $secondEntry = new ProductPriceEntryDTO(
            billingPeriod: 1,
            contractPeriod: 1,
            registrationPrice: 5000,
            additionalPrices: null,
        );

        $thirdEntry = new ProductPriceEntryDTO(
            billingPeriod: 1,
            contractPeriod: 12,
            registrationPrice: 5000,
            additionalPrices: null,
        );

        $fourthEntry = new ProductPriceEntryDTO(
            billingPeriod: 12,
            contractPeriod: 12,
            registrationPrice: 5000,
            additionalPrices: null,
        );

        $dto = new CreateProductDTO(
            'test',
            'test',
            null,
            ProductGroupType::EXTENSION,
            new ShopConfig(true),
            null,
            null,
            null,
            [$firstEntry, $secondEntry, $thirdEntry, $fourthEntry],
            null,
            null
        );

        $product = $this->productCreationService->storeProductLine($dto);

        self::assertDatabaseHas('product_periods', [
            'product_id' => $product->id,
            'billing_period' => 1,
            'contract_period' => 1,
        ]);
        self::assertDatabaseHas('product_periods', [
            'product_id' => $product->id,
            'billing_period' => 1,
            'contract_period' => 12,
        ]);
        self::assertDatabaseHas('product_periods', [
            'product_id' => $product->id,
            'billing_period' => 12,
            'contract_period' => 12,
        ]);
        self::assertDatabaseCount('product_periods', 3);
    }

    #[Test]
    public function creationWithProlongationDifferentFromRegistrationCreatesBoth(): void
    {
        $firstEntry = new ProductPriceEntryDTO(
            billingPeriod: 1,
            contractPeriod: 1,
            registrationPrice: 500,
            additionalPrices: [new AdditionalPriceDTO(type: PriceComponentType::PROLONGATION, price: 750)],
        );

        $dto = new CreateProductDTO('test', 'test', null, ProductGroupType::EXTENSION, new ShopConfig(true), null, null, null, [$firstEntry], null, null);

        $product = $this->productCreationService->storeProductLine($dto);

        self::assertDatabaseHas('product_price_components', [
            'product_id' => $product->id,
            'type' => PriceComponentType::REGISTRATION,
            'billing_period' => 1,
            'price' => 500,
        ]);
        self::assertDatabaseHas('product_price_components', [
            'product_id' => $product->id,
            'type' => PriceComponentType::PROLONGATION,
            'billing_period' => 1,
            'price' => 750,
        ]);
        self::assertDatabaseCount('product_price_components', 2);
    }

    #[Test]
    public function creationWithProlongationSameAsRegistrationCreatesOnlyRegistration(): void
    {
        $firstEntry = new ProductPriceEntryDTO(
            billingPeriod: 1,
            contractPeriod: 1,
            registrationPrice: 500,
            additionalPrices: [new AdditionalPriceDTO(type: PriceComponentType::PROLONGATION, price: 500)],
        );

        $dto = new CreateProductDTO('test', 'test', null, ProductGroupType::EXTENSION, new ShopConfig(true), null, null, null, [$firstEntry], null, null);

        $product = $this->productCreationService->storeProductLine($dto);

        self::assertDatabaseHas('product_price_components', [
            'product_id' => $product->id,
            'type' => PriceComponentType::REGISTRATION,
            'billing_period' => 1,
            'price' => 500,
        ]);
        self::assertDatabaseCount('product_price_components', 1);
    }

    #[Test]
    public function creationWithAddonsCouplesTheAddonProducts(): void
    {
        $addonGroup = new ProductGroupFactory()->addon()->createOne();
        $firstAddon = new ProductFactory()->for($addonGroup)->createOne(['slug' => 'first-addon']);
        $secondAddon = new ProductFactory()->for($addonGroup)->createOne(['slug' => 'second-addon']);

        $dto = new CreateProductDTO('test', 'test', null, ProductGroupType::EXTENSION, new ShopConfig(true), null, null, null, null, [
            new ProductAddonDTO($firstAddon->id),
            new ProductAddonDTO($secondAddon->id),
        ], null);

        $product = $this->productCreationService->storeProductLine($dto);

        self::assertDatabaseHas('product_addon_coupling', [
            'parent_product_id' => $product->id,
            'addon_product_id' => $firstAddon->id,
        ]);
        self::assertDatabaseHas('product_addon_coupling', [
            'parent_product_id' => $product->id,
            'addon_product_id' => $secondAddon->id,
        ]);
        self::assertDatabaseCount('product_addon_coupling', 2);
    }

    #[Test]
    public function creationWithoutAddonsCouplesNothing(): void
    {
        $dto = new CreateProductDTO('test', 'test', null, ProductGroupType::EXTENSION, new ShopConfig(true), null, null, null, null, null, null);

        $this->productCreationService->storeProductLine($dto);

        self::assertDatabaseCount('product_addon_coupling', 0);
    }

    #[Test]
    public function creationWithIntroductionPriceConfigurationSuccess(): void
    {
        $configuration = new IntroductionPriceConfigurationDTO(
            contractPeriod: 12,
            maxUsesPerCustomer: 5,
            firstMonthsDiscountPeriod: 3,
        );

        $dto = new CreateProductDTO('test', 'test', null, ProductGroupType::EXTENSION, new ShopConfig(true), null, null, null, null, null, [$configuration]);

        $product = $this->productCreationService->storeProductLine($dto);

        self::assertDatabaseHas('product_introduction_discounts', [
            'product_id' => $product->id,
            'contract_period' => 12,
            'max_uses_per_customer' => 5,
            'first_months_discount_period' => 3,
            'deleted_at' => null,
        ]);
        self::assertDatabaseCount('product_introduction_discounts', 1);
    }

    #[Test]
    public function creationWithMultipleIntroductionPriceConfigurationsStoresAll(): void
    {
        $firstConfiguration = new IntroductionPriceConfigurationDTO(
            contractPeriod: 12,
            maxUsesPerCustomer: 1,
            firstMonthsDiscountPeriod: 6,
        );

        $secondConfiguration = new IntroductionPriceConfigurationDTO(
            contractPeriod: 24,
            maxUsesPerCustomer: 2,
            firstMonthsDiscountPeriod: 12,
        );

        $dto = new CreateProductDTO('test', 'test', null, ProductGroupType::EXTENSION, new ShopConfig(true), null, null, null, null, null, [$firstConfiguration, $secondConfiguration]);

        $product = $this->productCreationService->storeProductLine($dto);

        self::assertDatabaseHas('product_introduction_discounts', [
            'product_id' => $product->id,
            'contract_period' => 12,
            'max_uses_per_customer' => 1,
            'first_months_discount_period' => 6,
        ]);
        self::assertDatabaseHas('product_introduction_discounts', [
            'product_id' => $product->id,
            'contract_period' => 24,
            'max_uses_per_customer' => 2,
            'first_months_discount_period' => 12,
        ]);
        self::assertDatabaseCount('product_introduction_discounts', 2);
    }

    #[Test]
    public function creationWithIntroductionPriceConfigurationWithoutOptionalFieldsStoresNulls(): void
    {
        $configuration = new IntroductionPriceConfigurationDTO(
            contractPeriod: 12,
            maxUsesPerCustomer: null,
            firstMonthsDiscountPeriod: null,
        );

        $dto = new CreateProductDTO('test', 'test', null, ProductGroupType::EXTENSION, new ShopConfig(true), null, null, null, null, null, [$configuration]);

        $product = $this->productCreationService->storeProductLine($dto);

        self::assertDatabaseHas('product_introduction_discounts', [
            'product_id' => $product->id,
            'contract_period' => 12,
            'max_uses_per_customer' => null,
            'first_months_discount_period' => null,
        ]);
    }

    #[Test]
    public function creationWithoutIntroductionPriceConfigurationStoresNoDiscounts(): void
    {
        $dto = new CreateProductDTO('test', 'test', null, ProductGroupType::EXTENSION, new ShopConfig(true), null, null, null, null, null, null);

        $this->productCreationService->storeProductLine($dto);

        self::assertDatabaseCount('product_introduction_discounts', 0);
    }

    #[Test]
    public function creationWithIntroductionPriceConfigurationIsSkippedWithoutEditProductPricesPermission(): void
    {
        $authorizationChecker = self::createStub(AuthorizationChecker::class);
        $authorizationChecker->method('can')->willReturn(false);
        $this->app->bind(AuthorizationChecker::class, fn (): AuthorizationChecker => $authorizationChecker);

        $productCreationService = self::resolve(ProductCreationService::class);

        $configuration = new IntroductionPriceConfigurationDTO(
            contractPeriod: 12,
            maxUsesPerCustomer: 5,
            firstMonthsDiscountPeriod: 3,
        );

        $dto = new CreateProductDTO('test', 'test', null, ProductGroupType::EXTENSION, new ShopConfig(true), null, null, null, null, null, [$configuration]);

        $productCreationService->storeProductLine($dto);

        self::assertDatabaseCount('product_introduction_discounts', 0);
    }
}
