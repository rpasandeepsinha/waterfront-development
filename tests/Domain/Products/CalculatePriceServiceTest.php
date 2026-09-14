<?php

declare(strict_types=1);

namespace Tests\Domain\Products;

use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPeriodFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\TranslationKeyFactory;
use Tests\Factories\TranslationLanguageFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Products\CalculatePriceService;
use Waterfront\Domain\Products\DTO\ProductWithCalculatedPrice;
use Waterfront\Domain\Products\DTO\ProductWithPeriodsAndPrice;
use Waterfront\Domain\Products\Enums\ProductPriceType;
use Waterfront\Domain\Products\Enums\ProductSpecName;

#[CoversClass(CalculatePriceService::class)]
class CalculatePriceServiceTest extends IntegrationTestCase
{
    #[Test]
    public function calculatePricesCalculatesCorrectPriceInclVatForDefaultVatRate(): void
    {
        $customer = new CustomerFactory()->createOne();

        $nlProduct = new ProductFactory()->nlDomain()->createOne();
        $registrationPrice = new ProductPriceComponentFactory()
            ->for($nlProduct)
            ->registration()
            ->createOne(['price' => 2000]);

        $simpleProductDto = new ProductWithPeriodsAndPrice(
            uuid: Uuid::uuid4(),
            parentItemUuid: null,
            slug: $nlProduct->slug,
            productId: $nlProduct->id,
            billingPeriod: $registrationPrice->billing_period,
            contractPeriod: $registrationPrice->contract_period,
            priceType: ProductPriceType::REGISTRATION,
            price: null,
            parentSubscription: null,
            subscription: null,
            experimentSlug: null,
        );

        $result = self::resolve(CalculatePriceService::class)
            ->calculatePrices($customer, new Collection([$simpleProductDto]), []);

        $calculatedDomainItem = $result->items->first();

        self::assertInstanceOf(ProductWithCalculatedPrice::class, $calculatedDomainItem);
        self::assertSame(2420, $calculatedDomainItem->regularPrice->priceInclVat);
        self::assertSame(2000, $calculatedDomainItem->regularPrice->priceExclVat);
    }

    #[Test]
    public function calculatePricesCalculatesCorrectPriceInclVatForSpecificVatRate(): void
    {
        $customer = new CustomerFactory()->createOne(['vat_rate' => 15.00]);

        $nlProduct = new ProductFactory()->nlDomain()->createOne();
        $registrationPrice = new ProductPriceComponentFactory()
            ->for($nlProduct)
            ->registration()
            ->createOne(['price' => 2000]);

        $simpleProductDto = new ProductWithPeriodsAndPrice(
            uuid: Uuid::uuid4(),
            parentItemUuid: null,
            slug: $nlProduct->slug,
            productId: $nlProduct->id,
            billingPeriod: $registrationPrice->billing_period,
            contractPeriod: $registrationPrice->contract_period,
            priceType: ProductPriceType::REGISTRATION,
            price: null,
            parentSubscription: null,
            subscription: null,
            experimentSlug: null,
        );

        $result = self::resolve(CalculatePriceService::class)
            ->calculatePrices($customer, new Collection([$simpleProductDto]), []);

        $calculatedDomainItem = $result->items->first();

        self::assertInstanceOf(ProductWithCalculatedPrice::class, $calculatedDomainItem);
        self::assertSame(2300, $calculatedDomainItem->regularPrice->priceInclVat);
        self::assertSame(2000, $calculatedDomainItem->regularPrice->priceExclVat);
    }

