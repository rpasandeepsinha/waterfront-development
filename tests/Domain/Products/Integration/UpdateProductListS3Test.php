<?php

declare(strict_types=1);

namespace Tests\Domain\Products\Integration;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ExperimentFactory;
use Tests\Factories\HostingProductCompositionFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductIntroductionDiscountsFactory;
use Tests\Factories\ProductPeriodFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Atlantis\Resources\Products\ProductListResourceFactory;
use Waterfront\Domain\Experiment\Enums\ExperimentType;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Products\DTO\PriceList;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Products\ProductListUpdater;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Domain\Products\Repositories\HostingProductCompositionRepository;
use Waterfront\Domain\Products\Repositories\ProductPromotionsRepository;
use Waterfront\Domain\Products\Repositories\ProductRepository;

#[CoversClass(ProductListUpdater::class)]
class UpdateProductListS3Test extends IntegrationTestCase
{
    private ProductGroup $extensionProductGroup;

    public function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading(false);
        CarbonImmutable::setTestNow();
        Bus::fake();

        // Workaround to always start creating product prices with the same IDs
        DB::statement(<<<SQL
            ALTER SEQUENCE product_prices_id_seq RESTART;
        SQL);

        $this->extensionProductGroup = $extensionProductGroup = ProductGroupFactory::new()->extension()->createOne();

        $nlProduct = ProductFactory::new()->for($extensionProductGroup)->createOne([
            'name' => '.nl',
            'slug' => 'extension_nl',
            'description' => '',
            'weight' => 5,
        ]);

        ProductPriceComponentFactory::new()->for($nlProduct)->registration()->createOne([
            'billing_period' => 12,
            'contract_period' => 12,
            'price' => 10,
        ]);

        new ProductIntroductionDiscountsFactory()->for($nlProduct)->createOne(['max_uses_per_customer' => 5, 'first_months_discount_period' => 3, 'contract_period' => 12]);

        ProductPriceComponentFactory::new()->for($nlProduct)->introduction()->createOne([
            'billing_period' => 12,
            'contract_period' => 12,
            'price' => 8,
        ]);

        $beProduct = ProductFactory::new()->for($extensionProductGroup)->createOne([
            'name' => '.be',
            'slug' => 'extension_be',
            'description' => '',
            'weight' => 4,
        ]);

        ProductPriceComponentFactory::new()->for($beProduct)->registration()->createOne([
            'billing_period' => 12,
            'contract_period' => 12,
            'price' => 10,
        ]);

        $hostingProductGroup = ProductGroupFactory::new()->hosting()->createOne();

        $hostingProduct = ProductFactory::new()->for($hostingProductGroup)->createOne([
            'description' => '',
            'name' => 'Test 1',
            'slug' => 'test-1',
            'weight' => 3,
        ]);

        new ProductPeriodFactory()->for($hostingProduct)->createOne([
            'billing_period' => 12,
            'contract_period' => 12,
            'is_default' => true,
        ]);
        new ProductPriceComponentFactory()->for($hostingProduct)->createOne([
            'billing_period' => 12,
            'contract_period' => 12,
            'type' => PriceComponentType::REGISTRATION,
            'price' => 10,
        ]);
        new ProductPriceComponentFactory()->for($hostingProduct)->createOne([
            'billing_period' => 12,
            'contract_period' => 12,
            'type' => PriceComponentType::PROMOTION,
            'price' => 5,
        ]);
        new ProductPriceComponentFactory()->for($hostingProduct)->prolongation()->createOne([
            'billing_period' => 12,
            'contract_period' => 12,
            'price' => 10,
        ]);

        $wpHostingProduct = ProductFactory::new()->for($hostingProductGroup)->createOne([
            'description' => 'Hosting with WP',
            'name' => 'Hosting with WP',
            'slug' => 'test-1-wp',
            'weight' => 9999,
        ]);

