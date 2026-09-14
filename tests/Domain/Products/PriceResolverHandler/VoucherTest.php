<?php

declare(strict_types=1);

namespace Tests\Domain\Products\PriceResolverHandler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\VoucherFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Pricing\DTO\PriceComponents\PriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\VoucherPriceComponent;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\DTO\PriceRequest;
use Waterfront\Domain\Products\DTO\RegistrationPriceRequest;
use Waterfront\Domain\Products\Enums\ProductPriceType;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Domain\Voucher\Enum\VoucherAmountType;

#[CoversClass(PriceResolver::class)]
class VoucherTest extends IntegrationTestCase
{
    private PriceResolver $priceResolver;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->priceResolver = self::resolve(PriceResolver::class);
        $this->customer = new CustomerFactory()->createOne();
    }

    #[Test]
    public function voucherGetsApplied(): void
    {
        $product = new ProductFactory()->for(new ProductGroupFactory())->createOne();
        new ProductPriceComponentFactory()->for($product)->createOne([
            'type' => PriceComponentType::REGISTRATION,
            'price' => 999,
        ]);
        $voucher = new VoucherFactory()
            ->for($product->productGroup)
            ->for($product)
            ->createOne(['amount_type' => VoucherAmountType::FIXED, 'amount' => 500]);

        $priceList = $this->priceResolver->getPriceList(
            new PriceRequest([new RegistrationPriceRequest($product)], $this->customer, [$voucher], true),
        );
        $price = $priceList->getProductPrice($product->slug, 12, 12);

        $voucherPriceComponent = array_find(
            $price->appliedPriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent->type === PriceComponentType::VOUCHER,
        );
        self::assertInstanceOf(VoucherPriceComponent::class, $voucherPriceComponent);
        self::assertSame(500, $voucherPriceComponent->fixedDiscount);
        self::assertSame(500, $voucherPriceComponent->appliedAmount);
        self::assertSame(499, $voucherPriceComponent->newPrice);
        self::assertSame(499, $price->calculatedPrice);
    }

    // When the amount of a voucher is higher than the product price, it should be applied as much as possible, and not be rejected.
    #[Test]
    public function voucherGetsPartiallyApplied(): void
    {
        $product = new ProductFactory()->for(new ProductGroupFactory())->createOne();
        new ProductPriceComponentFactory()->for($product)->createOne([
            'type' => PriceComponentType::REGISTRATION,
            'price' => 999,
        ]);
        $voucher = new VoucherFactory()
            ->for($product->productGroup)
            ->for($product)
            ->createOne(['amount_type' => VoucherAmountType::FIXED, 'amount' => 1500]);

        $priceList = $this->priceResolver->getPriceList(
            new PriceRequest([new RegistrationPriceRequest($product)], $this->customer, [$voucher], true),
        );
        $price = $priceList->getProductPrice($product->slug, 12, 12);

        $voucherPriceComponent = array_find(
            $price->appliedPriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent->type === PriceComponentType::VOUCHER,
        );
        self::assertInstanceOf(VoucherPriceComponent::class, $voucherPriceComponent);
        self::assertSame(1500, $voucherPriceComponent->fixedDiscount);
        self::assertSame(999, $voucherPriceComponent->appliedAmount);
        self::assertSame(0, $voucherPriceComponent->newPrice);
        self::assertSame(0, $price->calculatedPrice);
    }

    #[Test]
    public function percentageVoucherGetsApplied(): void
    {
        $product = new ProductFactory()->for(new ProductGroupFactory())->createOne();
        new ProductPriceComponentFactory()->for($product)->createOne([
            'type' => PriceComponentType::REGISTRATION,
            'price' => 1234,
        ]);
        $voucher = new VoucherFactory()
            ->for($product->productGroup)
            ->for($product)
            ->createOne(['amount_type' => VoucherAmountType::PERCENTAGE, 'amount' => 10]);

        $priceList = $this->priceResolver->getPriceList(
            new PriceRequest([new RegistrationPriceRequest($product)], $this->customer, [$voucher], true),
        );
        $price = $priceList->getProductPrice($product->slug, 12, 12);

        $voucherPriceComponent = array_find(
            $price->appliedPriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent->type === PriceComponentType::VOUCHER,
        );
        self::assertInstanceOf(VoucherPriceComponent::class, $voucherPriceComponent);
        self::assertSame(10.0, $voucherPriceComponent->percentageDiscount);
        self::assertSame(123, $voucherPriceComponent->appliedAmount);
        self::assertSame(1111, $voucherPriceComponent->newPrice);
        self::assertSame(1111, $price->calculatedPrice);
    }

    // When there are multiple vouchers the one with the highest amount should be used. We never apply two vouchers for the same product price.
    #[Test]
    public function highestAmountVoucherGetsApplied(): void
    {
        $product = new ProductFactory()->for(new ProductGroupFactory())->createOne();
        new ProductPriceComponentFactory()->for($product)->createOne([
            'type' => PriceComponentType::REGISTRATION,
            'price' => 1899,
        ]);
        $voucher1 = new VoucherFactory()
            ->for($product->productGroup)
            ->for($product)
            ->createOne(['amount_type' => VoucherAmountType::FIXED, 'amount' => 500]);
        $voucher2 = new VoucherFactory()
            ->for($product->productGroup)
            ->for($product)
            ->createOne(['amount_type' => VoucherAmountType::FIXED, 'amount' => 1000]);

        $priceList = $this->priceResolver->getPriceList(
            new PriceRequest([new RegistrationPriceRequest($product)], $this->customer, [$voucher1, $voucher2], true),
        );
        $price = $priceList->getProductPrice($product->slug, 12, 12);

        $voucherPriceComponents = array_filter(
            $price->appliedPriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent->type === PriceComponentType::VOUCHER,
        );
        self::assertCount(1, $voucherPriceComponents);
        $voucherPriceComponent = array_first($voucherPriceComponents);
        self::assertInstanceOf(VoucherPriceComponent::class, $voucherPriceComponent);
        self::assertSame($voucher2->code, $voucherPriceComponent->voucher->code);
        self::assertSame(1000, $voucherPriceComponent->fixedDiscount);
        self::assertSame(1000, $voucherPriceComponent->appliedAmount);
        self::assertSame(899, $voucherPriceComponent->newPrice);
        self::assertSame(899, $price->calculatedPrice);
    }

    // When there are multiple vouchers the one with the highest amount should be used. We never apply two vouchers for the same product price.
    #[Test]
    public function highestPercentageVoucherGetsApplied(): void
    {
        $product = new ProductFactory()->for(new ProductGroupFactory())->createOne();
        new ProductPriceComponentFactory()->for($product)->createOne([
            'type' => PriceComponentType::REGISTRATION,
            'price' => 2495,
        ]);
        $voucher1 = new VoucherFactory()
            ->for($product->productGroup)
            ->for($product)
            ->createOne(['amount_type' => VoucherAmountType::PERCENTAGE, 'amount' => 20]);
        $voucher2 = new VoucherFactory()
            ->for($product->productGroup)
            ->for($product)
            ->createOne(['amount_type' => VoucherAmountType::PERCENTAGE, 'amount' => 30]);

        $priceList = $this->priceResolver->getPriceList(
            new PriceRequest([new RegistrationPriceRequest($product)], $this->customer, [$voucher1, $voucher2], true),
        );
        $price = $priceList->getProductPrice($product->slug, 12, 12);

        $voucherPriceComponents = array_filter(
            $price->appliedPriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent->type === PriceComponentType::VOUCHER,
        );
        self::assertCount(1, $voucherPriceComponents);
        $voucherPriceComponent = array_first($voucherPriceComponents);
        self::assertInstanceOf(VoucherPriceComponent::class, $voucherPriceComponent);
        self::assertSame($voucher2->code, $voucherPriceComponent->voucher->code);
        self::assertSame(30.0, $voucherPriceComponent->percentageDiscount);
        self::assertSame(748, $voucherPriceComponent->appliedAmount);
        self::assertSame(1747, $voucherPriceComponent->newPrice);
        self::assertSame(1747, $price->calculatedPrice);
    }

    // When there is a voucher that is product specific it should always be used over a voucher specific to a product group, regardless of the amount.
    #[Test]
    public function productSpecificVoucherHasPrecedenceOverAProductGroupVoucher(): void
    {
        $product = new ProductFactory()->for(new ProductGroupFactory())->createOne();
        new ProductPriceComponentFactory()->for($product)->createOne([
            'type' => PriceComponentType::REGISTRATION,
            'price' => 777,
        ]);
        $voucher1 = new VoucherFactory()
            ->for($product->productGroup)
            ->for($product)
            ->createOne(['amount_type' => VoucherAmountType::FIXED, 'amount' => 111]);
        $voucher2 = new VoucherFactory()->for($product->productGroup)->createOne([
            'amount_type' => VoucherAmountType::FIXED,
            'amount' => 222,
        ]);

        $priceList = $this->priceResolver->getPriceList(
            new PriceRequest([new RegistrationPriceRequest($product)], $this->customer, [$voucher1, $voucher2], true),
        );
        $price = $priceList->getProductPrice($product->slug, 12, 12);

        $voucherPriceComponents = array_filter(
            $price->appliedPriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent->type === PriceComponentType::VOUCHER,
        );
        self::assertCount(1, $voucherPriceComponents);
        $voucherPriceComponent = array_first($voucherPriceComponents);
        self::assertInstanceOf(VoucherPriceComponent::class, $voucherPriceComponent);
        self::assertSame($voucher1->code, $voucherPriceComponent->voucher->code);
        self::assertSame(111, $voucherPriceComponent->fixedDiscount);
        self::assertSame(111, $voucherPriceComponent->appliedAmount);
        self::assertSame(666, $voucherPriceComponent->newPrice);
        self::assertSame(666, $price->calculatedPrice);
    }

    #[Test]
    public function voucherOnlyGetsAppliedWhenCustomerIsSpecified(): void
    {
        $product = new ProductFactory()->for(new ProductGroupFactory())->createOne();
        new ProductPriceComponentFactory()->for($product)->createOne([
            'type' => PriceComponentType::REGISTRATION,
            'price' => 1234,
        ]);
        $voucher = new VoucherFactory()
            ->for($product->productGroup)
            ->for($product)
            ->createOne(['amount_type' => VoucherAmountType::FIXED, 'amount' => 123]);

        $priceList = $this->priceResolver->getPriceList(
            new PriceRequest([new RegistrationPriceRequest($product)], null, [$voucher], true),
        );
        $price = $priceList->getProductPrice($product->slug, 12, 12);

        $voucherPriceComponent = array_find(
            $price->appliedPriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent->type === PriceComponentType::VOUCHER,
        );
        self::assertNull($voucherPriceComponent);
        self::assertSame(1234, $price->calculatedPrice);
    }

    #[Test]
    public function onlyUsableVouchersGetApplied(): void
    {
        $product = new ProductFactory()->for(new ProductGroupFactory())->createOne();
        new ProductPriceComponentFactory()->for($product)->createOne([
            'type' => PriceComponentType::REGISTRATION,
            'price' => 799,
        ]);
        $voucher1 = new VoucherFactory()->for($product->productGroup)->createOne([
            'amount_type' => VoucherAmountType::FIXED,
            'amount' => 122,
        ]);
        $voucher2 = new VoucherFactory()->for($product->productGroup)->createOne([
            'amount_type' => VoucherAmountType::FIXED,
            'amount' => 333,
            'max_claims' => 0,
        ]);

        $priceList = $this->priceResolver->getPriceList(
            new PriceRequest([new RegistrationPriceRequest($product)], $this->customer, [$voucher1, $voucher2], true),
        );
        $price = $priceList->getProductPrice($product->slug, 12, 12);

        $voucherPriceComponents = array_filter(
            $price->appliedPriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent->type === PriceComponentType::VOUCHER,
        );
        self::assertCount(1, $voucherPriceComponents);
        $voucherPriceComponent = array_first($voucherPriceComponents);
        self::assertInstanceOf(VoucherPriceComponent::class, $voucherPriceComponent);
        self::assertSame($voucher1->code, $voucherPriceComponent->voucher->code);
        self::assertSame(122, $voucherPriceComponent->fixedDiscount);
        self::assertSame(122, $voucherPriceComponent->appliedAmount);
        self::assertSame(677, $voucherPriceComponent->newPrice);
        self::assertSame(677, $price->calculatedPrice);
    }

    #[Test]
    public function voucherGetsAppliedOnCorrectProduct(): void
    {
        $productGroup = new ProductGroupFactory()->createOne();
        $product1 = new ProductFactory()->for($productGroup)->createOne();
        new ProductPriceComponentFactory()->for($product1)->createOne([
            'type' => PriceComponentType::REGISTRATION,
            'price' => 9876,
        ]);
        $product2 = new ProductFactory()->for($productGroup)->createOne();
        new ProductPriceComponentFactory()->for($product2)->createOne([
            'type' => PriceComponentType::REGISTRATION,
            'price' => 1234,
        ]);
        $voucher = new VoucherFactory()
            ->for($product1->productGroup)
            ->for($product1)
            ->createOne(['amount_type' => VoucherAmountType::FIXED, 'amount' => 432]);

        $priceList = $this->priceResolver->getPriceList(
            new PriceRequest(
                [new RegistrationPriceRequest($product1), new RegistrationPriceRequest($product2)],
                $this->customer,
                [$voucher],
                true,
            ),
        );
        $priceProduct1 = $priceList->getProductPrice($product1->slug, 12, 12);
        $priceProduct2 = $priceList->getProductPrice($product2->slug, 12, 12);

        $voucherPriceComponent = array_find(
            $priceProduct1->appliedPriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent->type === PriceComponentType::VOUCHER,
        );
        self::assertInstanceOf(VoucherPriceComponent::class, $voucherPriceComponent);
        self::assertSame(432, $voucherPriceComponent->fixedDiscount);
        self::assertSame(432, $voucherPriceComponent->appliedAmount);
        self::assertSame(9444, $voucherPriceComponent->newPrice);
        self::assertSame(9444, $priceProduct1->calculatedPrice);

        $voucherPriceComponent = array_find(
            $priceProduct2->appliedPriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent->type === PriceComponentType::VOUCHER,
        );
        self::assertNull($voucherPriceComponent);
        self::assertSame(1234, $priceProduct2->calculatedPrice);
    }

    #[Test]
    public function voucherGetsAppliedOnCorrectProductGroup(): void
    {
        $product1 = new ProductFactory()->for(new ProductGroupFactory()->extension())->createOne();
        new ProductPriceComponentFactory()->for($product1)->createOne([
            'type' => PriceComponentType::REGISTRATION,
            'price' => 6780,
        ]);
        $product2 = new ProductFactory()->for(new ProductGroupFactory()->hosting())->createOne();
        new ProductPriceComponentFactory()->for($product2)->createOne([
            'type' => PriceComponentType::REGISTRATION,
            'price' => 4587,
        ]);
        $voucher = new VoucherFactory()->for($product1->productGroup)->createOne([
            'amount_type' => VoucherAmountType::FIXED,
            'amount' => 911,
        ]);

        $priceList = $this->priceResolver->getPriceList(
            new PriceRequest(
                [new RegistrationPriceRequest($product1), new RegistrationPriceRequest($product2)],
                $this->customer,
                [$voucher],
                true,
            ),
        );
        $priceProduct1 = $priceList->getProductPrice($product1->slug, 12, 12);
        $priceProduct2 = $priceList->getProductPrice($product2->slug, 12, 12);

        $voucherPriceComponent = array_find(
            $priceProduct1->appliedPriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent->type === PriceComponentType::VOUCHER,
        );
        self::assertInstanceOf(VoucherPriceComponent::class, $voucherPriceComponent);
        self::assertSame(911, $voucherPriceComponent->fixedDiscount);
        self::assertSame(911, $voucherPriceComponent->appliedAmount);
        self::assertSame(5869, $voucherPriceComponent->newPrice);
        self::assertSame(5869, $priceProduct1->calculatedPrice);

        $voucherPriceComponent = array_find(
            $priceProduct2->appliedPriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent->type === PriceComponentType::VOUCHER,
        );
        self::assertNull($voucherPriceComponent);
        self::assertSame(4587, $priceProduct2->calculatedPrice);
    }

    // A voucher for a product group is allowed to be spread over multiple product prices.
    #[Test]
    public function voucherForProductGroupGetsAppliedOverMultipleProducts(): void
    {
        $productGroup = new ProductGroupFactory()->createOne();
        $product1 = new ProductFactory()->for($productGroup)->createOne();
        new ProductPriceComponentFactory()->for($product1)->createOne([
            'type' => PriceComponentType::REGISTRATION,
            'price' => 80,
        ]);
        $product2 = new ProductFactory()->for($productGroup)->createOne();
        new ProductPriceComponentFactory()->for($product2)->createOne([
            'type' => PriceComponentType::REGISTRATION,
            'price' => 70,
        ]);
        $voucher = new VoucherFactory()->for($productGroup)->createOne([
            'amount_type' => VoucherAmountType::FIXED,
            'amount' => 120,
        ]);

        $priceList = $this->priceResolver->getPriceList(
            new PriceRequest(
                [new RegistrationPriceRequest($product1), new RegistrationPriceRequest($product2)],
                $this->customer,
                [$voucher],
                true,
            ),
        );
        $priceProduct1 = $priceList->getProductPrice($product1->slug, 12, 12);
        $priceProduct2 = $priceList->getProductPrice($product2->slug, 12, 12);

        $voucherPriceComponent = array_find(
            $priceProduct1->appliedPriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent->type === PriceComponentType::VOUCHER,
        );
        self::assertInstanceOf(VoucherPriceComponent::class, $voucherPriceComponent);
        self::assertSame(120, $voucherPriceComponent->fixedDiscount);
        self::assertSame(80, $voucherPriceComponent->appliedAmount);
        self::assertSame(0, $voucherPriceComponent->newPrice);
        self::assertSame(0, $priceProduct1->calculatedPrice);

        $voucherPriceComponent = array_find(
            $priceProduct2->appliedPriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent->type === PriceComponentType::VOUCHER,
        );
        self::assertInstanceOf(VoucherPriceComponent::class, $voucherPriceComponent);
        self::assertSame(120, $voucherPriceComponent->fixedDiscount);
        self::assertSame(40, $voucherPriceComponent->appliedAmount);
        self::assertSame(30, $voucherPriceComponent->newPrice);
        self::assertSame(30, $priceProduct2->calculatedPrice);
    }

    // A voucher for a product is not allowed to be spread over multiple product prices.
    #[Test]
    public function voucherForProductDoesntGetAppliedOverMultipleProducts(): void
    {
        $product = new ProductFactory()->for(new ProductGroupFactory())->createOne();
        new ProductPriceComponentFactory()->for($product)->createOne([
            'type' => PriceComponentType::REGISTRATION,
            'price' => 80,
        ]);
        $voucher = new VoucherFactory()
            ->for($product->productGroup)
            ->for($product)
            ->createOne(['amount_type' => VoucherAmountType::FIXED, 'amount' => 160]);

        $priceList = $this->priceResolver->getPriceList(
            new PriceRequest([new RegistrationPriceRequest($product, 2, 12, 12)], $this->customer, [$voucher], true),
        );
        $prices = $priceList->getPrices();
        $prices = array_filter($prices, fn (Price $price) => $price->type === ProductPriceType::REGISTRATION);
        $price1 = array_shift($prices);
        self::assertNotNull($price1);
        $price2 = array_shift($prices);
        self::assertNotNull($price2);

        $voucherPriceComponent = array_find(
            $price1->appliedPriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent->type === PriceComponentType::VOUCHER,
        );
        self::assertInstanceOf(VoucherPriceComponent::class, $voucherPriceComponent);
        self::assertSame(160, $voucherPriceComponent->fixedDiscount);
        self::assertSame(80, $voucherPriceComponent->appliedAmount);
        self::assertSame(0, $voucherPriceComponent->newPrice);
        self::assertSame(0, $price1->calculatedPrice);

        $voucherPriceComponent = array_find(
            $price2->appliedPriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent->type === PriceComponentType::VOUCHER,
        );
        self::assertNull($voucherPriceComponent);
        self::assertSame(80, $price2->calculatedPrice);
    }

    // A percentage voucher for a product group will be applied over all products within the group.
    #[Test]
    public function percentageVoucherForProductGroupGetsAppliedOverMultipleProducts(): void
    {
        $product = new ProductFactory()->for(new ProductGroupFactory())->createOne();
        new ProductPriceComponentFactory()->for($product)->createOne([
            'type' => PriceComponentType::REGISTRATION,
            'price' => 11243,
        ]);
        $voucher = new VoucherFactory()->for($product->productGroup)->createOne([
            'amount_type' => VoucherAmountType::PERCENTAGE,
            'amount' => 10,
        ]);

        $priceList = $this->priceResolver->getPriceList(
            new PriceRequest([new RegistrationPriceRequest($product, 2, 12, 12)], $this->customer, [$voucher], true),
        );
        $prices = $priceList->getPrices();
        $prices = array_filter($prices, fn (Price $price) => $price->type === ProductPriceType::REGISTRATION);
        $price1 = array_shift($prices);
        self::assertNotNull($price1);
        $price2 = array_shift($prices);
        self::assertNotNull($price2);

        $voucherPriceComponent = array_find(
            $price1->appliedPriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent->type === PriceComponentType::VOUCHER,
        );
        self::assertInstanceOf(VoucherPriceComponent::class, $voucherPriceComponent);
        self::assertSame(10.0, $voucherPriceComponent->percentageDiscount);
        self::assertSame(1124, $voucherPriceComponent->appliedAmount);
        self::assertSame(10119, $voucherPriceComponent->newPrice);
        self::assertSame(10119, $price1->calculatedPrice);

        $voucherPriceComponent = array_find(
            $price2->appliedPriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent->type === PriceComponentType::VOUCHER,
        );
        self::assertInstanceOf(VoucherPriceComponent::class, $voucherPriceComponent);
        self::assertSame(10.0, $voucherPriceComponent->percentageDiscount);
        self::assertSame(1124, $voucherPriceComponent->appliedAmount);
        self::assertSame(10119, $voucherPriceComponent->newPrice);
        self::assertSame(10119, $price2->calculatedPrice);
    }

    #[Test]
    public function voucherForProductGroupGetsAppliedOverTwoOfTheSameProducts(): void
    {
        $product = new ProductFactory()->for(new ProductGroupFactory())->createOne();
        new ProductPriceComponentFactory()->for($product)->createOne([
            'type' => PriceComponentType::REGISTRATION,
            'price' => 80,
        ]);
        $voucher = new VoucherFactory()->for($product->productGroup)->createOne([
            'amount_type' => VoucherAmountType::FIXED,
            'amount' => 160,
        ]);

        $priceList = $this->priceResolver->getPriceList(
            new PriceRequest([new RegistrationPriceRequest($product, 2, 12, 12)], $this->customer, [$voucher], true),
        );
        $prices = $priceList->getPrices();
        $prices = array_filter($prices, fn (Price $price) => $price->type === ProductPriceType::REGISTRATION);
        $price1 = array_shift($prices);
        self::assertNotNull($price1);
        $price2 = array_shift($prices);
        self::assertNotNull($price2);

        $voucherPriceComponent = array_find(
            $price1->appliedPriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent->type === PriceComponentType::VOUCHER,
        );
        self::assertInstanceOf(VoucherPriceComponent::class, $voucherPriceComponent);
        self::assertSame(160, $voucherPriceComponent->fixedDiscount);
        self::assertSame(80, $voucherPriceComponent->appliedAmount);
        self::assertSame(0, $voucherPriceComponent->newPrice);
        self::assertSame(0, $price1->calculatedPrice);

        $voucherPriceComponent = array_find(
            $price2->appliedPriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent->type === PriceComponentType::VOUCHER,
        );
        self::assertInstanceOf(VoucherPriceComponent::class, $voucherPriceComponent);
        self::assertSame(160, $voucherPriceComponent->fixedDiscount);
        self::assertSame(80, $voucherPriceComponent->appliedAmount);
        self::assertSame(0, $voucherPriceComponent->newPrice);
        self::assertSame(0, $price2->calculatedPrice);
    }

    #[Test]
    public function vouchersForDifferentProductGroupsCorrectlyGetAppliedOnDifferentProducts(): void
    {
        $product1 = new ProductFactory()->for(new ProductGroupFactory()->hosting())->createOne();
        new ProductPriceComponentFactory()->for($product1)->createOne([
            'type' => PriceComponentType::REGISTRATION,
            'price' => 8302,
        ]);
        $voucher1 = new VoucherFactory()->for($product1->productGroup)->createOne([
            'amount_type' => VoucherAmountType::FIXED,
            'amount' => 832,
        ]);
        $product2 = new ProductFactory()->for(new ProductGroupFactory()->extension())->createOne();
        new ProductPriceComponentFactory()->for($product2)->createOne([
            'type' => PriceComponentType::REGISTRATION,
            'price' => 9323,
        ]);
        $voucher2 = new VoucherFactory()->for($product2->productGroup)->createOne([
            'amount_type' => VoucherAmountType::PERCENTAGE,
            'amount' => 39,
        ]);

        $priceList = $this->priceResolver->getPriceList(
            new PriceRequest(
                [new RegistrationPriceRequest($product1), new RegistrationPriceRequest($product2)],
                $this->customer,
                [$voucher1, $voucher2],
                true,
            ),
        );
        $priceProduct1 = $priceList->getProductPrice($product1->slug, 12, 12);
        $priceProduct2 = $priceList->getProductPrice($product2->slug, 12, 12);

        $voucherPriceComponents = array_filter(
            $priceProduct1->appliedPriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent->type === PriceComponentType::VOUCHER,
        );
        self::assertCount(1, $voucherPriceComponents);
        $voucherPriceComponent = array_first($voucherPriceComponents);
        self::assertInstanceOf(VoucherPriceComponent::class, $voucherPriceComponent);
        self::assertSame($voucher1->code, $voucherPriceComponent->voucher->code);
        self::assertSame(832, $voucherPriceComponent->fixedDiscount);
        self::assertSame(832, $voucherPriceComponent->appliedAmount);
        self::assertSame(7470, $voucherPriceComponent->newPrice);
        self::assertSame(7470, $priceProduct1->calculatedPrice);

        $voucherPriceComponents = array_filter(
            $priceProduct2->appliedPriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent->type === PriceComponentType::VOUCHER,
        );
        self::assertCount(1, $voucherPriceComponents);
        $voucherPriceComponent = array_first($voucherPriceComponents);
        self::assertInstanceOf(VoucherPriceComponent::class, $voucherPriceComponent);
        self::assertSame($voucher2->code, $voucherPriceComponent->voucher->code);
        self::assertSame(39.0, $voucherPriceComponent->percentageDiscount);
        self::assertSame(3636, $voucherPriceComponent->appliedAmount);
        self::assertSame(5687, $voucherPriceComponent->newPrice);
        self::assertSame(5687, $priceProduct2->calculatedPrice);
    }

    #[Test]
    public function vouchersForDifferentProductsCorrectlyGetAppliedOnDifferentProducts(): void
    {
        $productGroup = new ProductGroupFactory()->createOne();
        $product1 = new ProductFactory()->for($productGroup)->createOne();
        new ProductPriceComponentFactory()->for($product1)->createOne([
            'type' => PriceComponentType::REGISTRATION,
            'price' => 1199,
        ]);
        $voucher1 = new VoucherFactory()
            ->for($product1->productGroup)
            ->for($product1)
            ->createOne(['amount_type' => VoucherAmountType::FIXED, 'amount' => 88]);
        $product2 = new ProductFactory()->for($productGroup)->createOne();
        new ProductPriceComponentFactory()->for($product2)->createOne([
            'type' => PriceComponentType::REGISTRATION,
            'price' => 23335,
        ]);
        $voucher2 = new VoucherFactory()
            ->for($product2->productGroup)
            ->for($product2)
            ->createOne(['amount_type' => VoucherAmountType::PERCENTAGE, 'amount' => 44]);

        $priceList = $this->priceResolver->getPriceList(
            new PriceRequest(
                [new RegistrationPriceRequest($product1), new RegistrationPriceRequest($product2)],
                $this->customer,
                [$voucher1, $voucher2],
                true,
            ),
        );
        $priceProduct1 = $priceList->getProductPrice($product1->slug, 12, 12);
        $priceProduct2 = $priceList->getProductPrice($product2->slug, 12, 12);

        $voucherPriceComponents = array_filter(
            $priceProduct1->appliedPriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent->type === PriceComponentType::VOUCHER,
        );
        self::assertCount(1, $voucherPriceComponents);
        $voucherPriceComponent = array_first($voucherPriceComponents);
        self::assertInstanceOf(VoucherPriceComponent::class, $voucherPriceComponent);
        self::assertSame($voucher1->code, $voucherPriceComponent->voucher->code);
        self::assertSame(88, $voucherPriceComponent->fixedDiscount);
        self::assertSame(88, $voucherPriceComponent->appliedAmount);
        self::assertSame(1111, $voucherPriceComponent->newPrice);
        self::assertSame(1111, $priceProduct1->calculatedPrice);

        $voucherPriceComponents = array_filter(
            $priceProduct2->appliedPriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent->type === PriceComponentType::VOUCHER,
        );
        self::assertCount(1, $voucherPriceComponents);
        $voucherPriceComponent = array_first($voucherPriceComponents);
        self::assertInstanceOf(VoucherPriceComponent::class, $voucherPriceComponent);
        self::assertSame($voucher2->code, $voucherPriceComponent->voucher->code);
        self::assertSame(44.0, $voucherPriceComponent->percentageDiscount);
        self::assertSame(10267, $voucherPriceComponent->appliedAmount);
        self::assertSame(13068, $voucherPriceComponent->newPrice);
        self::assertSame(13068, $priceProduct2->calculatedPrice);
    }

    #[Test]
    public function vouchersAreAppliedOverProductGroupDiscountsWhenConfigured(): void
    {
        $product = new ProductFactory()->for(new ProductGroupFactory())->createOne();
        $this->customer->productGroups()->attach($product->productGroup->id, ['discount' => 20]);
        new ProductPriceComponentFactory()->for($product)->createOne([
            'type' => PriceComponentType::REGISTRATION,
            'price' => 100,
        ]);
        $voucher1 = new VoucherFactory()
            ->for($product->productGroup)
            ->for($product)
            ->createOne(['amount_type' => VoucherAmountType::FIXED, 'amount' => 50, 'apply_with_discount' => true]);
        $voucher2 = new VoucherFactory()->for($product->productGroup)->createOne([
            'amount_type' => VoucherAmountType::FIXED,
            'amount' => 100,
            'apply_with_discount' => true,
        ]);
        $voucher3 = new VoucherFactory()
            ->for($product->productGroup)
            ->for($product)
            ->createOne([
                'amount_type' => VoucherAmountType::PERCENTAGE,
                'amount' => 12,
                'apply_with_discount' => true,
            ]);
        $voucher4 = new VoucherFactory()->for($product->productGroup)->createOne([
            'amount_type' => VoucherAmountType::PERCENTAGE,
            'amount' => 29,
            'apply_with_discount' => true,
        ]);

        $priceList = $this->priceResolver->getPriceList(
            new PriceRequest(
                [new RegistrationPriceRequest($product)],
                $this->customer,
                [$voucher1, $voucher2, $voucher3, $voucher4],
                true,
            ),
        );
        $price = $priceList->getProductPrice($product->slug, 12, 12);

        $voucherPriceComponents = array_filter(
            $price->appliedPriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent->type === PriceComponentType::VOUCHER,
        );
        self::assertCount(1, $voucherPriceComponents);
        $voucherPriceComponent = array_first($voucherPriceComponents);
        self::assertInstanceOf(VoucherPriceComponent::class, $voucherPriceComponent);
        self::assertSame($voucher1->code, $voucherPriceComponent->voucher->code);
        self::assertSame(50, $voucherPriceComponent->fixedDiscount);
        self::assertSame(50, $voucherPriceComponent->appliedAmount);
        self::assertSame(30, $voucherPriceComponent->newPrice);
        self::assertSame(30, $price->calculatedPrice);
    }

    #[Test]
    public function vouchersAreNotAppliedOverProductGroupDiscountsWhenConfiguredNotTo(): void
    {
        $product = new ProductFactory()->for(new ProductGroupFactory())->createOne();
        $this->customer->productGroups()->attach($product->productGroup->id, ['discount' => 50]);
        new ProductPriceComponentFactory()->for($product)->createOne([
            'type' => PriceComponentType::REGISTRATION,
            'price' => 1899,
        ]);
        $voucher1 = new VoucherFactory()
            ->for($product->productGroup)
            ->for($product)
            ->createOne(['amount_type' => VoucherAmountType::FIXED, 'amount' => 500, 'apply_with_discount' => false]);
        $voucher2 = new VoucherFactory()->for($product->productGroup)->createOne([
            'amount_type' => VoucherAmountType::FIXED,
            'amount' => 1000,
            'apply_with_discount' => false,
        ]);
        $voucher3 = new VoucherFactory()
            ->for($product->productGroup)
            ->for($product)
            ->createOne([
                'amount_type' => VoucherAmountType::PERCENTAGE,
                'amount' => 18,
                'apply_with_discount' => false,
            ]);
        $voucher4 = new VoucherFactory()->for($product->productGroup)->createOne([
            'amount_type' => VoucherAmountType::PERCENTAGE,
            'amount' => 83,
            'apply_with_discount' => false,
        ]);

        $priceList = $this->priceResolver->getPriceList(
            new PriceRequest(
                [new RegistrationPriceRequest($product)],
                $this->customer,
                [$voucher1, $voucher2, $voucher3, $voucher4],
                true,
            ),
        );
        $price = $priceList->getProductPrice($product->slug, 12, 12);

        $voucherPriceComponent = array_find(
            $price->appliedPriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent->type === PriceComponentType::VOUCHER,
        );
        self::assertNull($voucherPriceComponent);
        self::assertSame(950, $price->calculatedPrice);
    }
}