    #[Test]
    public function actionPeriodIsAppliedWhenAvailable(): void
    {
        $customer = new CustomerFactory()->createOne(['vat_rate' => 15.00]);

        $nlProduct = new ProductFactory()->nlDomain()->createOne();
        $productPeriod = new ProductPeriodFactory()->for($nlProduct)->createOne([
            'action_period' => 6,
            'action_period_price' => 99,
        ]);
        new ProductPriceComponentFactory()->for($nlProduct)->createMany([
            [
                'type' => PriceComponentType::REGISTRATION,
                'contract_period' => 12,
                'billing_period' => 12,
                'price' => 25175,
            ],
            [
                'type' => PriceComponentType::PROMOTION,
                'contract_period' => 12,
                'billing_period' => 12,
                'price' => 21395,
            ],
        ]);

        $simpleProductDto = new ProductWithPeriodsAndPrice(
            uuid: Uuid::uuid4(),
            parentItemUuid: null,
            slug: $nlProduct->slug,
            productId: $nlProduct->id,
            billingPeriod: $productPeriod->billing_period,
            contractPeriod: $productPeriod->contract_period,
            priceType: ProductPriceType::REGISTRATION,
            price: null,
            parentSubscription: null,
            subscription: null,
            experimentSlug: null,
        );

        $result = self::resolve(CalculatePriceService::class)
            ->calculatePrices($customer, new Collection([$simpleProductDto]), []);

        $calculatedDomainItem = $result->items->first();

        self::assertInstanceOf(ProductWithCalculatedPrice::class, $calculatedDomainItem);

        self::assertSame(6, $calculatedDomainItem->appliedPrice->actionPeriod);
        self::assertSame(99, $calculatedDomainItem->appliedPrice->actionPeriodPrice);
        self::assertSame(21395, $calculatedDomainItem->appliedPrice->priceExclVat);
        self::assertSame(25175, $calculatedDomainItem->regularPrice->priceExclVat);
    }

    #[Test]
    public function actionPeriodIsNullWhenVoucherIsUsed(): void
    {
        $customer = new CustomerFactory()->createOne();

        $nlProduct = new ProductFactory()->nlDomain()->createOne();
        $registrationPrice = new ProductPriceComponentFactory()
            ->for($nlProduct)
            ->registration()
            ->createOne(['price' => 2000]);

        $simpleProductDto = new ProductWithPeriodsAndPrice(
            uuid: Uuid::uuid4(),
            parentItemUuid: null,
            slug: $nlProduct->slug,
            productId: $nlProduct->id,
            billingPeriod: $registrationPrice->billing_period,
            contractPeriod: $registrationPrice->contract_period,
            priceType: ProductPriceType::REGISTRATION,
            price: null,
            parentSubscription: null,
            subscription: null,
            experimentSlug: null,
        );

        $result = self::resolve(CalculatePriceService::class)
            ->calculatePrices($customer, new Collection([$simpleProductDto]), []);

        $calculatedDomainItem = $result->items->first();

        self::assertInstanceOf(ProductWithCalculatedPrice::class, $calculatedDomainItem);
        self::assertNull($calculatedDomainItem->appliedPrice->actionPeriodPrice);
        self::assertNull($calculatedDomainItem->appliedPrice->actionPeriod);
    }

    #[Test]
    public function priceExplanationIsTranslated(): void
    {
        $customer = new CustomerFactory()->createOne(['vat_rate' => 15.00]);

        $this->actingAsCustomer($customer);

        $language = new TranslationLanguageFactory()->createOne(['locale' => 'nl']);
        $priceExplanationTranslation = new TranslationKeyFactory()
            ->withTranslatedString($language, 'test vertaling')
            ->createOne(['key' => 'test.price.explanation']);

        $nlProduct = new ProductFactory()->nlDomain()->createOne();
        new ProductPeriodFactory()
            ->for($nlProduct)
            ->for($priceExplanationTranslation, 'priceExplanation')
            ->createOne();
        new ProductPriceComponentFactory()->for($nlProduct)->createOne([
            'type' => PriceComponentType::REGISTRATION,
            'price' => 25175,
        ]);
        new ProductPriceComponentFactory()->for($nlProduct)->createOne([
            'type' => PriceComponentType::PROMOTION,
            'price' => 21395,
        ]);

        $simpleProductDto = new ProductWithPeriodsAndPrice(
            uuid: Uuid::uuid4(),
            parentItemUuid: null,
            slug: $nlProduct->slug,
            productId: $nlProduct->id,
            billingPeriod: 12,
            contractPeriod: 12,
            priceType: ProductPriceType::REGISTRATION,
            price: null,
            parentSubscription: null,
            subscription: null,
            experimentSlug: null,
        );

        $result = self::resolve(CalculatePriceService::class)
            ->calculatePrices($customer, new Collection([$simpleProductDto]), []);

        $calculatedDomainItem = $result->items->first();

        self::assertInstanceOf(ProductWithCalculatedPrice::class, $calculatedDomainItem);
        self::assertSame('test vertaling', $calculatedDomainItem->appliedPrice->priceExplanation);
    }

