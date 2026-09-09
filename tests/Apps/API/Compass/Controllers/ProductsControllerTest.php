<?php

declare(strict_types=1);

namespace Tests\Apps\API\Compass\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductIntroductionDiscountsFactory;
use Tests\Factories\ProductPromotionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Controllers\ProductsController;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductGroup;

#[CoversClass(ProductsController::class)]
class ProductsControllerTest extends IntegrationTestCase
{
    private ProductGroup $extensionGroup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extensionGroup = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::EXTENSION,
        ]);
    }

    #[Test]
    public function showProducts(): void
    {
        $hostingProductGroup = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::HOSTING,
        ]);

        new ProductFactory()->for($this->extensionGroup)->createOne();
        new ProductFactory()->for($hostingProductGroup)->createOne();

        $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.products.list'))
            ->assertOk();
    }

    #[Test]
    public function listReturnsThePaginatorEnvelopeTheFrontendPaginatesOn(): void
    {
        new ProductFactory()->for($this->extensionGroup)->createOne();
        new ProductFactory()->for($this->extensionGroup)->createOne();

        $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.products.list', ['pageSize' => 1]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.last_page', 2);
    }

    #[Test]
    public function getAllProductsReturnsAnUnwrappedListOfProducts(): void
    {
        $product = new ProductFactory()->for($this->extensionGroup)->createOne();

        $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.products.all'))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.uuid', $product->uuid);
    }

    #[Test]
    public function createSuccess(): void
    {
        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.products.create'), [
                'slug' => 'test',
                'name' => 'Test Product',
                'groupSlug' => ProductGroupType::EXTENSION->value,
                'shopConfig' => ['orderable' => true],
            ])
            ->assertCreated();

        self::assertDatabaseHas('products', [
            'slug' => 'test',
            'name' => 'Test Product',
            'product_group_id' => $this->extensionGroup->id,
        ]);
    }

    #[Test]
    public function createValidationFailedWrongSlug(): void
    {
        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.products.create'), [
                'slug' => 'tes!!t',
                'name' => 'Test Product',
                'groupSlug' => ProductGroupType::EXTENSION->value,
                'shopConfig' => ['orderable' => true],
            ])
            ->assertUnprocessable();

        self::assertDatabaseMissing('products', [
            'slug' => 'tes!!t',
            'name' => 'Test Product',
            'product_group_id' => $this->extensionGroup->id,
        ]);
    }

    #[Test]
    public function createValidationFailedSlugAlreadyExists(): void
    {
        new ProductFactory()->for($this->extensionGroup)->createOne(['slug' => 'test', 'name' => 'Wow']);

        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.products.create'), [
                'slug' => 'test',
                'name' => 'Test Product',
                'groupSlug' => ProductGroupType::EXTENSION->value,
                'shopConfig' => ['orderable' => true],
            ])
            ->assertUnprocessable();

        self::assertDatabaseMissing('products', [
            'slug' => 'test',
            'name' => 'Test Product',
            'product_group_id' => $this->extensionGroup->id,
        ]);
    }

    #[Test]
    public function validationFailedDuplicateProductPricePeriods(): void
    {
        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.products.create'), [
                'slug' => 'duplicate-price-product',
                'name' => 'Test Product',
                'groupSlug' => ProductGroupType::EXTENSION->value,
                'shopConfig' => ['orderable' => true],

                'productPrices' => [
                    ['billing_period' => '1', 'contract_period' => '12'],
                    ['billing_period' => '1', 'contract_period' => '12'],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['productPrices.1.billing_period']);

        self::assertDatabaseMissing('products', [
            'slug' => 'duplicate-price-product',
        ]);
    }

    #[Test]
    public function validationFailedPeriods(): void
    {
        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.products.create'), [
                'slug' => 'duplicate-price-product',
                'name' => 'Test Product',
                'groupSlug' => ProductGroupType::EXTENSION->value,
                'shopConfig' => ['orderable' => true],

                'productPrices' => [
                    ['billing_period' => '6', 'contract_period' => '12'],
                    ['billing_period' => '24', 'contract_period' => '12'],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['productPrices.0.billing_period', 'productPrices.1.billing_period']);

        self::assertDatabaseMissing('products', [
            'slug' => 'duplicate-price-product',
        ]);
    }

    #[Test]
    public function productPromotionsListReturnsSnakeCaseCallToActionForSnakeCaseStoredData(): void
    {
        $product = new ProductFactory()->for($this->extensionGroup)->createOne();

        new ProductPromotionFactory()->for($product)->createOne([
            'call_to_action' => [
                'title'             => 'pages.promotions.title',
                'button_text'       => 'pages.promotions.button_text',
                'description'       => 'pages.promotions.description',
                'destination_url'   => 'https://example.com',
                'price_description' => 'pages.promotions.price_description',
            ],
        ]);

        $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.products.show.product-promotions.list', $product))
            ->assertOk()
            ->assertJsonPath('data.0.callToAction.button_text', 'pages.promotions.button_text')
            ->assertJsonPath('data.0.callToAction.destination_url', 'https://example.com')
            ->assertJsonPath('data.0.callToAction.price_description', 'pages.promotions.price_description');
    }

    #[Test]
    public function productPromotionsListNormalizesCamelCaseCallToActionToSnakeCase(): void
    {
        $product = new ProductFactory()->for($this->extensionGroup)->createOne();

        new ProductPromotionFactory()->for($product)->createOne();

        // Write raw camelCase JSON to simulate legacy data stored before the cast was introduced
        DB::table('product_promotions')
            ->where('product_id', $product->id)
            ->update(['call_to_action' => json_encode([
                'title'            => 'pages.promotions.title',
                'buttonText'       => 'pages.promotions.button_text',
                'description'      => 'pages.promotions.description',
                'destinationUrl'   => 'https://example.com',
                'priceDescription' => 'pages.promotions.price_description',
            ])]);

        $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.products.show.product-promotions.list', $product))
            ->assertOk()
            ->assertJsonPath('data.0.callToAction.button_text', 'pages.promotions.button_text')
            ->assertJsonPath('data.0.callToAction.destination_url', 'https://example.com')
            ->assertJsonPath('data.0.callToAction.price_description', 'pages.promotions.price_description')
            ->assertJsonMissingPath('data.0.callToAction.buttonText')
            ->assertJsonMissingPath('data.0.callToAction.destinationUrl')
            ->assertJsonMissingPath('data.0.callToAction.priceDescription');
    }

    #[Test]
    public function updateSuccess(): void
    {
        $product = new ProductFactory()->for($this->extensionGroup)->createOne(['slug' => 'original-slug', 'name' => 'Original Name']);

        $this->actingAsEmployee()
            ->putJson($this->generateRoute('admin.products.show.update', ['product' => $product->uuid]), [
                'name' => 'Updated Name',
                'groupSlug' => ProductGroupType::EXTENSION->value,
                'slug' => 'up-dat E_Slug(.)',
                'shopConfig' => ['orderable' => true],
            ])
            ->assertOk();

        self::assertDatabaseHas('products', [
            'uuid' => $product->uuid,
            'slug' => $product->slug,
            'name' => 'Updated Name',
        ]);
    }

    #[Test]
    public function updateValidationFailedSlugAlreadyExistsOnAnotherProduct(): void
    {
        new ProductFactory()->for($this->extensionGroup)->createOne(['slug' => 'taken-slug']);
        $product = new ProductFactory()->for($this->extensionGroup)->createOne(['slug' => 'my-slug']);

        $this->actingAsEmployee()
            ->putJson($this->generateRoute('admin.products.show.update', ['product' => $product->uuid]), [
                'slug' => 'taken-slug',
                'name' => 'My Product',
                'groupSlug' => ProductGroupType::EXTENSION->value,
                'shopConfig' => ['orderable' => true],
            ])
            ->assertUnprocessable();
    }

    #[Test]
    public function updateAllowsKeepingTheSameSlug(): void
    {
        $product = new ProductFactory()->for($this->extensionGroup)->createOne(['slug' => 'my-slug', 'name' => 'Old Name']);

        $this->actingAsEmployee()
            ->putJson($this->generateRoute('admin.products.show.update', ['product' => $product->uuid]), [
                'slug' => 'my-slug',
                'name' => 'New Name',
                'groupSlug' => ProductGroupType::EXTENSION->value,
                'shopConfig' => ['orderable' => true],
            ])
            ->assertOk();

        self::assertDatabaseHas('products', ['uuid' => $product->uuid, 'slug' => 'my-slug', 'name' => 'New Name']);
    }

    #[Test]
    public function createWithIntroductionPriceConfigurationSuccess(): void
    {
        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.products.create'), [
                'slug' => 'intro-discount-product',
                'name' => 'Test Product',
                'groupSlug' => ProductGroupType::EXTENSION->value,
                'shopConfig' => ['orderable' => true],
                'prices' => [
                    [
                        'billing_period' => 1,
                        'contract_period' => 12,
                        'registration_price' => 999,
                        'additional_prices' => [
                            ['type' => PriceComponentType::INTRODUCTION->value, 'price' => 499],
                        ],
                    ],
                ],
                'introduction_price_configuration' => [
                    ['contract_period' => 12, 'max_uses_per_customer' => 5, 'first_months_discount_period' => 3],
                ],
            ])
            ->assertCreated();

        $product = Product::where('slug', 'intro-discount-product')->firstOrFail();

        self::assertDatabaseHas('product_introduction_discounts', [
            'product_id'                   => $product->id,
            'contract_period'              => 12,
            'max_uses_per_customer'        => 5,
            'first_months_discount_period' => 3,
            'deleted_at'                   => null,
        ]);
    }

    #[Test]
    public function updateWithIntroductionPriceConfigurationSuccess(): void
    {
        $product = new ProductFactory()->for($this->extensionGroup)->createOne(['slug' => 'my-slug']);

        $this->actingAsEmployee()
            ->putJson($this->generateRoute('admin.products.show.update', ['product' => $product->uuid]), [
                'slug' => 'my-slug',
                'name' => 'My Product',
                'groupSlug' => ProductGroupType::EXTENSION->value,
                'shopConfig' => ['orderable' => true],
                'introduction_price_configuration' => [
                    ['contract_period' => 24, 'max_uses_per_customer' => 1],
                ],
            ])
            ->assertOk();

        self::assertDatabaseHas('product_introduction_discounts', [
            'product_id'                   => $product->id,
            'contract_period'              => 24,
            'max_uses_per_customer'        => 1,
            'first_months_discount_period' => null,
            'deleted_at'                   => null,
        ]);
    }

    #[Test]
    public function updateWithoutIntroductionPriceConfigurationKeepsExistingDiscounts(): void
    {
        $product = new ProductFactory()->for($this->extensionGroup)->createOne(['slug' => 'my-slug']);
        new ProductIntroductionDiscountsFactory()->for($product)->createOne([
            'contract_period'       => 12,
            'max_uses_per_customer' => 5,
        ]);

        $this->actingAsEmployee()
            ->putJson($this->generateRoute('admin.products.show.update', ['product' => $product->uuid]), [
                'slug' => 'my-slug',
                'name' => 'My Product',
                'groupSlug' => ProductGroupType::EXTENSION->value,
                'shopConfig' => ['orderable' => true],
            ])
            ->assertOk();

        self::assertDatabaseHas('product_introduction_discounts', [
            'product_id'            => $product->id,
            'contract_period'       => 12,
            'max_uses_per_customer' => 5,
            'deleted_at'            => null,
        ]);
    }

    #[Test]
    public function updateWithEmptyIntroPriceConfigurationShouldDeleteExistingIntroDiscount(): void
    {
        $product = new ProductFactory()->for($this->extensionGroup)->createOne(['slug' => 'my-slug']);
        new ProductIntroductionDiscountsFactory()->for($product)->createOne([
            'contract_period'       => 12,
            'max_uses_per_customer' => 5,
        ]);

        $this->actingAsEmployee()
            ->putJson($this->generateRoute('admin.products.show.update', ['product' => $product->uuid]), [
                'slug' => 'my-slug',
                'name' => 'My Product',
                'groupSlug' => ProductGroupType::EXTENSION->value,
                'shopConfig' => ['orderable' => true],
                'introduction_price_configuration' => [],
            ])
            ->assertOk();

        self::assertDatabaseHas('product_introduction_discounts', [
            'product_id'            => $product->id,
            'contract_period'       => 12,
            'max_uses_per_customer' => 5,
            'deleted_at'            => CarbonImmutable::now(),
        ]);
    }

    #[Test]
    public function validationFailedIntroductionPriceConfigurationFirstMonthsDiscountPeriodExceedsContractPeriod(): void
    {
        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.products.create'), [
                'slug' => 'intro-discount-product',
                'name' => 'Test Product',
                'groupSlug' => ProductGroupType::EXTENSION->value,
                'shopConfig' => ['orderable' => true],
                'introduction_price_configuration' => [
                    ['contract_period' => 12, 'first_months_discount_period' => 24],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['introduction_price_configuration.0.first_months_discount_period']);

        self::assertDatabaseMissing('products', ['slug' => 'intro-discount-product']);
    }

    #[Test]
    public function validationFailedDuplicateIntroductionPriceConfigurationContractPeriods(): void
    {
        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.products.create'), [
                'slug' => 'intro-discount-product',
                'name' => 'Test Product',
                'groupSlug' => ProductGroupType::EXTENSION->value,
                'shopConfig' => ['orderable' => true],
                'introduction_price_configuration' => [
                    ['contract_period' => 12, 'max_uses_per_customer' => 1],
                    ['contract_period' => 12, 'max_uses_per_customer' => 2],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['introduction_price_configuration.1.contract_period']);

        self::assertDatabaseMissing('products', ['slug' => 'intro-discount-product']);
    }

    #[Test]
    public function validationFailedIntroductionPriceConfigurationNegativeMaxUsesPerCustomer(): void
    {
        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.products.create'), [
                'slug' => 'intro-discount-product',
                'name' => 'Test Product',
                'groupSlug' => ProductGroupType::EXTENSION->value,
                'shopConfig' => ['orderable' => true],
                'introduction_price_configuration' => [
                    ['contract_period' => 12, 'max_uses_per_customer' => -1],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['introduction_price_configuration.0.max_uses_per_customer']);
    }

    #[Test]
    public function validationFailedIntroductionPriceConfigurationMissingContractPeriod(): void
    {
        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.products.create'), [
                'slug' => 'intro-discount-product',
                'name' => 'Test Product',
                'groupSlug' => ProductGroupType::EXTENSION->value,
                'shopConfig' => ['orderable' => true],
                'introduction_price_configuration' => [
                    ['max_uses_per_customer' => 1],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['introduction_price_configuration.0.contract_period']);
    }

    #[Test]
    public function showReturnsIntroductionPriceConfiguration(): void
    {
        $product = new ProductFactory()->for($this->extensionGroup)->createOne();
        new ProductIntroductionDiscountsFactory()->for($product)->createOne([
            'contract_period'              => 12,
            'max_uses_per_customer'        => 5,
            'first_months_discount_period' => 3,
        ]);

        $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.products.show.details', ['product' => $product->uuid]))
            ->assertOk()
            ->assertJsonPath('introduction_price_configuration.0.contract_period', 12)
            ->assertJsonPath('introduction_price_configuration.0.max_uses_per_customer', 5)
            ->assertJsonPath('introduction_price_configuration.0.first_months_discount_period', 3);
    }

    #[Test]
    public function listReturnsIntroductionPriceConfiguration(): void
    {
        $product = new ProductFactory()->for($this->extensionGroup)->createOne();
        new ProductIntroductionDiscountsFactory()->for($product)->createOne([
            'contract_period'       => 24,
            'max_uses_per_customer' => 2,
        ]);

        $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.products.list'))
            ->assertOk()
            ->assertJsonPath('data.0.introduction_price_configuration.0.contract_period', 24)
            ->assertJsonPath('data.0.introduction_price_configuration.0.max_uses_per_customer', 2);
    }
}
