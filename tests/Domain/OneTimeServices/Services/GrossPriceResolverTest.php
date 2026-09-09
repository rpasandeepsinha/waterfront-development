<?php

declare(strict_types=1);

namespace Tests\Domain\OneTimeServices\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\OneTimeServices\Services\GrossPriceResolver;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductPriceAlternative;

#[CoversClass(GrossPriceResolver::class)]
class GrossPriceResolverTest extends IntegrationTestCase
{
    private Product $productWithoutAlternativePrice;

    private Product $productWithAlternativePrice;

    private Product $oneTimeServiceProduct;

    private GrossPriceResolver $grossPriceResolver;

    public function setUp(): void
    {
        parent::setUp();

        $extensionGroup = new ProductGroupFactory()->extension()->createOne();
        $this->productWithoutAlternativePrice = new ProductFactory()
            ->for($extensionGroup)
            ->createOne();

        $this->productWithAlternativePrice = new ProductFactory()
            ->for($extensionGroup)
            ->createOne();

        $this->oneTimeServiceProduct = new ProductFactory()
            ->for(new ProductGroupFactory()->oneTimeService())
            ->createOne();
        $price = new ProductPriceComponentFactory()
            ->oneTimeService()
            ->for($this->oneTimeServiceProduct)
            ->createOne([
                'price' => 2500,
            ]);

        $alternativePrice = new ProductPriceAlternative();
        $alternativePrice->product_id = $this->oneTimeServiceProduct->id;
        $alternativePrice->billing_period = $price->billing_period;
        $alternativePrice->contract_period = $price->contract_period;
        $alternativePrice->alternative_product_id = $this->productWithAlternativePrice->id;
        $alternativePrice->gross_price = 5000;
        $alternativePrice->save();

        $this->grossPriceResolver = self::resolve(GrossPriceResolver::class);
    }

    #[Test]
    public function getsCorrectGrossPrices(): void
    {
        self::assertSame(2500, $this->grossPriceResolver->getGrossPrice($this->oneTimeServiceProduct, $this->productWithoutAlternativePrice->id));
        self::assertSame(5000, $this->grossPriceResolver->getGrossPrice($this->oneTimeServiceProduct, $this->productWithAlternativePrice->id));
    }
}
