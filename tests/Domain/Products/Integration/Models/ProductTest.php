<?php

declare(strict_types=1);

namespace Tests\Domain\Products\Integration\Models;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductGroup;

#[CoversClass(Product::class)]
class ProductTest extends IntegrationTestCase
{
    private Product $extensionProduct;

    private ProductGroup $hostingGroup;

    protected function setUp(): void
    {
        parent::setUp();

        $extensionGroup = new ProductGroupFactory()->createOne(['slug' => 'extension', 'name' => 'Extension']);
        $this->hostingGroup = new ProductGroupFactory()->createOne(['slug' => 'hosting', 'name' => 'Hosting']);
        $this->extensionProduct = new ProductFactory()->createOne([
            'product_group_id' => $extensionGroup->id,
            'name' => '.nl',
            'slug' => 'extension_nl',
        ]);
    }

    #[Test]
    public function isBaseKitProduct(): void
    {
        new ProductSpecFactory()->for($this->extensionProduct)->createOne(['name' => ProductSpecName::BASEKIT_PACKAGE_REFERENCE]);
        self::assertTrue($this->extensionProduct->isBaseKitProduct());

        $newProduct = new ProductFactory()->createOne([
            'product_group_id' => $this->hostingGroup->id,
            'name' => 'premium-only',
            'slug' => null, // Slug has to be null here in order for the creating event to generate the correct slug.
        ]);
        self::assertFalse($newProduct->isBaseKitProduct());
    }

    #[Test]
    public function generatedSlug(): void
    {
        $newProduct = new ProductFactory()->createOne([
            'product_group_id' => $this->hostingGroup->id,
            'name' => 'premium-only',
            'slug' => null, // Slug has to be null here in order for the creating event to generate the correct slug.
        ]);
        self::assertDatabaseHas('products', [
            'id' => $newProduct->id,
        ]);
        self::assertSame('hosting_premium_only', $newProduct->slug);
    }
}
