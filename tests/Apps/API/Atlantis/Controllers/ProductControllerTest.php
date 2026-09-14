<?php

declare(strict_types=1);

namespace Tests\Apps\API\Atlantis\Controllers;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProviderFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Atlantis\Controllers\ProductController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;

#[CoversClass(ProductController::class)]
class ProductControllerTest extends IntegrationTestCase
{
    private Customer $customer;

    public function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->withAddress()->createOne();
    }

    #[Test]
    public function productsRouteWithDiscounts(): void
    {
        $givenDiscountPercentage = 90;

        $this->generateProductData(
            regular: 100,
            promotion: null,
            discount: $givenDiscountPercentage,
        );

        $response = $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute('storefront.product.index'),
            )
            ->assertOk();

        $response->assertOk();
        $products = $response->json('products');
        assert(is_array($products));

        $productCollection = new Collection($products);

        $givenExtensionDiscountValue = Arr::get(
            $productCollection->where('type', 'extension')->first(),
            'prices.0.discount_price',
            0,
        );
        self::assertSame(10, $givenExtensionDiscountValue);

        $givenHostingDiscountValue = Arr::get(
            $productCollection->where('type', 'hosting')->first(),
            'prices.0.discount_price',
            0,
        );
        self::assertSame(0, $givenHostingDiscountValue);
    }

    private function generateProductData(int $regular, ?int $promotion, ?int $discount, bool $createAddon = false): void
    {
        ProviderFactory::new()->createOne([
            'type' => ProviderType::DOMAIN,
            'enabled' => true,
            'default' => true,
            'slug' => ProviderSlug::OPEN_PROVIDER,
        ]);
        ProviderFactory::new()->create([
            'slug' => ProviderSlug::PLESK,
            'type' => ProviderType::HOSTING,
            'default' => true,
            'enabled' => true,
        ]);

        $extensionGroup = new ProductGroupFactory()->extension()->createOne();

        $extensionProduct = new ProductFactory()->createOne([
            'product_group_id' => $extensionGroup->id,
            'name' => '.com',
            'slug' => 'extension_nl',
        ]);

        $hostingProduct = new ProductFactory()->for(new ProductGroupFactory()->hosting())->createOne([
            'name' => 'standard+',
            'slug' => 'hosting_standard+',
        ]);

        new ProductPriceComponentFactory()
            ->for($extensionProduct)
            ->registration()
            ->createOne(['price' => $regular]);
        new ProductPriceComponentFactory()
            ->for($hostingProduct)
            ->registration()
            ->createOne(['price' => $regular + 200]);

        if ($promotion !== null) {
            new ProductPriceComponentFactory()->for($extensionProduct)->createOne([
                'type' => PriceComponentType::PROMOTION,
                'price' => $promotion,
            ]);
            new ProductPriceComponentFactory()->for($hostingProduct)->createOne([
                'type' => PriceComponentType::PROMOTION,
                'price' => $promotion + 200,
            ]);
        }

        if ($createAddon) {
            $addonProduct = new ProductFactory()->for(new ProductGroupFactory()->addon())->createOne([
                'name' => 'IPV4',
                'slug' => 'ip-4',
            ]);

            new ProductPriceComponentFactory()
                ->for($addonProduct)
                ->registration()
                ->createOne([
                    'price' => $regular,
                    'billing_period' => 12,
                    'contract_period' => 12,
                ]);

            if ($promotion !== null) {
                new ProductPriceComponentFactory()->for($addonProduct)->createOne([
                    'type' => PriceComponentType::PROMOTION,
                    'price' => $promotion,
                ]);
            }
        }

        $this->customer->productGroups()->save($extensionGroup, ['discount' => $discount ?? 0]);
    }
}
