<?php

declare(strict_types=1);

namespace Tests\Domain\Products\Integration;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ItemNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\MigratedCustomersFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\DTO\PriceRequest;
use Waterfront\Domain\Products\DTO\ProlongationPriceRequest;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Exceptions\ProductPriceNotDiscountableException;
use Waterfront\Domain\Products\MigrationsPriceDiscounts;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductDiscount;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;

#[CoversClass(MigrationsPriceDiscounts::class)]
class MigrationsPriceDiscountsTest extends IntegrationTestCase
{
    private int $period         = 12;

    private int $shortPeriod    = 1;

    private int $regularPrice   = 1000;

    private int $migrationPrice = 800;

    private string $productSlug = 'extension_nl';

    private Customer $customer;

    private ProductPriceComponent $prolongationProductPrice;

    private Product $domainProduct;

    private PriceResolver $priceResolver;

    private MigrationsPriceDiscounts $migrationDiscounts;

    private MigratedCustomer $migratedCustomer;

    public function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading(false);

        $this->migrationDiscounts = self::resolve(MigrationsPriceDiscounts::class);
        $this->priceResolver = self::resolve(PriceResolver::class);

        $productGroup = new ProductGroupFactory()->createOne(['slug' => ProductGroupType::EXTENSION]);
        $this->domainProduct = new ProductFactory()->for($productGroup)->createOne(['slug' => $this->productSlug, 'name' => 'nl']);

        $this->prolongationProductPrice = new ProductPriceComponentFactory()->for($this->domainProduct)->prolongation()->createOne([
            'contract_period' => $this->period,
            'billing_period' => $this->period,
            'price' => $this->regularPrice,
        ]);
        $this->prolongationProductPrice->refresh();

        new ProductPriceComponentFactory()->for($this->domainProduct)->registration()->createOne([
            'contract_period' => $this->period,
            'billing_period' => $this->period,
            'price' => $this->regularPrice,
        ]);
        new ProductPriceComponentFactory()->for($this->domainProduct)->prolongation()->createOne([
            'contract_period' => $this->shortPeriod,
            'billing_period' => $this->shortPeriod,
            'price' => $this->regularPrice,
        ]);

        $this->customer = new CustomerFactory()->createOne();
        $this->migratedCustomer = new MigratedCustomersFactory()->createOne([
            'reference_customer_number' => 33,
        ]);
        $this->migratedCustomer->customers()->attach($this->customer);
    }

    #[Test]
    public function assignDiscountForMigration(): void
    {
        $this->migrationDiscounts->assignDiscount(
            $this->migrationPrice,
            $this->period,
            $this->period,
            Price::fromPrice($this->prolongationProductPrice),
            $this->customer
        );

        $discount = $this->customer->productDiscounts->first();
        self::assertInstanceOf(ProductDiscount::class, $discount);

        $price = ProductPriceComponent::where('product_id', $this->domainProduct->id)->where('contract_period', $this->period)->where('type', PriceComponentType::PROLONGATION_STAFFEL)->first();

        self::assertInstanceOf(ProductPriceComponent::class, $price);

        self::assertSame($price->price, $this->migrationPrice);
        self::assertNotSame($price->price, $this->regularPrice);

        $product = $price->product;
        self::assertSame($product->slug, $this->productSlug);

        $priceRequest = new PriceRequest([new ProlongationPriceRequest($this->domainProduct)], $this->customer);
        $list = $this->priceResolver->getPriceList($priceRequest);
        $pricing = $list->getProductPrice(
            $this->domainProduct->slug,
            $this->period,
            $this->period,
        );

        self::assertSame($this->migrationPrice, $pricing->calculatedPrice);
    }

    #[Test]
    public function assignDiscountForMigrationSamePrice(): void
    {
        $this->expectException(ProductPriceNotDiscountableException::class);

        $this->migrationDiscounts->assignDiscount(
            $this->regularPrice,
            $this->period,
            $this->period,
            Price::fromPrice($this->prolongationProductPrice),
            $this->customer
        );

        $discount = $this->customer->productDiscounts->first();
        self::assertNull($discount);

        $prices = ProductPriceComponent::query()->where(['product_id' => $this->domainProduct->id])->get();

        self::assertCount(3, $prices);

        $priceRequest = new PriceRequest([new ProlongationPriceRequest($this->domainProduct)], $this->customer);
        $list = $this->priceResolver->getPriceList($priceRequest);
        $pricing = $list->getProductPrice(
            $this->domainProduct->slug,
            $this->period,
            $this->period,
        );

        self::assertSame($this->regularPrice, $pricing->calculatedPrice);
    }

    #[Test]
    public function assignDiscountForMigrationHigherPrice(): void
    {
        $this->expectException(ProductPriceNotDiscountableException::class);

        $this->migrationDiscounts->assignDiscount(
            $this->regularPrice + 100,
            $this->period,
            $this->period,
            Price::fromPrice($this->prolongationProductPrice),
            $this->customer
        );

        $discount = $this->customer->productDiscounts->first();
        self::assertNull($discount);

        $prices = ProductPriceComponent::query()->where(['product_id' => $this->domainProduct->id])->get();

        self::assertCount(3, $prices);

        $priceRequest = new PriceRequest([new ProlongationPriceRequest($this->domainProduct)], $this->customer);
        $list = $this->priceResolver->getPriceList($priceRequest);
        $pricing = $list->getProductPrice(
            $this->domainProduct->slug,
            $this->period,
            $this->period,
        );

        self::assertSame($this->regularPrice, $pricing->calculatedPrice);
    }

    #[Test]
    public function assignDiscountForNonMigratedCustomer(): void
    {
        $this->customer->migratedCustomers()->detach($this->migratedCustomer);
        $this->expectException(ItemNotFoundException::class);

        $this->migrationDiscounts->assignDiscount(
            $this->migrationPrice,
            $this->period,
            $this->period,
            Price::fromPrice($this->prolongationProductPrice),
            $this->customer
        );
    }
}
