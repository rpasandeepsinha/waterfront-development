<?php

declare(strict_types=1);

namespace Tests\Apps\API\Compass\Controllers;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Controllers\SearchController;
use Waterfront\Apps\API\Compass\Resources\Enum\SearchType;
use Waterfront\Domain\Products\Models\Product;

#[CoversClass(SearchController::class)]
class SearchProductControllerTest extends IntegrationTestCase
{
    private Product $hostingProduct;

    private Product $extensionProduct;

    protected function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading(false);

        $extensionProductGroup = new ProductGroupFactory()->extension()->createOne();
        $this->extensionProduct = new ProductFactory()->createOne([
            'product_group_id' => $extensionProductGroup->id,
            'name' => '.nl',
            'slug' => 'extension_nl',
        ]);

        $hostingProductGroup = new ProductGroupFactory()->hosting()->createOne();
        $this->hostingProduct = new ProductFactory()->for($hostingProductGroup)->createOne([
            'name' => 'wow',
            'slug' => 'hosting_basic',
        ]);
    }

    #[Test]
    public function searchHostingProduct(): void
    {
        $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.search.products', ['searchterm' => 'basic']))
            ->assertOk()
            ->assertExactJson([
                [
                    'name' => $this->hostingProduct->name,
                    'slug' => $this->hostingProduct->slug,
                    'uuid' => $this->hostingProduct->uuid,
                    'product_group' => $this->hostingProduct->productGroup->name,
                    'type' => SearchType::PRODUCT->value,
                ],
            ]);
    }

    #[Test]
    public function searchHostingNameCaseSensitivity(): void
    {
        $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.search.products', ['searchterm' => 'Wow']))
            ->assertOk()
            ->assertExactJson([
                [
                    'name' => $this->hostingProduct->name,
                    'slug' => $this->hostingProduct->slug,
                    'uuid' => $this->hostingProduct->uuid,
                    'product_group' => $this->hostingProduct->productGroup->name,
                    'type' => SearchType::PRODUCT->value,
                ],
            ]);
    }

    #[Test]
    public function searchExtensionProductBasedOnSlug(): void
    {
        $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.search.products', ['searchterm' => 'extension_nl']))
            ->assertOk()
            ->assertExactJson([
                [
                    'name' => $this->extensionProduct->name,
                    'slug' => $this->extensionProduct->slug,
                    'uuid' => $this->extensionProduct->uuid,
                    'product_group' => $this->extensionProduct->productGroup->name,
                    'type' => SearchType::PRODUCT->value,
                ],
            ]);
    }

    #[Test]
    public function searchExtensionProductBasedOnName(): void
    {
        $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.search.products', ['searchterm' => '.nl']))
            ->assertOk()
            ->assertExactJson([
                [
                    'name' => $this->extensionProduct->name,
                    'slug' => $this->extensionProduct->slug,
                    'uuid' => $this->extensionProduct->uuid,
                    'product_group' => $this->extensionProduct->productGroup->name,
                    'type' => SearchType::PRODUCT->value,
                ],
            ]);
    }
}
