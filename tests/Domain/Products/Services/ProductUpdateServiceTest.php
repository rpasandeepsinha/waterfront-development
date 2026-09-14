<?php

declare(strict_types=1);

namespace Tests\Domain\Products\Services;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Tests\Factories\ProductAddonCouplingFactory;
use Tests\Factories\ProductAllowedChangeFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductIntroductionDiscountsFactory;
use Tests\Factories\ProductPeriodFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProductPromotionFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;
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
use Waterfront\Domain\Products\Services\ProductUpdateService;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;

#[CoversClass(ProductUpdateService::class)]
class ProductUpdateServiceTest extends IntegrationTestCase
{
    private ProductUpdateService $productUpdateService;

    private ProductGroup $productGroup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productUpdateService = self::resolve(ProductUpdateService::class);
        $this->productGroup = new ProductGroupFactory()->extension()->createOne();
    }

    #[Test]
    public function updateProductLineUpdatesBasicProductFields(): void
    {
        $product = new ProductFactory()->for($this->productGroup)->createOne([
            'slug' => 'test-product',
            'name' => 'Original Name',
        ]);

        $this->productUpdateService->updateProductLine($product, $this->makeDto(name: 'Updated Name'));

        self::assertDatabaseHas('products', [
            'uuid' => $product->uuid,
            'slug' => 'test-product',
            'name' => 'Updated Name',
        ]);
    }

    #[Test]
    public function updateSpecsUpdatesValueOfExistingSpec(): void
    {
        $product = new ProductFactory()
            ->for($this->productGroup)
            ->has(ProductSpecFactory::new()->state(['name' => 'bandwidth', 'value' => '10']))
            ->createOne(['slug' => 'test-product']);

        $this->productUpdateService->updateProductLine(
            $product,
            $this->makeDto(specifications: [new Specifications('bandwidth', '50')]),
        );

        self::assertDatabaseHas('product_specs', [
            'product_id' => $product->id,
            'name' => 'bandwidth',
            'value' => '50',
        ]);
        self::assertDatabaseCount('product_specs', 1);
    }

    #[Test]
    public function updateSpecsDeletesSpecNotInPayload(): void
    {
        $product = new ProductFactory()
            ->for($this->productGroup)
            ->has(ProductSpecFactory::new()->state(['name' => 'bandwidth', 'value' => '10']))
            ->has(ProductSpecFactory::new()->state(['name' => 'storage', 'value' => '100']))
            ->createOne(['slug' => 'test-product']);

        $this->productUpdateService->updateProductLine(
            $product,
            $this->makeDto(specifications: [new Specifications('bandwidth', '10')]),
        );

        self::assertDatabaseHas('product_specs', ['product_id' => $product->id, 'name' => 'bandwidth']);
        self::assertDatabaseMissing('product_specs', ['product_id' => $product->id, 'name' => 'storage']);
    }

    #[Test]
    public function updateSpecsCreatesNewSpec(): void
    {
        $product = new ProductFactory()->for($this->productGroup)->createOne(['slug' => 'test-product']);

        $this->productUpdateService->updateProductLine(
            $product,
            $this->makeDto(specifications: [new Specifications('bandwidth', '100')]),
        );

        self::assertDatabaseHas('product_specs', [
            'product_id' => $product->id,
            'name' => 'bandwidth',
            'value' => '100',
        ]);
    }

    #[Test]
    public function updateAllowedChangesUpdatesExistingChange(): void
    {
        $targetProduct = new ProductFactory()->for($this->productGroup)->createOne();
        $product = new ProductFactory()->for($this->productGroup)->createOne(['slug' => 'test-product']);

        new ProductAllowedChangeFactory()->createOne([
            'from_product_id' => $product->id,
            'to_product_id' => $targetProduct->id,
            'change_type' => ProductChangeType::UPGRADE,
            'display_order' => 1,
            'is_available_for_customer' => true,
        ]);

        $updatedChange = new AllowedChange('target', $targetProduct->id, ProductChangeType::UPGRADE, 5, false);

        $this->productUpdateService->updateProductLine(
            $product,
            $this->makeDto(allowedChanges: [$updatedChange]),
        );

        self::assertDatabaseHas('product_allowed_changes', [
            'from_product_id' => $product->id,
            'to_product_id' => $targetProduct->id,
            'change_type' => ProductChangeType::UPGRADE->value,
            'display_order' => 5,
            'is_available_for_customer' => false,
        ]);
        self::assertDatabaseCount('product_allowed_changes', 1);
    }

    #[Test]
    public function updateAllowedChangesDeletesChangeNotInPayload(): void
    {
        $keepProduct = new ProductFactory()->for($this->productGroup)->createOne();
        $removeProduct = new ProductFactory()->for($this->productGroup)->createOne();
        $product = new ProductFactory()->for($this->productGroup)->createOne(['slug' => 'test-product']);

        new ProductAllowedChangeFactory()->createOne([
            'from_product_id' => $product->id,
            'to_product_id' => $keepProduct->id,
            'change_type' => ProductChangeType::UPGRADE,
        ]);
        new ProductAllowedChangeFactory()->createOne([
            'from_product_id' => $product->id,
            'to_product_id' => $removeProduct->id,
            'change_type' => ProductChangeType::UPGRADE,
        ]);

        $this->productUpdateService->updateProductLine(
            $product,
            $this->makeDto(allowedChanges: [new AllowedChange(
                'keep',
                $keepProduct->id,
                ProductChangeType::UPGRADE,
                1,
                true,
            )]),
        );

        self::assertDatabaseHas('product_allowed_changes', [
            'from_product_id' => $product->id,
            'to_product_id' => $keepProduct->id,
            'deleted_at' => null,
        ]);
        self::assertDatabaseMissing('product_allowed_changes', [
            'from_product_id' => $product->id,
            'to_product_id' => $removeProduct->id,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function updateAllowedChangesCreatesNewChange(): void
    {
        $targetProduct = new ProductFactory()->for($this->productGroup)->createOne();
        $product = new ProductFactory()->for($this->productGroup)->createOne(['slug' => 'test-product']);

        $this->productUpdateService->updateProductLine(
            $product,
            $this->makeDto(allowedChanges: [new AllowedChange(
                'target',
                $targetProduct->id,
                ProductChangeType::DOWNGRADE,
                1,
                true,
            )]),
        );

        self::assertDatabaseHas('product_allowed_changes', [
            'from_product_id' => $product->id,
            'to_product_id' => $targetProduct->id,
            'change_type' => ProductChangeType::DOWNGRADE->value,
        ]);
    }

    #[Test]
    public function updateAllowedChangesUpdatesChangeTypeInPlaceWhenToProductIdMatches(): void
    {
        $targetProduct = new ProductFactory()->for($this->productGroup)->createOne();
        $product = new ProductFactory()->for($this->productGroup)->createOne(['slug' => 'test-product']);

        new ProductAllowedChangeFactory()->createOne([
            'from_product_id' => $product->id,
            'to_product_id' => $targetProduct->id,
            'change_type' => ProductChangeType::UPGRADE,
        ]);

        $this->productUpdateService->updateProductLine(
            $product,
            $this->makeDto(allowedChanges: [new AllowedChange(
                'target',
                $targetProduct->id,
                ProductChangeType::DOWNGRADE,
                1,
                true,
            )]),
        );

        self::assertDatabaseHas('product_allowed_changes', [
            'from_product_id' => $product->id,
            'to_product_id' => $targetProduct->id,
            'change_type' => ProductChangeType::DOWNGRADE->value,
            'deleted_at' => null,
        ]);
        self::assertDatabaseCount('product_allowed_changes', 1);
    }

    #[Test]
    public function updateProductPromotionsUpdatesExistingPromotion(): void
    {
        $product = new ProductFactory()->for($this->productGroup)->createOne(['slug' => 'test-product']);

        $uuid = Uuid::uuid4();
        new ProductPromotionFactory()->createOne([
            'product_id' => $product->id,
            'uuid' => $uuid,
            'weight' => 1,
        ]);

        $updatedPromotion = $this->makePromotion(uuid: $uuid, weight: 10);

        $this->productUpdateService->updateProductLine($product, $this->makeDto(promotions: [$updatedPromotion]));

        self::assertDatabaseHas('product_promotions', [
            'product_id' => $product->id,
            'uuid' => $uuid->toString(),
            'weight' => 10,
        ]);
        self::assertDatabaseCount('product_promotions', 1);
    }

    #[Test]
    public function updateProductPromotionsDeletesPromotionNotInPayload(): void
    {
        $product = new ProductFactory()->for($this->productGroup)->createOne(['slug' => 'test-product']);

        $uuid = Uuid::uuid4();
        $uuid2 = Uuid::uuid4();
        new ProductPromotionFactory()->createOne(['product_id' => $product->id, 'uuid' => $uuid]);
        new ProductPromotionFactory()->createOne(['product_id' => $product->id, 'uuid' => $uuid2]);

        $this->productUpdateService->updateProductLine(
            $product,
            $this->makeDto(promotions: [$this->makePromotion(uuid: $uuid)]),
        );

        self::assertDatabaseHas('product_promotions', ['product_id' => $product->id, 'uuid' => $uuid]);
        self::assertDatabaseMissing('product_promotions', ['product_id' => $product->id, 'uuid' => $uuid2]);
    }

    #[Test]
    public function updateProductPromotionsCreatesNewPromotion(): void
    {
        $product = new ProductFactory()->for($this->productGroup)->createOne(['slug' => 'test-product']);

        $this->productUpdateService->updateProductLine(
            $product,
            $this->makeDto(promotions: [$this->makePromotion(uuid: null)]),
        );

        self::assertDatabaseHas('product_promotions', ['product_id' => $product->id]);
    }

    #[Test]
    public function updateProductPromotionsSkipsUpdateForExpiredPromotion(): void
    {
        $product = new ProductFactory()->for($this->productGroup)->createOne(['slug' => 'test-product']);

        $uuid = Uuid::uuid4();
        new ProductPromotionFactory()->createOne([
            'product_id' => $product->id,
            'uuid' => $uuid,
            'weight' => 1,
            'end_date' => CarbonImmutable::parse('2020-01-01'),
        ]);

        $this->productUpdateService->updateProductLine(
            $product,
            $this->makeDto(promotions: [$this->makePromotion(uuid: $uuid, weight: 99)]),
        );

        self::assertDatabaseHas('product_promotions', ['product_id' => $product->id, 'uuid' => $uuid, 'weight' => 1]);
    }

    #[Test]
    public function updateProductPeriodsCreatesPeriodForNewPriceEntry(): void
    {
        $product = new ProductFactory()->for($this->productGroup)->createOne(['slug' => 'test-product']);

        $entry = new ProductPriceEntryDTO(
            billingPeriod: 1,
            contractPeriod: 12,
            registrationPrice: 999,
            additionalPrices: null,
        );

        $this->productUpdateService->updateProductLine($product, $this->makeDto(productPrices: [$entry]));

        self::assertDatabaseHas('product_periods', [
            'product_id' => $product->id,
            'billing_period' => 1,
            'contract_period' => 12,
        ]);
        self::assertDatabaseCount('product_periods', 1);
    }

    #[Test]
    public function updateProductPeriodsKeepsExistingPeriodUntouched(): void
    {
        $product = new ProductFactory()->for($this->productGroup)->createOne(['slug' => 'test-product']);
        $existingPeriod = new ProductPeriodFactory()->for($product)->createOne([
            'billing_period' => 1,
            'contract_period' => 12,
            'action_period' => 3,
        ]);

        $entry = new ProductPriceEntryDTO(
            billingPeriod: 1,
            contractPeriod: 12,
            registrationPrice: 999,
            additionalPrices: null,
        );

        $this->productUpdateService->updateProductLine($product, $this->makeDto(productPrices: [$entry]));

        self::assertDatabaseHas('product_periods', [
            'id' => $existingPeriod->id,
            'product_id' => $product->id,
            'billing_period' => 1,
            'contract_period' => 12,
            'action_period' => 3,
        ]);
        self::assertDatabaseCount('product_periods', 1);
    }

    #[Test]
    public function updateProductPeriodsDeletesPeriodNotInPayload(): void
    {
        $product = new ProductFactory()->for($this->productGroup)->createOne(['slug' => 'test-product']);
        new ProductPeriodFactory()->for($product)->createOne([
            'billing_period' => 1,
            'contract_period' => 12,
        ]);
        new ProductPeriodFactory()->for($product)->createOne([
            'billing_period' => 12,
            'contract_period' => 12,
        ]);

        $entry = new ProductPriceEntryDTO(
            billingPeriod: 1,
            contractPeriod: 12,
            registrationPrice: 999,
            additionalPrices: null,
        );

        $this->productUpdateService->updateProductLine($product, $this->makeDto(productPrices: [$entry]));

        self::assertDatabaseHas('product_periods', [
            'product_id' => $product->id,
            'billing_period' => 1,
            'contract_period' => 12,
        ]);
        self::assertDatabaseMissing('product_periods', [
            'product_id' => $product->id,
            'billing_period' => 12,
            'contract_period' => 12,
        ]);
        self::assertDatabaseCount('product_periods', 1);
    }

    #[Test]
    public function updateProductPeriodsWithEmptyPricesDeletesAllPeriods(): void
    {
        $product = new ProductFactory()->for($this->productGroup)->createOne(['slug' => 'test-product']);
        new ProductPeriodFactory()->for($product)->createOne([
            'billing_period' => 1,
            'contract_period' => 12,
        ]);

        $this->productUpdateService->updateProductLine($product, $this->makeDto(productPrices: []));

        self::assertDatabaseCount('product_periods', 0);
    }

    #[Test]
    public function updateProductPeriodsStoresOnlyUniquePeriodsForMultiplePriceEntries(): void
    {
        $product = new ProductFactory()->for($this->productGroup)->createOne(['slug' => 'test-product']);

        $firstEntry = new ProductPriceEntryDTO(
            billingPeriod: 1,
            contractPeriod: 12,
            registrationPrice: 500,
            additionalPrices: null,
        );
        $duplicateEntry = new ProductPriceEntryDTO(
            billingPeriod: 1,
            contractPeriod: 12,
            registrationPrice: 5000,
            additionalPrices: null,
        );

        $this->productUpdateService->updateProductLine(
            $product,
            $this->makeDto(productPrices: [$firstEntry, $duplicateEntry]),
        );

        self::assertDatabaseCount('product_periods', 1);
    }

    #[Test]
    public function updateProductPricesCreatesNewPriceEntry(): void
    {
        $product = new ProductFactory()->for($this->productGroup)->createOne(['slug' => 'test-product']);

        $entry = new ProductPriceEntryDTO(
            billingPeriod: 1,
            contractPeriod: 12,
            registrationPrice: 999,
            additionalPrices: null,
        );

        $this->productUpdateService->updateProductLine($product, $this->makeDto(productPrices: [$entry]));

        self::assertDatabaseHas('product_price_components', [
            'product_id' => $product->id,
            'billing_period' => 1,
            'contract_period' => 12,
            'type' => PriceComponentType::REGISTRATION,
            'price' => 999,
            'expires_at' => null,
        ]);
    }

    #[Test]
    public function updateProductPricesUpdatesExistingPriceEntryInPlace(): void
    {
        $product = new ProductFactory()->for($this->productGroup)->createOne(['slug' => 'test-product']);

        $existing = new ProductPriceComponentFactory()
            ->registration()
            ->createOne([
                'product_id' => $product->id,
                'billing_period' => 1,
                'contract_period' => 12,
                'price' => 500,
            ]);

        $entry = new ProductPriceEntryDTO(
            billingPeriod: 1,
            contractPeriod: 12,
            registrationPrice: 1500,
            additionalPrices: null,
        );

        $this->productUpdateService->updateProductLine($product, $this->makeDto(productPrices: [$entry]));

        self::assertNotNull($existing->refresh()->expires_at);
        self::assertDatabaseHas('product_price_components', [
            'product_id' => $product->id,
            'billing_period' => 1,
            'contract_period' => 12,
            'type' => PriceComponentType::REGISTRATION,
            'price' => 1500,
            'expires_at' => null,
        ]);
        self::assertDatabaseCount('product_price_components', 2);
    }

    #[Test]
    public function updateProductPricesDoesNotChangeUnchangedPriceEntry(): void
    {
        $product = new ProductFactory()->for($this->productGroup)->createOne(['slug' => 'test-product']);

        $existing = new ProductPriceComponentFactory()
            ->registration()
            ->createOne([
                'product_id' => $product->id,
                'billing_period' => 1,
                'contract_period' => 12,
                'price' => 999,
            ]);

        $entry = new ProductPriceEntryDTO(
            billingPeriod: 1,
            contractPeriod: 12,
            registrationPrice: 999,
            additionalPrices: null,
        );

        $this->productUpdateService->updateProductLine($product, $this->makeDto(productPrices: [$entry]));

        self::assertDatabaseHas('product_price_components', ['id' => $existing->id, 'expires_at' => null]);
        self::assertDatabaseCount('product_price_components', 1);
    }

    #[Test]
    public function updateProductPricesExpiresPriceEntryNotInPayload(): void
    {
        $product = new ProductFactory()->for($this->productGroup)->createOne(['slug' => 'test-product']);

        new ProductPriceComponentFactory()
            ->registration()
            ->createOne([
                'product_id' => $product->id,
                'billing_period' => 1,
                'contract_period' => 12,
            ]);
        $removed = new ProductPriceComponentFactory()
            ->registration()
            ->createOne([
                'product_id' => $product->id,
                'billing_period' => 12,
                'contract_period' => 12,
            ]);

        $entry = new ProductPriceEntryDTO(
            billingPeriod: 1,
            contractPeriod: 12,
            registrationPrice: 999,
            additionalPrices: null,
        );

        $this->productUpdateService->updateProductLine($product, $this->makeDto(productPrices: [$entry]));

        self::assertDatabaseHas('product_price_components', [
            'product_id' => $product->id,
            'billing_period' => 1,
            'contract_period' => 12,
            'expires_at' => null,
        ]);
        self::assertNotNull($removed->refresh()->expires_at);
    }

    #[Test]
    public function updateProductPricesDoesNotTouchStaffelDiscountComponents(): void
    {
        $product = new ProductFactory()->for($this->productGroup)->createOne(['slug' => 'test-product']);
        $staffel = new ProductPriceComponentFactory()->createOne([
            'product_id' => $product->id,
            'type' => PriceComponentType::REGISTRATION_STAFFEL,
            'billing_period' => 12,
            'contract_period' => 12,
            'price' => 777,
        ]);

        $this->productUpdateService->updateProductLine($product, $this->makeDto(productPrices: []));

        self::assertDatabaseHas('product_price_components', [
            'id' => $staffel->id,
            'product_id' => $product->id,
            'type' => PriceComponentType::REGISTRATION_STAFFEL,
            'price' => 777,
            'expires_at' => null,
        ]);
    }

    #[Test]
    public function updateProductPricesSetsAdditionalPricesCorrectly(): void
    {
        $product = new ProductFactory()->for($this->productGroup)->createOne(['slug' => 'test-product']);

        $entry = new ProductPriceEntryDTO(
            billingPeriod: 1,
            contractPeriod: 12,
            registrationPrice: 999,
            additionalPrices: [
                new AdditionalPriceDTO(type: PriceComponentType::INTRODUCTION, price: 100),
                new AdditionalPriceDTO(type: PriceComponentType::PROMOTION, price: 200),
                new AdditionalPriceDTO(type: PriceComponentType::PROLONGATION, price: 300),
            ],
        );

        $this->productUpdateService->updateProductLine($product, $this->makeDto(productPrices: [$entry]));

        self::assertDatabaseHas('product_price_components', [
            'product_id' => $product->id,
            'billing_period' => 1,
            'contract_period' => 12,
            'type' => PriceComponentType::REGISTRATION,
            'price' => 999,
            'expires_at' => null,
        ]);
        self::assertDatabaseHas('product_price_components', [
            'product_id' => $product->id,
            'billing_period' => 1,
            'contract_period' => 12,
            'type' => PriceComponentType::INTRODUCTION,
            'price' => 100,
            'expires_at' => null,
        ]);
        self::assertDatabaseHas('product_price_components', [
            'product_id' => $product->id,
            'billing_period' => 1,
            'contract_period' => 12,
            'type' => PriceComponentType::PROMOTION,
            'price' => 200,
            'expires_at' => null,
        ]);
        self::assertDatabaseHas('product_price_components', [
            'product_id' => $product->id,
            'billing_period' => 1,
            'contract_period' => 12,
            'type' => PriceComponentType::PROLONGATION,
            'price' => 300,
            'expires_at' => null,
        ]);
    }

    #[Test]
    public function updateProductPricesWithProlongationCreatesProlongation(): void
    {
        $product = new ProductFactory()->for($this->productGroup)->createOne(['slug' => 'test-product']);

        $entry = new ProductPriceEntryDTO(
            billingPeriod: 1,
            contractPeriod: 12,
            registrationPrice: 999,
            additionalPrices: [
                new AdditionalPriceDTO(type: PriceComponentType::PROLONGATION, price: 300),
            ],
        );

        $this->productUpdateService->updateProductLine($product, $this->makeDto(productPrices: [$entry]));

        self::assertDatabaseHas('product_price_components', [
            'product_id' => $product->id,
            'billing_period' => 1,
            'contract_period' => 12,
            'type' => PriceComponentType::REGISTRATION,
            'price' => 999,
            'expires_at' => null,
        ]);
        self::assertDatabaseHas('product_price_components', [
            'product_id' => $product->id,
            'billing_period' => 1,
            'contract_period' => 12,
            'type' => PriceComponentType::PROLONGATION,
            'price' => 300,
            'expires_at' => null,
        ]);
    }

    #[Test]
    public function updateProductPricesWithProlongationUpdatesProlongation(): void
    {
        $product = new ProductFactory()->for($this->productGroup)->createOne(['slug' => 'test-product']);
        $existingRegistration = new ProductPriceComponentFactory()
            ->registration()
            ->createOne([
                'product_id' => $product->id,
                'billing_period' => 1,
                'contract_period' => 12,
                'price' => 999,
            ]);

        $existingProlongation = new ProductPriceComponentFactory()
            ->prolongation()
            ->createOne([
                'product_id' => $product->id,
                'billing_period' => 1,
                'contract_period' => 12,
                'price' => 1000,
            ]);

        $entry = new ProductPriceEntryDTO(
            billingPeriod: 1,
            contractPeriod: 12,
            registrationPrice: 999,
            additionalPrices: [
                new AdditionalPriceDTO(type: PriceComponentType::PROLONGATION, price: 300),
            ],
        );

        $this->productUpdateService->updateProductLine($product, $this->makeDto(productPrices: [$entry]));

        self::assertDatabaseHas('product_price_components', ['id' => $existingRegistration->id, 'expires_at' => null]);
        self::assertNotNull($existingProlongation->refresh()->expires_at);
        self::assertDatabaseHas('product_price_components', [
            'product_id' => $product->id,
            'billing_period' => 1,
            'contract_period' => 12,
            'type' => PriceComponentType::PROLONGATION,
            'price' => 300,
            'expires_at' => null,
        ]);
    }

    #[Test]
    public function updateProductPricesWithProlongationSameAsRegistrationDoesNotCreateProlongation(): void
    {
        $product = new ProductFactory()->for($this->productGroup)->createOne(['slug' => 'test-product']);

        $entry = new ProductPriceEntryDTO(
            billingPeriod: 1,
            contractPeriod: 12,
            registrationPrice: 999,
            additionalPrices: [
                new AdditionalPriceDTO(type: PriceComponentType::PROLONGATION, price: 999),
            ],
        );

        $this->productUpdateService->updateProductLine($product, $this->makeDto(productPrices: [$entry]));

        self::assertDatabaseHas('product_price_components', [
            'product_id' => $product->id,
            'billing_period' => 1,
            'contract_period' => 12,
            'type' => PriceComponentType::REGISTRATION,
            'price' => 999,
            'expires_at' => null,
        ]);
        self::assertDatabaseMissing('product_price_components', [
            'product_id' => $product->id,
            'billing_period' => 1,
            'contract_period' => 12,
            'type' => PriceComponentType::PROLONGATION,
        ]);
    }

    #[Test]
    public function updateProductPricesWithProlongationSameAsRegistrationRemovesExistingProlongation(): void
    {
        $product = new ProductFactory()->for($this->productGroup)->createOne();

        new ProductPriceComponentFactory()
            ->for($product)
            ->registration()
            ->createOne();
        $existingProlongation = new ProductPriceComponentFactory()
            ->for($product)
            ->prolongation()
            ->createOne();

        $entry = new ProductPriceEntryDTO(
            billingPeriod: 12,
            contractPeriod: 12,
            registrationPrice: 999,
            additionalPrices: [
                new AdditionalPriceDTO(type: PriceComponentType::PROLONGATION, price: 999),
            ],
        );

        $this->productUpdateService->updateProductLine($product, $this->makeDto(productPrices: [$entry]));

        self::assertDatabaseHas('product_price_components', [
            'product_id' => $product->id,
            'type' => PriceComponentType::REGISTRATION,
            'billing_period' => 12,
            'contract_period' => 12,
            'price' => 999,
            'expires_at' => null,
        ]);
        self::assertNotNull($existingProlongation->refresh()->expires_at);
        self::assertSame(
            1,
            ProductPriceComponent::query()->where('product_id', $product->id)->whereNull('expires_at')->count(),
        );
    }

    #[Test]
    public function updateProductPricesWithoutProlongationShouldRemovesExistingProlongation(): void
    {
        $product = new ProductFactory()->for($this->productGroup)->createOne();

        new ProductPriceComponentFactory()
            ->for($product)
            ->registration()
            ->createOne();
        $existingProlongation = new ProductPriceComponentFactory()
            ->for($product)
            ->prolongation()
            ->createOne();

        $entry = new ProductPriceEntryDTO(
            billingPeriod: 12,
            contractPeriod: 12,
            registrationPrice: 999,
            additionalPrices: [],
        );

        $this->productUpdateService->updateProductLine($product, $this->makeDto(productPrices: [$entry]));

        self::assertDatabaseHas('product_price_components', [
            'product_id' => $product->id,
            'type' => PriceComponentType::REGISTRATION,
            'billing_period' => 12,
            'contract_period' => 12,
            'price' => 999,
            'expires_at' => null,
        ]);
        self::assertNotNull($existingProlongation->refresh()->expires_at);
        self::assertSame(
            1,
            ProductPriceComponent::query()->where('product_id', $product->id)->whereNull('expires_at')->count(),
        );
    }

    #[Test]
    public function updateProductLineCouplesNewAddonsAndKeepsExistingOnes(): void
    {
        $product = new ProductFactory()->for($this->productGroup)->createOne();
        $addonGroup = new ProductGroupFactory()->addon()->createOne();
        $existingAddon = new ProductFactory()->for($addonGroup)->createOne(['slug' => 'existing-addon']);
        $newAddon = new ProductFactory()->for($addonGroup)->createOne(['slug' => 'new-addon']);

        $existingCoupling = new ProductAddonCouplingFactory()->createOne([
            'parent_product_id' => $product->id,
            'addon_product_id' => $existingAddon->id,
        ]);

        $this->productUpdateService->updateProductLine($product, $this->makeDto(addons: [
            new ProductAddonDTO($existingAddon->id),
            new ProductAddonDTO($newAddon->id),
        ]));

        self::assertDatabaseHas('product_addon_coupling', [
            'id' => $existingCoupling->id,
            'parent_product_id' => $product->id,
            'addon_product_id' => $existingAddon->id,
        ]);
        self::assertDatabaseHas('product_addon_coupling', [
            'parent_product_id' => $product->id,
            'addon_product_id' => $newAddon->id,
        ]);
        self::assertDatabaseCount('product_addon_coupling', 2);
    }

    #[Test]
    public function updateProductLineRemovesAddonsThatAreNoLongerSubmitted(): void
    {
        $product = new ProductFactory()->for($this->productGroup)->createOne();
        $addonGroup = new ProductGroupFactory()->addon()->createOne();
        $removedAddon = new ProductFactory()->for($addonGroup)->createOne(['slug' => 'removed-addon']);
        $keptAddon = new ProductFactory()->for($addonGroup)->createOne(['slug' => 'kept-addon']);

        new ProductAddonCouplingFactory()->createOne([
            'parent_product_id' => $product->id,
            'addon_product_id' => $removedAddon->id,
        ]);
        new ProductAddonCouplingFactory()->createOne([
            'parent_product_id' => $product->id,
            'addon_product_id' => $keptAddon->id,
        ]);

        $this->productUpdateService->updateProductLine($product, $this->makeDto(addons: [new ProductAddonDTO($keptAddon->id)]));

        self::assertDatabaseMissing('product_addon_coupling', [
            'parent_product_id' => $product->id,
            'addon_product_id' => $removedAddon->id,
        ]);
        self::assertDatabaseHas('product_addon_coupling', [
            'parent_product_id' => $product->id,
            'addon_product_id' => $keptAddon->id,
        ]);
        self::assertDatabaseCount('product_addon_coupling', 1);
    }

    #[Test]
    public function updateProductLineWithoutAddonsRemovesAllCouplings(): void
    {
        $product = new ProductFactory()->for($this->productGroup)->createOne();
        $addon = new ProductFactory()->for(new ProductGroupFactory()->addon()->createOne())->createOne([
            'slug' => 'addon',
        ]);

        new ProductAddonCouplingFactory()->createOne([
            'parent_product_id' => $product->id,
            'addon_product_id' => $addon->id,
        ]);

        $this->productUpdateService->updateProductLine($product, $this->makeDto(addons: []));

        self::assertDatabaseCount('product_addon_coupling', 0);
    }

    #[Test]
    public function updateIntroductionPriceConfigurationCreatesNewDiscount(): void
    {
        $product = new ProductFactory()->for($this->productGroup)->createOne();

        $configuration = new IntroductionPriceConfigurationDTO(
            contractPeriod: 12,
            maxUsesPerCustomer: 5,
            firstMonthsDiscountPeriod: 3,
        );

        $this->productUpdateService->updateProductLine(
            $product,
            $this->makeDto(introductionPriceConfiguration: [$configuration]),
        );

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
    public function updateIntroductionPriceConfigurationUpdatesExistingDiscountInPlace(): void
    {
        $product = new ProductFactory()->for($this->productGroup)->createOne();
        $existingDiscount = new ProductIntroductionDiscountsFactory()->for($product)->createOne([
            'contract_period' => 12,
            'max_uses_per_customer' => 5,
        ]);

        $configuration = new IntroductionPriceConfigurationDTO(
            contractPeriod: 12,
            maxUsesPerCustomer: 9,
            firstMonthsDiscountPeriod: 6,
        );

        $this->productUpdateService->updateProductLine(
            $product,
            $this->makeDto(introductionPriceConfiguration: [$configuration]),
        );

        self::assertDatabaseHas('product_introduction_discounts', [
            'id' => $existingDiscount->id,
            'product_id' => $product->id,
            'contract_period' => 12,
            'max_uses_per_customer' => 9,
            'first_months_discount_period' => 6,
            'deleted_at' => null,
        ]);
        self::assertDatabaseCount('product_introduction_discounts', 1);
    }

    #[Test]
    public function updateIntroductionPriceConfigurationDeletesDiscountNotInPayload(): void
    {
        $product = new ProductFactory()->for($this->productGroup)->createOne();
        new ProductIntroductionDiscountsFactory()->for($product)->createOne(['contract_period' => 12]);
        new ProductIntroductionDiscountsFactory()->for($product)->createOne(['contract_period' => 24]);

        $configuration = new IntroductionPriceConfigurationDTO(
            contractPeriod: 12,
            maxUsesPerCustomer: 1,
            firstMonthsDiscountPeriod: null,
        );

        $this->productUpdateService->updateProductLine(
            $product,
            $this->makeDto(introductionPriceConfiguration: [$configuration]),
        );

        self::assertDatabaseHas('product_introduction_discounts', [
            'product_id' => $product->id,
            'contract_period' => 12,
            'deleted_at' => null,
        ]);
        self::assertDatabaseMissing('product_introduction_discounts', [
            'product_id' => $product->id,
            'contract_period' => 24,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function updateIntroductionPriceConfigurationWithEmptyArrayDeletesAllDiscounts(): void
    {
        $product = new ProductFactory()->for($this->productGroup)->createOne();
        new ProductIntroductionDiscountsFactory()->for($product)->createOne(['contract_period' => 12]);

        $this->productUpdateService->updateProductLine($product, $this->makeDto(introductionPriceConfiguration: []));

        self::assertDatabaseMissing('product_introduction_discounts', [
            'product_id' => $product->id,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function updateIntroductionPriceConfigurationWithNullPayloadKeepsExistingDiscounts(): void
    {
        $product = new ProductFactory()->for($this->productGroup)->createOne();
        new ProductIntroductionDiscountsFactory()->for($product)->createOne([
            'contract_period' => 12,
            'max_uses_per_customer' => 5,
        ]);

        $this->productUpdateService->updateProductLine($product, $this->makeDto());

        self::assertDatabaseHas('product_introduction_discounts', [
            'product_id' => $product->id,
            'contract_period' => 12,
            'max_uses_per_customer' => 5,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function updateIntroductionPriceConfigurationCreatesNewDiscountWhenExistingDiscountIsSoftDeleted(): void
    {
        $product = new ProductFactory()->for($this->productGroup)->createOne();
        $trashedDiscount = new ProductIntroductionDiscountsFactory()->for($product)->createOne([
            'contract_period' => 12,
        ]);
        $trashedDiscount->delete();

        $configuration = new IntroductionPriceConfigurationDTO(
            contractPeriod: 12,
            maxUsesPerCustomer: 2,
            firstMonthsDiscountPeriod: null,
        );

        $this->productUpdateService->updateProductLine(
            $product,
            $this->makeDto(introductionPriceConfiguration: [$configuration]),
        );

        self::assertDatabaseHas('product_introduction_discounts', [
            'product_id' => $product->id,
            'contract_period' => 12,
            'max_uses_per_customer' => 2,
            'deleted_at' => null,
        ]);
        self::assertDatabaseCount('product_introduction_discounts', 2);
    }

    #[Test]
    public function updateIntroductionPriceConfigurationPersistsDiscountWithoutMatchingIntroductionPrice(): void
    {
        $product = new ProductFactory()->for($this->productGroup)->createOne();

        $entry = new ProductPriceEntryDTO(
            billingPeriod: 12,
            contractPeriod: 12,
            registrationPrice: 999,
            additionalPrices: [],
        );

        $configuration = new IntroductionPriceConfigurationDTO(
            contractPeriod: 24,
            maxUsesPerCustomer: 1,
            firstMonthsDiscountPeriod: null,
        );

        $this->productUpdateService->updateProductLine(
            $product,
            $this->makeDto(productPrices: [$entry], introductionPriceConfiguration: [$configuration]),
        );

        self::assertDatabaseHas('product_introduction_discounts', [
            'product_id' => $product->id,
            'contract_period' => 24,
            'deleted_at' => null,
        ]);
    }

    /**
     * @param Specifications[]|null                    $specifications
     * @param AllowedChange[]|null                     $allowedChanges
     * @param Promotion[]|null                         $promotions
     * @param ProductPriceEntryDTO[]|null              $productPrices
     * @param ProductAddonDTO[]|null                   $addons
     * @param IntroductionPriceConfigurationDTO[]|null $introductionPriceConfiguration
     */
    private function makeDto(
        string $slug = 'test-product',
        string $name = 'Test Product',
        ?ShopConfig $shopConfig = null,
        ?array $specifications = [],
        ?array $allowedChanges = [],
        ?array $promotions = [],
        ?array $productPrices = [],
        ?array $addons = [],
        ?array $introductionPriceConfiguration = null,
    ): CreateProductDTO {
        return new CreateProductDTO(
            slug: $slug,
            name: $name,
            description: null,
            groupSlug: ProductGroupType::EXTENSION,
            shopConfig: $shopConfig ?? new ShopConfig(orderable: true),
            specifications: $specifications,
            allowedChange: $allowedChanges,
            promotions: $promotions,
            productPrices: $productPrices,
            addons: $addons,
            introductionPriceConfiguration: $introductionPriceConfiguration,
        );
    }

    private function makePromotion(?UuidInterface $uuid, int $weight = 1): Promotion
    {
        return new Promotion(
            uuid: $uuid,
            platform: ProductPromotionPlatform::CUSTOMER_PANEL,
            placementUrl: 'https://example.com/banner.png',
            startDate: CarbonImmutable::parse('2025-01-01'),
            endDate: CarbonImmutable::parse('2025-12-31'),
            callToAction: new PromotionCallToAction(
                title: 'Buy now',
                buttonText: 'Order',
                description: 'Great deal',
                destinationUrl: 'https://example.com',
                priceDescription: 'From €9.99',
            ),
            weight: $weight,
        );
    }
}