    #[Test]
    public function priceExplanationIsNullWhenVoucherIsApplied(): void
    {
        $customer = new CustomerFactory()->createOne();

        $nlProduct = new ProductFactory()->nlDomain()->createOne();
        $registrationPrice = new ProductPriceComponentFactory()
            ->for($nlProduct)
            ->registration()
            ->createOne(['price' => 2000]);

        $simpleProductDto = new ProductWithPeriodsAndPrice(
            uuid: Uuid::uuid4(),
            parentItemUuid: null,
            slug: $nlProduct->slug,
            productId: $nlProduct->id,
            billingPeriod: $registrationPrice->billing_period,
            contractPeriod: $registrationPrice->contract_period,
            priceType: ProductPriceType::REGISTRATION,
            price: null,
            parentSubscription: null,
            subscription: null,
            experimentSlug: null,
        );

        $result = self::resolve(CalculatePriceService::class)
            ->calculatePrices($customer, new Collection([$simpleProductDto]), []);

        $calculatedDomainItem = $result->items->first();

        self::assertInstanceOf(ProductWithCalculatedPrice::class, $calculatedDomainItem);
        self::assertNull($calculatedDomainItem->appliedPrice->priceExplanation);
    }

    #[Test]
    public function transferServiceIsFreeWhenOrderedWithHostingSubscriptionWithServicePlus(): void
    {
        $customer = new CustomerFactory()->createOne();

        $hostingProduct = new ProductFactory()->hostingBrons()->createOne();
        $hostingPrice = new ProductPriceComponentFactory()
            ->for($hostingProduct)
            ->registration()
            ->createOne(['price' => 2000]);

        new ProductSpecFactory()->createOne(
            [
                'name' => ProductSpecName::HAS_SERVICE_PLUS->value,
                'value' => '1',
                'product_id' => $hostingProduct->id,
            ],
        );

        $transferServiceProduct = new ProductFactory()->for(new ProductGroupFactory()->oneTimeService())->createOne([
            'name' => 'transfer_service',
            'slug' => 'transfer_service',
        ]);
        $transferServicePrice = new ProductPriceComponentFactory()
            ->for($transferServiceProduct)
            ->registration()
            ->createOne(['price' => 1000]);

        $hostingProductDto = new ProductWithPeriodsAndPrice(
            uuid: Uuid::uuid4(),
            parentItemUuid: null,
            slug: $hostingProduct->slug,
            productId: $hostingProduct->id,
            billingPeriod: $hostingPrice->billing_period,
            contractPeriod: $hostingPrice->contract_period,
            priceType: ProductPriceType::REGISTRATION,
            price: null,
            parentSubscription: null,
            subscription: null,
            experimentSlug: null,
        );

        $transferServiceProductDto = new ProductWithPeriodsAndPrice(
            uuid: Uuid::uuid4(),
            parentItemUuid: $hostingProductDto->uuid,
            slug: $transferServiceProduct->slug,
            productId: $transferServiceProduct->id,
            billingPeriod: $transferServicePrice->billing_period,
            contractPeriod: $transferServicePrice->contract_period,
            priceType: ProductPriceType::REGISTRATION,
            price: null,
            parentSubscription: null,
            subscription: null,
            experimentSlug: null,
        );

        $result = self::resolve(CalculatePriceService::class)
            ->calculatePrices($customer, new Collection([$hostingProductDto, $transferServiceProductDto]), []);

        $transferProductPrice = $result
            ->items
            ->filter(
                fn (ProductWithCalculatedPrice $dto) => $dto->productId === $transferServiceProduct->id,
            )
            ->first();

        self::assertSame(1000, $transferProductPrice?->regularPrice->priceExclVat);
        self::assertSame(0, $transferProductPrice->appliedPrice->priceExclVat);
    }
}