        new ProductPeriodFactory()->for($wpHostingProduct)->createOne([
            'billing_period' => 12,
            'contract_period' => 12,
            'is_default' => true,
        ]);
        new ProductPriceComponentFactory()->for($wpHostingProduct)->createOne([
            'billing_period' => 12,
            'contract_period' => 12,
            'type' => PriceComponentType::REGISTRATION,
            'price' => 50,
        ]);
        new ProductPriceComponentFactory()->for($wpHostingProduct)->prolongation()->createOne([
            'billing_period' => 12,
            'contract_period' => 12,
            'price' => 50,
        ]);

        $webOnlyHostingProduct = ProductFactory::new()->for($hostingProductGroup)->createOne([
            'slug' => 'web_only_1',
            'name' => 'Web Only',
            'description' => '',
            'weight' => 2,
        ]);

        new ProductPeriodFactory()->for($webOnlyHostingProduct)->createOne([
            'billing_period' => 12,
            'contract_period' => 12,
            'is_default' => true,
        ]);
        new ProductPriceComponentFactory()->for($webOnlyHostingProduct)->createOne([
            'billing_period' => 12,
            'contract_period' => 12,
            'type' => PriceComponentType::REGISTRATION,
            'price' => 50,
        ]);
        new ProductPriceComponentFactory()->for($webOnlyHostingProduct)->prolongation()->createOne([
            'billing_period' => 12,
            'contract_period' => 12,
            'price' => 50,
        ]);

        $wpWebOnlyHostingProduct = ProductFactory::new()->for($hostingProductGroup)->createOne([
            'slug' => 'web_only_wp_1',
            'name' => 'Web Only with WP',
            'description' => '',
            'weight' => 9998,
        ]);

        new ProductPeriodFactory()->for($wpWebOnlyHostingProduct)->createOne([
            'billing_period' => 12,
            'contract_period' => 12,
            'is_default' => true,
        ]);
        new ProductPriceComponentFactory()->for($wpWebOnlyHostingProduct)->createOne([
            'billing_period' => 12,
            'contract_period' => 12,
            'type' => PriceComponentType::REGISTRATION,
            'price' => 50,
        ]);
        new ProductPriceComponentFactory()->for($wpWebOnlyHostingProduct)->prolongation()->createOne([
            'billing_period' => 12,
            'contract_period' => 12,
            'price' => 50,
        ]);

        $mailOnlyHostingProduct = ProductFactory::new()->for($hostingProductGroup)->createOne([
            'slug' => 'mail_only_1',
            'name' => 'Mail Only',
            'description' => '',
            'weight' => 1,
        ]);

        new ProductPeriodFactory()->for($mailOnlyHostingProduct)->createOne([
            'billing_period' => 12,
            'contract_period' => 12,
            'is_default' => true,
        ]);
        new ProductPriceComponentFactory()->for($mailOnlyHostingProduct)->createOne([
            'billing_period' => 12,
            'contract_period' => 12,
            'type' => PriceComponentType::REGISTRATION,
            'price' => 50,
        ]);
        new ProductPriceComponentFactory()->for($mailOnlyHostingProduct)->prolongation()->createOne([
            'billing_period' => 12,
            'contract_period' => 12,
            'price' => 50,
        ]);

        $experiment = new ExperimentFactory()->createOne(['id' => 1234, 'slug' => ExperimentType::PRICING_LADDER]);

        $experiment->products()->save($nlProduct);
        $experiment->products()->save($beProduct);

        HostingProductCompositionFactory::new()
            ->for($hostingProduct, 'composedProduct')
            ->for($mailOnlyHostingProduct, 'mailOnlyProduct')
            ->for($webOnlyHostingProduct, 'webOnlyProduct')
            ->for($wpWebOnlyHostingProduct, 'wpWebOnlyProduct')
            ->for($wpHostingProduct, 'wpComposedProduct')
            ->createOne();

