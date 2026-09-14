<?php

declare(strict_types=1);

namespace Tests\Domain\Products\Integration;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Testing\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\CustomerProductDiscountFactory;
use Tests\Factories\ProductDiscountFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductIntroductionDiscountsFactory;
use Tests\Factories\ProductPeriodFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\TranslationKeyFactory;
use Tests\Factories\TranslationLanguageFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Pricing\DTO\PriceComponents\IntroductionPriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\PriceComponent;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\DTO\PriceRequest;
use Waterfront\Domain\Products\DTO\RegistrationPriceRequest;
use Waterfront\Domain\Products\Enums\ProductPriceType;
use Waterfront\Domain\Products\Models\ProductPriceAlternative;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Domain\Products\VolumeDiscountService;

#[CoversClass(PriceResolver::class)]
class PriceResolverTest extends IntegrationTestCase
{
    private PriceResolver $priceResolver;

    protected function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading(false);

        $this->priceResolver = self::resolve(PriceResolver::class);
    }

    /**
     * In this scenario, we test two ordinary products in a public list. No discounts should be taken
     * into account, except for a promotion price.
     */
    #[Test]
    public function publicPrices(): void
    {
        $productGroup = new ProductGroupFactory()->createOne(['name' => 'Hosting Products', 'slug' => 'hosting']);
        $product1 = new ProductFactory()->for($productGroup)->createOne([
            'name' => 'Test 1',
            'slug' => 'test-1',
            'weight' => 1,
        ]);
        new ProductPriceComponentFactory()
            ->for($product1)
            ->registration()
            ->createOne(['billing_period' => 12, 'contract_period' => 12, 'price' => 10]);
        new ProductPriceComponentFactory()
            ->for($product1)
            ->prolongation()
            ->createOne(['billing_period' => 12, 'contract_period' => 12, 'price' => 10]);
        $product2 = new ProductFactory()->for($productGroup)->createOne([
            'name' => 'Test 2',
            'slug' => 'test-2',
            'weight' => 2,
        ]);
        new ProductPriceComponentFactory()
            ->for($product2)
            ->registration()
            ->createOne(['billing_period' => 12, 'contract_period' => 12, 'price' => 12]);
        new ProductPriceComponentFactory()
            ->for($product2)
            ->prolongation()
            ->createOne(['billing_period' => 12, 'contract_period' => 12, 'price' => 12]);
        new ProductPriceComponentFactory()->for($product2)->createOne([
            'type' => PriceComponentType::PROMOTION,
            'contract_period' => 12,
            'billing_period' => 12,
            'price' => 9,
        ]);

        $priceRequest = new PriceRequest(
            [
                new RegistrationPriceRequest($product1),
                new RegistrationPriceRequest($product2),
            ],
            null,
        );
        $priceList = $this->priceResolver->getPriceList($priceRequest);

        $registerProduct1 = $priceList->getPrice('test-1', 12, 12, ProductPriceType::REGISTRATION);
        self::assertSame(10, $registerProduct1->getNetPrice());
        self::assertSame(10, $registerProduct1->regularPrice);
        $registerProduct2 = $priceList->getPrice('test-2', 12, 12, ProductPriceType::REGISTRATION);
        self::assertSame(9, $registerProduct2->getNetPrice());
        self::assertSame(12, $registerProduct2->regularPrice);
        $prolongProduct2 = $priceList->getPrice('test-2', 12, 12, ProductPriceType::PROLONGATION);
        self::assertSame(12, $prolongProduct2->getNetPrice());
        self::assertSame(12, $prolongProduct2->regularPrice);
    }

    /**
     * In this scenario, there are two hosting products, one of which has 50% off on the registration price (promotion).
     * However, the customer that fetches the price list has a permanent 20% discount on all hosting products.
     */
    #[Test]
    public function groupDiscount(): void
    {
        $productGroup = new ProductGroupFactory()->createOne(['name' => 'Hosting Products', 'slug' => 'hosting']);
        $product1 = new ProductFactory()->for($productGroup)->createOne([
            'name' => 'Test 1',
            'slug' => 'test-1',
            'weight' => 1,
        ]);
        new ProductPriceComponentFactory()
            ->for($product1)
            ->registration()
            ->createOne(['billing_period' => 12, 'contract_period' => 12, 'price' => 100]);
        new ProductPriceComponentFactory()
            ->for($product1)
            ->prolongation()
            ->createOne(['billing_period' => 12, 'contract_period' => 12, 'price' => 100]);
        $product2 = new ProductFactory()->for($productGroup)->createOne([
            'name' => 'Test 2',
            'slug' => 'test-2',
            'weight' => 2,
        ]);
        new ProductPriceComponentFactory()
            ->for($product2)
            ->registration()
            ->createOne(['billing_period' => 12, 'contract_period' => 12, 'price' => 100]);
        new ProductPriceComponentFactory()
            ->for($product2)
            ->prolongation()
            ->createOne(['billing_period' => 12, 'contract_period' => 12, 'price' => 100]);
        new ProductPriceComponentFactory()->for($product2)->createOne([
            'type' => PriceComponentType::PROMOTION,
            'contract_period' => 12,
            'billing_period' => 12,
            'price' => 50,
        ]);

        $customer = new CustomerFactory()->createOne();
        $customer->productGroups()->attach($productGroup->id, ['discount' => 20]);

        $priceRequest = new PriceRequest(
            [
                new RegistrationPriceRequest($product1),
                new RegistrationPriceRequest($product2),
            ],
            $customer,
        );
        $priceList = $this->priceResolver->getPriceList($priceRequest);

        $registerProduct1 = $priceList->getPrice('test-1', 12, 12, ProductPriceType::REGISTRATION);
        self::assertSame(80, $registerProduct1->getNetPrice());
        self::assertSame(100, $registerProduct1->regularPrice);
        $registerProduct2 = $priceList->getPrice('test-2', 12, 12, ProductPriceType::REGISTRATION);
        self::assertSame(50, $registerProduct2->getNetPrice());
        self::assertSame(100, $registerProduct2->regularPrice);
        $prolongProduct2 = $priceList->getPrice('test-2', 12, 12, ProductPriceType::PROLONGATION);
        self::assertSame(80, $prolongProduct2->getNetPrice());
        self::assertSame(100, $prolongProduct2->regularPrice);
    }

    #[Test]
    public function oneTimeServicePriceWithAlternativeProductPrice(): void
    {
        $domainProductGroup = new ProductGroupFactory()->extension()->createOne();
        $nlDomainProduct = new ProductFactory()->for($domainProductGroup)->createOne();
        $comDomainProduct = new ProductFactory()->for($domainProductGroup)->createOne();
        $beDomainProduct = new ProductFactory()->for($domainProductGroup)->createOne();

        $oneTimeServiceProductGroup = new ProductGroupFactory()->oneTimeService()->createOne();
        $oneTimeServiceProduct = new ProductFactory()->for($oneTimeServiceProductGroup)->createOne();

        $oneTimeServicePrice = new ProductPriceComponentFactory()
            ->for($oneTimeServiceProduct)
            ->registration()
            ->createOne([
                'billing_period' => 1,
                'contract_period' => 1,
                'price' => 500,
            ]);

        $alternativePrice = new ProductPriceAlternative();
        $alternativePrice->product_id = $oneTimeServiceProduct->id;
        $alternativePrice->billing_period = $oneTimeServicePrice->billing_period;
        $alternativePrice->contract_period = $oneTimeServicePrice->contract_period;
        $alternativePrice->alternative_product_id = $nlDomainProduct->id;
        $alternativePrice->gross_price = 250;
        $alternativePrice->save();

        $alternativePrice = new ProductPriceAlternative();
        $alternativePrice->product_id = $oneTimeServiceProduct->id;
        $alternativePrice->billing_period = $oneTimeServicePrice->billing_period;
        $alternativePrice->contract_period = $oneTimeServicePrice->contract_period;
        $alternativePrice->alternative_product_id = $comDomainProduct->id;
        $alternativePrice->gross_price = 750;
        $alternativePrice->save();

        $priceRequest = new PriceRequest([new RegistrationPriceRequest($oneTimeServiceProduct)], null);
        $priceList = $this->priceResolver->getPriceList($priceRequest);
        $price = $priceList->getPrice(
            $oneTimeServiceProduct->slug,
            1,
            1,
            ProductPriceType::REGISTRATION,
        );

        self::assertSame(500, $price->regularPrice);
        self::assertSame(250, $price->getAlternativeGrossPrice($nlDomainProduct->id));
        self::assertSame(750, $price->getAlternativeGrossPrice($comDomainProduct->id));
        self::assertNull($price->getAlternativeGrossPrice($beDomainProduct->id));
    }

    #[test]
    public function customerWithStaffelWillReturnStaffelPrices(): void
    {
        $productDiscountService = self::resolve(VolumeDiscountService::class);
        $customer = new CustomerFactory()->createOne();
        $productGroup = new ProductGroupFactory()->extension()->createOne();
        $product = new ProductFactory()
            ->nlDomain()
            ->for($productGroup)
            ->createOne();
        new ProductPriceComponentFactory()
            ->registration()
            ->for($product)
            ->createOne([
                'billing_period' => 12,
                'contract_period' => 12,
                'price' => 150,
                'orderable' => true,
            ]);
        new ProductPriceComponentFactory()->for($product)->createOne([
            'type' => PriceComponentType::PROMOTION,
            'contract_period' => 12,
            'billing_period' => 12,
            'price' => 100,
        ]);
        $staffel = new ProductDiscountFactory()->for($product)->createOne();
        new CustomerProductDiscountFactory()
            ->for($customer)
            ->for($staffel)
            ->createOne();
        $staffelPrice = new ProductPriceComponentFactory()->for($product)->createOne([
            'price' => 200,
            'type' => PriceComponentType::REGISTRATION_STAFFEL,
        ]);
        $productDiscountService->attachPrice($staffel, $staffelPrice);

        $priceRequest = new PriceRequest([new RegistrationPriceRequest($product)], $customer);
        $response = $this->priceResolver->getPriceList($priceRequest);

        $responsePrice = $response->getPrice($product->slug, 12, 12, ProductPriceType::REGISTRATION);

        Assert::assertNull($responsePrice->discountPrice);
        Assert::assertSame($responsePrice->regularPrice, $staffelPrice->price);
    }

    #[test]
    public function publicPricesWhileHavingAStaffel(): void
    {
        $customer = new CustomerFactory()->createOne();

        $productGroupHosting = new ProductGroupFactory()->hosting()->createOne();
        $productGroupDomain = new ProductGroupFactory()->extension()->createOne();
        $hostingProduct1 = new ProductFactory()->for($productGroupHosting)->createOne(['slug' => 'hosting_brons']);
        $domainProduct1 = new ProductFactory()->for($productGroupDomain)->createOne(['slug' => 'extension_nl']);

        $staffel = new ProductDiscountFactory()->createOne(['name' => 'test', 'product_id' => $hostingProduct1]);
        $customer->productDiscounts()->attach($staffel->id);

        new ProductPriceComponentFactory()
            ->for($hostingProduct1)
            ->registration()
            ->createOne([
                'contract_period' => 12,
                'billing_period' => 12,
                'price' => 100,
            ]);

        $registrationStaffelPrice = new ProductPriceComponentFactory()->for($hostingProduct1)->createOne([
            'price' => 200,
            'type' => PriceComponentType::REGISTRATION_STAFFEL,
        ]);
        self::resolve(VolumeDiscountService::class)->attachPrice($staffel, $registrationStaffelPrice);

        new ProductPriceComponentFactory()
            ->for($domainProduct1)
            ->registration()
            ->createMany([
                ['contract_period' => 12, 'billing_period' => 12, 'price' => 100],
            ]);

        $priceRequest = new PriceRequest(
            [
                new RegistrationPriceRequest($hostingProduct1),
                new RegistrationPriceRequest($domainProduct1),
            ],
            $customer,
        );
        $priceList = $this->priceResolver->getPriceList($priceRequest);

        $domainPrice = $priceList->getPrice($domainProduct1->slug, 12, 12, ProductPriceType::REGISTRATION);
        $introductionPriceComponent = array_find(
            $domainPrice->possiblePriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent instanceof IntroductionPriceComponent,
        );
        Assert::assertNull($introductionPriceComponent);
        Assert::assertSame(100, $domainPrice->regularPrice);
        Assert::assertNull($domainPrice->discountPrice);
    }

    /**
     * Mix the cases from test above.
     */
    #[test]
    public function combinedDiscountCases(): void
    {
        $productDiscountService = self::resolve(VolumeDiscountService::class);
        $customer = new CustomerFactory()->createOne();

        $productGroupHosting = new ProductGroupFactory()->hosting()->createOne();
        $productGroupDomain = new ProductGroupFactory()->extension()->createOne();
        $otherGroup = new ProductGroupFactory()->other()->createOne();
        $hostingProduct1 = new ProductFactory()->for($productGroupHosting)->createOne(['slug' => 'hosting_brons']);
        $hostingProduct2 = new ProductFactory()->for($productGroupHosting)->createOne(['slug' => 'hosting_groot']);
        $hostingProduct3 = new ProductFactory()->for($productGroupHosting)->createOne(['slug' => 'hosting_silver']);
        $domainProduct1 = new ProductFactory()->for($productGroupDomain)->createOne(['slug' => 'extension_nl']);
        $otherProduct1 = new ProductFactory()->for($otherGroup)->createOne(['slug' => 'a-secret-addon']);

        $staffel = new ProductDiscountFactory()->for($hostingProduct2)->createOne();
        new CustomerProductDiscountFactory()
            ->for($customer)
            ->for($staffel)
            ->createOne();
        $staffelPrice = new ProductPriceComponentFactory()->for($hostingProduct1)->createOne([
            'price' => 200,
            'type' => PriceComponentType::REGISTRATION_STAFFEL,
        ]);
        $productDiscountService->attachPrice($staffel, $staffelPrice);

        $customer->productGroups()->attach($productGroupHosting->id, ['discount' => 20]);
        $customer->productGroups()->attach($otherGroup->id, ['discount' => 74.21]);

        new ProductPriceComponentFactory()
            ->for($hostingProduct1)
            ->registration()
            ->createOne(['contract_period' => 12, 'billing_period' => 12, 'price' => 100]);
        new ProductPriceComponentFactory()
            ->for($hostingProduct1)
            ->prolongation()
            ->createOne(['contract_period' => 12, 'billing_period' => 12, 'price' => 200]);
        new ProductPriceComponentFactory()
            ->for($hostingProduct2)
            ->registration()
            ->createOne(['contract_period' => 12, 'billing_period' => 12, 'price' => 100]);
        new ProductPriceComponentFactory()
            ->for($hostingProduct2)
            ->prolongation()
            ->createOne(['contract_period' => 12, 'billing_period' => 12, 'price' => 200]);
        new ProductPriceComponentFactory()
            ->for($hostingProduct3)
            ->registration()
            ->createOne(['contract_period' => 12, 'billing_period' => 12, 'price' => 100]);
        new ProductPriceComponentFactory()
            ->for($hostingProduct3)
            ->prolongation()
            ->createOne(['contract_period' => 12, 'billing_period' => 12, 'price' => 200]);
        new ProductPriceComponentFactory()
            ->for($domainProduct1)
            ->registration()
            ->createOne(['contract_period' => 12, 'billing_period' => 12, 'price' => 100]);
        new ProductPriceComponentFactory()
            ->for($domainProduct1)
            ->prolongation()
            ->createOne(['contract_period' => 12, 'billing_period' => 12, 'price' => 200]);
        new ProductPriceComponentFactory()
            ->for($otherProduct1)
            ->prolongation()
            ->createOne(['contract_period' => 1, 'billing_period' => 1, 'price' => 200000]);

        $priceRequest = new PriceRequest(
            [
                new RegistrationPriceRequest($hostingProduct1),
                new RegistrationPriceRequest($hostingProduct2),
                new RegistrationPriceRequest($hostingProduct3),
                new RegistrationPriceRequest($domainProduct1),
                new RegistrationPriceRequest($otherProduct1),
            ],
            $customer,
        );
        $priceList = $this->priceResolver->getPriceList($priceRequest);

        //case 1: Customer has productGroupDiscount and Product Volumn Discount the Volumn Discount will be applied.
        $hostingPrice1 = $priceList->getPrice($hostingProduct1->slug, 12, 12, ProductPriceType::REGISTRATION);
        Assert::assertSame(200, $hostingPrice1->getNetPrice());
        Assert::assertSame(200, $hostingPrice1->regularPrice);
        Assert::assertNull($hostingPrice1->discountPrice);

        //case 2: Customer only has Product Group Discount.
        $hostingPrice2 = $priceList->getPrice($hostingProduct2->slug, 12, 12, ProductPriceType::REGISTRATION);
        Assert::assertSame(80, $hostingPrice2->getNetPrice()); // 20% discount from regular price 100
        Assert::assertSame(100, $hostingPrice2->regularPrice);
        $introductionPriceComponent = array_find(
            $hostingPrice2->possiblePriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent instanceof IntroductionPriceComponent,
        );
        Assert::assertNull($introductionPriceComponent);

        //case 3: Check normal prices while Customer has Discounts on different products.
        $domainPrice = $priceList->getPrice($domainProduct1->slug, 12, 12, ProductPriceType::REGISTRATION);
        $introductionPriceComponent = array_find(
            $domainPrice->possiblePriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent instanceof IntroductionPriceComponent,
        );
        Assert::assertNull($introductionPriceComponent);
        Assert::assertSame(100, $domainPrice->regularPrice);
        Assert::assertNull($domainPrice->discountPrice);

        //case 4: Product group discount with a decimal
        $otherPrice = $priceList->getPrice($otherProduct1->slug, 1, 1, ProductPriceType::PROLONGATION);
        $introductionPriceComponent = array_find(
            $otherPrice->possiblePriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent instanceof IntroductionPriceComponent,
        );
        Assert::assertNull($introductionPriceComponent);
        Assert::assertSame(51580, $otherPrice->getNetPrice());
        Assert::assertSame(200000, $otherPrice->regularPrice);
        Assert::assertSame(51580, $otherPrice->discountPrice);
    }

    #[Test]
    public function multipleRequestsWithQuantitiesForTheSameProductReturnTheRightAmountOfPrices(): void
    {
        $customer = new CustomerFactory()->createOne();

        $productGroupDns = new ProductGroupFactory()->dns()->createOne();
        $productGroupDomain = new ProductGroupFactory()->extension()->createOne();

        $domainProductNl = new ProductFactory()->for($productGroupDomain)->createOne(['slug' => 'extension_nl']);
        $domainProductEu = new ProductFactory()->for($productGroupDomain)->createOne(['slug' => 'extension_eu']);
        $domainProductCom = new ProductFactory()->for($productGroupDomain)->createOne(['slug' => 'extension_com']);
        $freeDnsProduct = new ProductFactory()->for($productGroupDns)->createOne(['slug' => 'free-dns']);

        new ProductPriceComponentFactory()
            ->for($domainProductNl)
            ->registration()
            ->createOne(['contract_period' => 12, 'billing_period' => 12, 'price' => 100]);
        new ProductPriceComponentFactory()
            ->for($domainProductEu)
            ->registration()
            ->createOne(['contract_period' => 12, 'billing_period' => 12, 'price' => 100]);
        new ProductPriceComponentFactory()
            ->for($domainProductCom)
            ->registration()
            ->createOne(['contract_period' => 12, 'billing_period' => 12, 'price' => 100]);
        new ProductPriceComponentFactory()
            ->for($domainProductCom)
            ->registration()
            ->createOne(['contract_period' => 36, 'billing_period' => 36, 'price' => 100]);
        new ProductPriceComponentFactory()
            ->for($freeDnsProduct)
            ->registration()
            ->createOne(['contract_period' => 12, 'billing_period' => 12, 'price' => 100]);
        new ProductPriceComponentFactory()
            ->for($freeDnsProduct)
            ->registration()
            ->createOne(['contract_period' => 36, 'billing_period' => 36, 'price' => 100]);

        $priceRequest = new PriceRequest(
            [
                new RegistrationPriceRequest($domainProductNl, 2, 12, 12),
                new RegistrationPriceRequest($freeDnsProduct, 5, 12, 12),
                new RegistrationPriceRequest($domainProductEu, 2, 12, 12),
                new RegistrationPriceRequest($domainProductCom, 1, 12, 12),
                new RegistrationPriceRequest($domainProductCom, 1, 36, 36),
                new RegistrationPriceRequest($freeDnsProduct, 1, 36, 36),
            ],
            $customer,
        );
        $priceList = $this->priceResolver->getPriceList($priceRequest);
        $prices = $priceList->getPrices();

        $filter = static fn (Price $price, int $productId, int $billingPeriod, int $contractPeriod) => (
            $price->productId === $productId
            && $price->billingPeriod === $billingPeriod
            && $price->contractPeriod === $contractPeriod
            && $price->type === ProductPriceType::REGISTRATION
        );

        // The price list should contain:
        // 5x free-dns 12/12
        // 1x free-dns 36/36
        // 2x extension_nl 12/12
        // 1x extension_com 12/12
        // 1x extension_com 36/36
        // 2x extension_eu 12/12

        self::assertCount(5, array_filter($prices, fn (Price $price) => $filter($price, $freeDnsProduct->id, 12, 12)));
        self::assertCount(1, array_filter($prices, fn (Price $price) => $filter($price, $freeDnsProduct->id, 36, 36)));
        self::assertCount(2, array_filter($prices, fn (Price $price) => $filter($price, $domainProductNl->id, 12, 12)));
        self::assertCount(1, array_filter($prices, fn (Price $price) => $filter(
            $price,
            $domainProductCom->id,
            12,
            12,
        )));
        self::assertCount(1, array_filter($prices, fn (Price $price) => $filter(
            $price,
            $domainProductCom->id,
            36,
            36,
        )));
        self::assertCount(2, array_filter($prices, fn (Price $price) => $filter($price, $domainProductEu->id, 12, 12)));
    }

    #[Test]
    public function multipleRequestsWithQuantitiesForTheSameProductReturnTheRightPrices(): void
    {
        $customer = new CustomerFactory()->createOne();

        $productGroupDomain = new ProductGroupFactory()->extension()->createOne();
        $domainProductNl = new ProductFactory()->for($productGroupDomain)->createOne(['slug' => 'extension_nl']);

        new ProductPriceComponentFactory()
            ->for($domainProductNl)
            ->registration()
            ->createOne(['contract_period' => 12, 'billing_period' => 12, 'price' => 100]);
        new ProductPriceComponentFactory()
            ->for($domainProductNl)
            ->introduction()
            ->createOne(['contract_period' => 12, 'billing_period' => 12, 'price' => 50]);

        new ProductIntroductionDiscountsFactory()->createOne([
            'product_id' => $domainProductNl->id,
            'max_uses_per_customer' => 100,
            'contract_period' => 12,
        ]);

        $dutch = new TranslationLanguageFactory()->createOne(['locale' => 'nl']);
        $priceExplanation = new TranslationKeyFactory()
            ->withTranslatedString($dutch, 'test vertaling')
            ->createOne(['key' => 'test.price.explanation']);

        new ProductPeriodFactory()->for($domainProductNl)->createOne([
            'contract_period' => 12,
            'billing_period' => 12,
            'translation_key_id' => $priceExplanation->id,
        ]);

        $priceRequest = new PriceRequest(
            [new RegistrationPriceRequest($domainProductNl, 2, 12, 12)],
            $customer,
            requestingForOrder: true,
        );
        $priceList = $this->priceResolver->getPriceList($priceRequest);
        $prices = $priceList->getPrices();

        $registrationPrices = array_filter(
            $prices,
            fn (Price $price) => $price->type === ProductPriceType::REGISTRATION,
        );
        self::assertCount(2, $registrationPrices);
        $firstPrice = array_shift($registrationPrices);
        $secondPrice = array_shift($registrationPrices);

        // Somehow PHPStan understands that $firstPrice is not null but not the second one
        self::assertNotNull($secondPrice);

        // Checking if the right values are copied on duplicating the price per quantity. We don't need to check all properties here.
        self::assertSame($firstPrice->regularPrice, $secondPrice->regularPrice);
        self::assertSame($firstPrice->actionPeriod, $secondPrice->actionPeriod);
        self::assertSame($firstPrice->actionPeriodPrice, $secondPrice->actionPeriodPrice);
        self::assertSame($firstPrice->calculatedPrice, $secondPrice->calculatedPrice);
        self::assertSame($firstPrice->discountPrice, $secondPrice->discountPrice);
        self::assertSame($firstPrice->priceExplanation?->id, $secondPrice->priceExplanation?->id);
    }
}
