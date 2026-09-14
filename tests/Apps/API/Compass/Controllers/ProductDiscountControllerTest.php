<?php

declare(strict_types=1);

namespace Tests\Apps\API\Compass\Controllers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductDiscountFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Controllers\ProductDiscountController;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;
use Waterfront\Domain\Products\DTO\PriceRequest;
use Waterfront\Domain\Products\DTO\RegistrationPriceRequest;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductDiscount;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Domain\Products\VolumeDiscountService;

#[CoversClass(ProductDiscountController::class)]
class ProductDiscountControllerTest extends IntegrationTestCase
{
    private Product $productA;

    private Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        $productGroup = new ProductGroupFactory()->createOne();
        $this->productA = new ProductFactory()->for($productGroup)->createOne();
        $this->productB = new ProductFactory()->for($productGroup)->createOne();
    }

    #[Test]
    public function createStoresTheDiscountAndReturnsItPresented(): void
    {
        $response = $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.product-config.product-discount.create'), [
                'name' => 'Volume discount 2026',
                'description' => 'For the big accounts',
                'product_id' => $this->productA->id,
            ])
            ->assertCreated();

        $discount = ProductDiscount::query()->sole();

        self::assertSame('Volume discount 2026', $discount->name);
        self::assertSame('For the big accounts', $discount->description);
        self::assertNull($discount->product_id);

        $response->assertExactJson([
            'id' => $discount->id,
            'name' => 'Volume discount 2026',
            'description' => 'For the big accounts',
            'customers' => [],
            'productPrices' => [],
            'products' => [],
        ]);
    }

    #[Test]
    public function createRejectsAnInvalidPayloadAndWritesNothing(): void
    {
        $this->actingAsEmployee()->postJson($this->generateRoute('admin.product-config.product-discount.create'), [
            'description' => 'No name given',
        ])->assertJsonValidationErrors('name');

        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.product-config.product-discount.create'), [
                'name' => str_repeat('a', 256),
                'description' => ['not', 'a', 'string'],
            ])
            ->assertJsonValidationErrors(['name', 'description']);

        self::assertDatabaseCount('product_discounts', 0);
    }

    #[Test]
    public function createsStaffelComponentsForMultipleProducts(): void
    {
        $discount = new ProductDiscountFactory()->createOne();

        $this->actingAsEmployee()
            ->putJson(
                $this->generateRoute('admin.product-config.product-discount.prices.update', [
                    'productDiscount' => $discount->id,
                ]),
                [
                    'prices' => [
                        [
                            'product_id' => $this->productA->id,
                            'billing_period' => 12,
                            'contract_period' => 12,
                            'registration_staffel_price' => 1000,
                            'prolongation_staffel_price' => 900,
                        ],
                        [
                            'product_id' => $this->productB->id,
                            'billing_period' => 1,
                            'contract_period' => 1,
                            'registration_staffel_price' => 500,
                            'prolongation_staffel_price' => 400,
                        ],
                    ],
                ],
            )
            ->assertNoContent();

        self::assertDatabaseCount('product_price_components', 4);
        self::assertDatabaseCount('product_discount_prices', 4);

        $components = ProductPriceComponent::query()->get();
        self::assertTrue($components->every(fn (ProductPriceComponent $component) => $component->expires_at === null));
    }

    #[Test]
    public function unchangedPricesAreLeftAloneAndChangedPricesAreReplaced(): void
    {
        $discount = new ProductDiscountFactory()->createOne();

        $unchanged = new ProductPriceComponentFactory()
            ->for($this->productA)
            ->registrationStaffel()
            ->createOne([
                'billing_period' => 12,
                'contract_period' => 12,
                'price' => 1000,
            ]);

        $changed = new ProductPriceComponentFactory()
            ->for($this->productB)
            ->registrationStaffel()
            ->createOne([
                'billing_period' => 12,
                'contract_period' => 12,
                'price' => 500,
            ]);
        self::resolve(VolumeDiscountService::class)->attachPrice($discount, $unchanged);
        self::resolve(VolumeDiscountService::class)->attachPrice($discount, $changed);

        $this->actingAsEmployee()
            ->putJson(
                $this->generateRoute('admin.product-config.product-discount.prices.update', [
                    'productDiscount' => $discount->id,
                ]),
                [
                    'prices' => [
                        [
                            'product_id' => $this->productB->id,
                            'billing_period' => 12,
                            'contract_period' => 12,
                            'registration_staffel_price' => 700,
                            'prolongation_staffel_price' => null,
                        ],
                        [
                            'product_id' => $this->productA->id,
                            'billing_period' => 12,
                            'contract_period' => 12,
                            'registration_staffel_price' => 1000,
                            'prolongation_staffel_price' => null,
                        ],
                    ],
                ],
            )
            ->assertNoContent();

        self::assertNull($unchanged->refresh()->expires_at);
        self::assertNotNull($changed->refresh()->expires_at);

        // Only the changed row produces a component, and with it a second coupling.
        self::assertDatabaseCount('product_price_components', 3);
        self::assertDatabaseCount('product_discount_prices', 3);

        $replacement = ProductPriceComponent::query()->whereNotIn('id', [$unchanged->id, $changed->id])->sole();
        self::assertSame(700, $replacement->price);
        self::assertNull($replacement->expires_at);
    }

    #[Test]
    public function pricesLeftOutOfThePayloadAreExpired(): void
    {
        $discount = new ProductDiscountFactory()->createOne();

        $registration = new ProductPriceComponentFactory()
            ->for($this->productA)
            ->registrationStaffel()
            ->createOne([
                'billing_period' => 12,
                'contract_period' => 12,
                'price' => 1000,
            ]);
        $prolongation = new ProductPriceComponentFactory()
            ->for($this->productA)
            ->prolongationStaffel()
            ->createOne([
                'billing_period' => 12,
                'contract_period' => 12,
                'price' => 900,
            ]);
        $omittedRow = new ProductPriceComponentFactory()
            ->for($this->productB)
            ->registrationStaffel()
            ->createOne([
                'billing_period' => 1,
                'contract_period' => 1,
                'price' => 500,
            ]);

        self::resolve(VolumeDiscountService::class)->attachPrice($discount, $registration);
        self::resolve(VolumeDiscountService::class)->attachPrice($discount, $prolongation);
        self::resolve(VolumeDiscountService::class)->attachPrice($discount, $omittedRow);

        $this->actingAsEmployee()
            ->putJson(
                $this->generateRoute('admin.product-config.product-discount.prices.update', [
                    'productDiscount' => $discount->id,
                ]),
                [
                    'prices' => [
                        [
                            'product_id' => $this->productA->id,
                            'billing_period' => 12,
                            'contract_period' => 12,
                            'registration_staffel_price' => 1000,
                            'prolongation_staffel_price' => null,
                        ],
                    ],
                ],
            )
            ->assertNoContent();

        self::assertNull($registration->refresh()->expires_at);
        self::assertNotNull($prolongation->refresh()->expires_at);
        self::assertNotNull($omittedRow->refresh()->expires_at);

        $this->actingAsEmployee()
            ->putJson(
                $this->generateRoute('admin.product-config.product-discount.prices.update', [
                    'productDiscount' => $discount->id,
                ]),
                ['prices' => []],
            )
            ->assertNoContent();

        self::assertNotNull($registration->refresh()->expires_at);
        self::assertDatabaseCount('product_price_components', 3);
    }

    #[Test]
    public function pricesOfAnotherDiscountAreNotTouched(): void
    {
        $discount = new ProductDiscountFactory()->createOne();
        $otherDiscount = new ProductDiscountFactory()->createOne();

        $otherComponent = new ProductPriceComponentFactory()
            ->for($this->productA)
            ->registrationStaffel()
            ->createOne([
                'billing_period' => 12,
                'contract_period' => 12,
                'price' => 1000,
            ]);
        self::resolve(VolumeDiscountService::class)->attachPrice($otherDiscount, $otherComponent);

        $this->actingAsEmployee()
            ->putJson(
                $this->generateRoute('admin.product-config.product-discount.prices.update', [
                    'productDiscount' => $discount->id,
                ]),
                [
                    'prices' => [
                        [
                            'product_id' => $this->productA->id,
                            'billing_period' => 12,
                            'contract_period' => 12,
                            'registration_staffel_price' => 1500,
                            'prolongation_staffel_price' => null,
                        ],
                    ],
                ],
            )
            ->assertNoContent();

        self::assertNull($otherComponent->refresh()->expires_at);
        self::assertDatabaseCount('product_price_components', 2);
    }

    #[Test]
    public function invalidPayloadsFailValidationAndWriteNothing(): void
    {
        $discount = new ProductDiscountFactory()->createOne();

        $this->actingAsEmployee()
            ->putJson($this->generateRoute('admin.product-config.product-discount.prices.update', [
                'productDiscount' => $discount->id,
            ]), [])
            ->assertJsonValidationErrors('prices');

        $this->actingAsEmployee()
            ->putJson(
                $this->generateRoute('admin.product-config.product-discount.prices.update', [
                    'productDiscount' => $discount->id,
                ]),
                [
                    'prices' => [
                        [
                            'product_id' => $this->productA->id,
                            'billing_period' => 12,
                            'contract_period' => 12,
                            'registration_staffel_price' => -1,
                        ],
                    ],
                ],
            )
            ->assertJsonValidationErrors('prices.0.registration_staffel_price');

        $this->actingAsEmployee()
            ->putJson(
                $this->generateRoute('admin.product-config.product-discount.prices.update', [
                    'productDiscount' => $discount->id,
                ]),
                [
                    'prices' => [
                        ['product_id' => $this->productA->id, 'billing_period' => 12, 'contract_period' => 12],
                    ],
                ],
            )
            ->assertJsonValidationErrors('prices.0.registration_staffel_price');

        $this->actingAsEmployee()
            ->putJson(
                $this->generateRoute('admin.product-config.product-discount.prices.update', [
                    'productDiscount' => $discount->id,
                ]),
                [
                    'prices' => [
                        [
                            'product_id' => $this->productA->id,
                            'billing_period' => 12,
                            'contract_period' => 12,
                            'registration_staffel_price' => 1000,
                        ],
                        [
                            'product_id' => $this->productA->id,
                            'billing_period' => 12,
                            'contract_period' => 12,
                            'registration_staffel_price' => 1200,
                        ],
                    ],
                ],
            )
            ->assertJsonValidationErrors('prices.1.product_id');

        self::assertDatabaseCount('product_price_components', 0);
    }

    #[Test]
    public function priceResolverAppliesAFreshlySavedPriceForACustomerHoldingTheDiscount(): void
    {
        new ProductPriceComponentFactory()
            ->for($this->productA)
            ->registration()
            ->createOne([
                'billing_period' => 12,
                'contract_period' => 12,
                'price' => 2000,
            ]);

        $discount = new ProductDiscountFactory()->createOne();
        $customer = new CustomerFactory()->createOne();
        $customer->productDiscounts()->attach($discount->id);

        $this->actingAsEmployee()
            ->putJson(
                $this->generateRoute('admin.product-config.product-discount.prices.update', [
                    'productDiscount' => $discount->id,
                ]),
                [
                    'prices' => [
                        [
                            'product_id' => $this->productA->id,
                            'billing_period' => 12,
                            'contract_period' => 12,
                            'registration_staffel_price' => 1500,
                            'prolongation_staffel_price' => null,
                        ],
                    ],
                ],
            )
            ->assertNoContent();

        $priceRequest = new PriceRequest([new RegistrationPriceRequest($this->productA, 1, 12, 12)], $customer);
        $priceList = self::resolve(PriceResolver::class)->getPriceList($priceRequest);

        self::assertSame(1500, $priceList->getProductPrice($this->productA->slug, 12, 12)->calculatedPrice);
    }

    #[Test]
    public function showReturnsProductsAndPricesAsJsonArrays(): void
    {
        $discount = new ProductDiscountFactory()->createOne();

        foreach ([$this->productA, $this->productB] as $product) {
            $registration = new ProductPriceComponentFactory()
                ->for($product)
                ->registrationStaffel()
                ->createOne([
                    'billing_period' => 12,
                    'contract_period' => 12,
                    'price' => 1000,
                ]);
            $prolongation = new ProductPriceComponentFactory()
                ->for($product)
                ->prolongationStaffel()
                ->createOne([
                    'billing_period' => 12,
                    'contract_period' => 12,
                    'price' => 900,
                ]);
            self::resolve(VolumeDiscountService::class)->attachPrice($discount, $registration);
            self::resolve(VolumeDiscountService::class)->attachPrice($discount, $prolongation);
        }

        $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.product-config.product-discount.show', [
                'productDiscount' => $discount->id,
            ]))
            ->assertOk()
            ->assertJsonIsArray('products')
            ->assertJsonCount(2, 'products')
            ->assertJsonIsArray('productPrices')
            ->assertJsonCount(4, 'productPrices');

        $withoutPrices = new ProductDiscountFactory()->createOne();

        $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.product-config.product-discount.show', [
                'productDiscount' => $withoutPrices->id,
            ]))
            ->assertOk()
            ->assertExactJson([
                'id' => $withoutPrices->id,
                'name' => $withoutPrices->name,
                'description' => $withoutPrices->description,
                'customers' => [],
                'productPrices' => [],
                'products' => [],
            ]);
    }
}