        $nlProduct->productGroup->uuid = 'dc4702f8-0609-4566-ab9a-162ffea3ecc0';
        $nlProduct->productGroup->save();

        $nlProduct->uuid = '0f88f915-cd1b-40c1-8c3d-0cddfd98dada';
        $nlProduct->save();

        $beProduct->productGroup->uuid = 'dc4702f8-0609-4566-ab9a-162ffea3ecc0';
        $beProduct->productGroup->save();

        $beProduct->uuid = 'b766958f-537b-4ce7-aaa1-2dabfdc204f8';
        $beProduct->save();

        $hostingProductGroup->uuid = '9ded82af-3aae-4408-b0c5-060aee0d4a16';
        $hostingProductGroup->save();

        $mailOnlyHostingProduct->uuid = 'ab06f127-24c7-44fa-9da3-bb23ade7f036';
        $mailOnlyHostingProduct->save();

        $webOnlyHostingProduct->uuid = '992bca7c-1e8e-45e3-95be-55eb4df33af4';
        $webOnlyHostingProduct->save();

        $wpWebOnlyHostingProduct->uuid = '5bf041b4-bd91-4c3c-936c-2dbb415f0843';
        $wpWebOnlyHostingProduct->save();

        $hostingProduct->uuid = '2064b561-9bd2-4aa7-9b98-eaf77dd9253a';
        $hostingProduct->save();

        $wpHostingProduct->uuid = '49e52b85-0417-4f5c-8190-3cb4280bba16';
        $wpHostingProduct->save();
    }

    #[Test]
    public function updateProductList(): void
    {
        $productArray = include __DIR__ . '/data/valid_product_list.php';
        $productArray['productPromotions'] = null;
        $validProductPriceList = json_encode($productArray, JSON_THROW_ON_ERROR);

        $filesystem = self::createMock(Filesystem::class);
        $filesystem->expects(self::once())
            ->method('put')
            ->with('product-list.json', $validProductPriceList);

        $productListService = new ProductListUpdater(
            self::resolve(PriceResolver::class),
            $filesystem,
            self::resolve(ProductListResourceFactory::class),
            self::createStub(ProductPromotionsRepository::class),
            self::resolve(HostingProductCompositionRepository::class),
            self::resolve(ProductRepository::class),
        );
        $productListService->update();
    }

    #[Test]
    public function updateProductListWillNotExportProductsWithoutPrices(): void
    {
        $productWithoutPrice = new ProductFactory()->for($this->extensionProductGroup)->createOne();

        $productArray = include __DIR__ . '/data/valid_product_list.php';
        $productArray['productPromotions'] = null;
        $validProductPriceList = json_encode($productArray, JSON_THROW_ON_ERROR);

        $filesystem = self::createMock(Filesystem::class);
        $filesystem->expects(self::once())
            ->method('put')
            ->with('product-list.json', $validProductPriceList);

        $productListService = new ProductListUpdater(
            self::resolve(PriceResolver::class),
            $filesystem,
            self::resolve(ProductListResourceFactory::class),
            self::createStub(ProductPromotionsRepository::class),
            self::resolve(HostingProductCompositionRepository::class),
            self::resolve(ProductRepository::class),
        );
        $productListService->update();
    }

    #[Test]
    public function updateProductListFail(): void
    {
        $priceList = new PriceList();
        $resolver = self::createStub(PriceResolver::class);
        $resolver->method('getPriceList')
            ->willReturn($priceList);

        $filesystem = self::createMock(Filesystem::class);
        $filesystem->expects(self::never())
            ->method('put');

        $productListService = new ProductListUpdater(
            $resolver,
            $filesystem,
            self::resolve(ProductListResourceFactory::class),
            self::createStub(ProductPromotionsRepository::class),
            self::resolve(HostingProductCompositionRepository::class),
            self::resolve(ProductRepository::class),
        );
        $productListService->update();
    }
}
