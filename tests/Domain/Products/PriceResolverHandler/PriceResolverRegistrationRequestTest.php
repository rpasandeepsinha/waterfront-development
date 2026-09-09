<?php

declare(strict_types=1);

namespace Tests\Domain\Products\PriceResolverHandler;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\CustomerProductDiscountFactory;
use Tests\Factories\ProductDiscountFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductIntroductionDiscountsFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Pricing\DTO\PriceComponents\IntroductionPriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\PriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\ProductGroupPriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\ProlongationPriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\PromotionPriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\ProRatePriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\RegistrationPriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\RegistrationStaffelPriceComponent;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Products\DTO\PriceRequest;
use Waterfront\Domain\Products\DTO\RegistrationPriceRequest;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Domain\Products\VolumeDiscountService;

#[CoversClass(PriceResolver::class)]
class PriceResolverRegistrationRequestTest extends IntegrationTestCase
{
    private PriceResolver $priceResolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->priceResolver = self::resolve(PriceResolver::class);
    }

    #[Test]
    public function registrationPriceIsCreatedWhenOnlyProlongationExists(): void
    {
        // Sometimes a prolongation price exists, without there being a registration price.
        // If this is the case the price resolver creates a registration price by copying the prolongation price.
        $product = new ProductFactory()->for(new ProductGroupFactory())->createOne();
        $priceModel = new ProductPriceComponentFactory()->for($product)->prolongation()->createOne([
            'price' => 123,
        ]);

        $priceRequest = new PriceRequest([new RegistrationPriceRequest($product)], null);
        $priceList = $this->priceResolver->getPriceList($priceRequest);
        $priceDto = $priceList->getProductPrice($product->slug, $priceModel->contract_period, $priceModel->billing_period);

        self::assertCount(2, $priceDto->possiblePriceComponents);
        self::assertInstanceOf(RegistrationPriceComponent::class, $priceDto->possiblePriceComponents[0]);
        self::assertSame(123, $priceDto->possiblePriceComponents[0]->price);
        self::assertInstanceOf(ProlongationPriceComponent::class, $priceDto->possiblePriceComponents[1]);
        self::assertSame(123, $priceDto->possiblePriceComponents[1]->newPrice);

        self::assertCount(1, $priceDto->appliedPriceComponents);
        self::assertInstanceOf(RegistrationPriceComponent::class, $priceDto->appliedPriceComponents[0]);
        self::assertSame(123, $priceDto->appliedPriceComponents[0]->price);
        self::assertSame(1, $priceDto->appliedPriceComponents[0]->appliedOrder);
        self::assertSame(123, $priceDto->calculatedPrice);
    }

    #[Test]
    public function registrationPriceIsUsedWhenNoOtherPriceComponents(): void
    {
        $product = new ProductFactory()->for(new ProductGroupFactory())->createOne();
        $priceModel = new ProductPriceComponentFactory()->for($product)->registration()->createOne([
            'price' => 123,
        ]);

        $priceRequest = new PriceRequest([new RegistrationPriceRequest($product)], null);
        $priceList = $this->priceResolver->getPriceList($priceRequest);
        $priceDto = $priceList->getProductPrice($product->slug, $priceModel->contract_period, $priceModel->billing_period);

        self::assertCount(1, $priceDto->appliedPriceComponents);
        self::assertInstanceOf(RegistrationPriceComponent::class, $priceDto->appliedPriceComponents[0]);
        self::assertSame(123, $priceDto->appliedPriceComponents[0]->price);
        self::assertSame(1, $priceDto->appliedPriceComponents[0]->appliedOrder);
        self::assertSame(123, $priceDto->calculatedPrice);
    }

    #[Test]
    public function registrationTakesPrecedenceOverProlongation(): void
    {
        $product = new ProductFactory()->for(new ProductGroupFactory()->extension())->createOne();
        $priceModel = new ProductPriceComponentFactory()->for($product)->registration()->createOne([
            'price' => 123,
        ]);
        new ProductPriceComponentFactory()->for($product)->prolongation()->createOne([
            'price' => 456,
        ]);

        $priceRequest = new PriceRequest([new RegistrationPriceRequest($product)], null);
        $priceList = $this->priceResolver->getPriceList($priceRequest);
        $priceDto = $priceList->getProductPrice($product->slug, $priceModel->contract_period, $priceModel->billing_period);

        self::assertCount(1, $priceDto->appliedPriceComponents);
        self::assertInstanceOf(RegistrationPriceComponent::class, $priceDto->appliedPriceComponents[0]);
        self::assertSame(123, $priceDto->appliedPriceComponents[0]->price);
        self::assertSame(1, $priceDto->appliedPriceComponents[0]->appliedOrder);
        self::assertSame(123, $priceDto->calculatedPrice);
    }

    #[Test]
    public function productGroupIsAppliedOverRegistration(): void
    {
        $customer = new CustomerFactory()->createOne();
        $productGroup = new ProductGroupFactory()->createOne();
        $customer->productGroups()->attach($productGroup->id, ['discount' => 64]);
        $product = new ProductFactory()->for($productGroup)->createOne();
        $priceModel = new ProductPriceComponentFactory()->for($product)->registration()->createOne([
            'price' => 123,
        ]);

        $priceRequest = new PriceRequest([new RegistrationPriceRequest($product)], $customer);
        $priceList = $this->priceResolver->getPriceList($priceRequest);
        $priceDto = $priceList->getProductPrice($product->slug, $priceModel->contract_period, $priceModel->billing_period);

        self::assertCount(2, $priceDto->appliedPriceComponents);
        self::assertInstanceOf(RegistrationPriceComponent::class, $priceDto->appliedPriceComponents[0]);
        self::assertSame(123, $priceDto->appliedPriceComponents[0]->price);
        self::assertSame(1, $priceDto->appliedPriceComponents[0]->appliedOrder);
        self::assertInstanceOf(ProductGroupPriceComponent::class, $priceDto->appliedPriceComponents[1]);
        self::assertSame(44, $priceDto->appliedPriceComponents[1]->newPrice);
        self::assertSame(2, $priceDto->appliedPriceComponents[1]->appliedOrder);
        self::assertSame(44, $priceDto->calculatedPrice);
    }

    #[Test]
    public function promotionTakesPrecedenceOverProductGroup(): void
    {
        $customer = new CustomerFactory()->createOne();
        $productGroup = new ProductGroupFactory()->createOne();
        $customer->productGroups()->attach($productGroup->id, ['discount' => 32]);
        $product = new ProductFactory()->for($productGroup)->createOne();
        $priceModel = new ProductPriceComponentFactory()->for($product)->registration()->createOne([
            'price' => 123,
        ]);
        new ProductPriceComponentFactory()->for($product)->createOne([
            'type' => PriceComponentType::PROMOTION,
            'price' => 789,
            'contract_period' => 12,
            'billing_period' => 12,
        ]);

        $priceRequest = new PriceRequest([new RegistrationPriceRequest($product)], null);
        $priceList = $this->priceResolver->getPriceList($priceRequest);
        $priceDto = $priceList->getProductPrice($product->slug, $priceModel->contract_period, $priceModel->billing_period);

        self::assertCount(2, $priceDto->appliedPriceComponents);
        $registrationComponent = $priceDto->appliedPriceComponents[0];
        $promotionComponent = $priceDto->appliedPriceComponents[1];
        self::assertInstanceOf(RegistrationPriceComponent::class, $registrationComponent);
        self::assertInstanceOf(PromotionPriceComponent::class, $promotionComponent);
        self::assertSame(123, $registrationComponent->price);
        self::assertSame(1, $registrationComponent->appliedOrder);
        self::assertSame(789, $promotionComponent->newPrice);
        self::assertSame(2, $promotionComponent->appliedOrder);
        self::assertSame(789, $priceDto->calculatedPrice);
    }

    #[Test]
    public function introductionTakesPrecedenceOverPromotionWhenRequestingPriceForOrdering(): void
    {
        new CustomerFactory()->createOne();
        $product = new ProductFactory()->for(new ProductGroupFactory())->createOne();
        new ProductPriceComponentFactory()->for($product)->registration()->createOne(['price' => 123]);
        new ProductPriceComponentFactory()->for($product)->introduction()->createOne(['price' => 80]);
        new ProductIntroductionDiscountsFactory()->for($product)->createOne([
            'max_uses_per_customer' => 1,
            'contract_period' => 12,
            'first_months_discount_period' => 3,
        ]);
        new ProductPriceComponentFactory()->for($product)->createOne([
            'type' => PriceComponentType::PROMOTION,
            'price' => 789,
            'contract_period' => 12,
            'billing_period' => 12,
        ]);

        $priceRequest = new PriceRequest([new RegistrationPriceRequest($product)], null, requestingForOrder: true);
        $priceList = $this->priceResolver->getPriceList($priceRequest);
        $priceDto = $priceList->getProductPrice($product->slug, 12, 12);

        self::assertCount(2, $priceDto->appliedPriceComponents);
        $registrationComponent = $priceDto->appliedPriceComponents[0];
        $introductionComponent = $priceDto->appliedPriceComponents[1];
        self::assertInstanceOf(RegistrationPriceComponent::class, $registrationComponent);
        self::assertInstanceOf(IntroductionPriceComponent::class, $introductionComponent);
        self::assertSame(123, $registrationComponent->price);
        self::assertSame(1, $registrationComponent->appliedOrder);
        self::assertSame(80, $introductionComponent->newPrice);
        self::assertSame(2, $introductionComponent->appliedOrder);
        self::assertSame(3, $introductionComponent->firstMonthsDiscountPeriod);
        self::assertSame(80, $priceDto->calculatedPrice);
    }

    #[Test]
    public function staffelTakesPrecendenceOverProductGroup(): void
    {
        $productDiscountService = self::resolve(VolumeDiscountService::class);
        $customer = new CustomerFactory()->createOne();
        $productGroup = new ProductGroupFactory()->createOne();
        $customer->productGroups()->attach($productGroup->id, ['discount' => 70]);
        $product = new ProductFactory()->for($productGroup)->createOne();
        $priceModel = new ProductPriceComponentFactory()->for($product)->registration()->createOne([
            'price' => 123,
        ]);
        $staffel = new ProductDiscountFactory()->for($product)->createOne();
        new CustomerProductDiscountFactory()->for($customer)->for($staffel)->createOne();
        $registrationStaffelPrice = new ProductPriceComponentFactory()->for($product)->createOne([
            'price' => 124,
            'type' => PriceComponentType::REGISTRATION_STAFFEL,
        ]);
        $productDiscountService->attachPrice($staffel, $registrationStaffelPrice);

        $priceRequest = new PriceRequest([new RegistrationPriceRequest($product)], $customer);
        $priceList = $this->priceResolver->getPriceList($priceRequest);
        $priceDto = $priceList->getProductPrice($product->slug, $priceModel->contract_period, $priceModel->billing_period);

        self::assertCount(2, $priceDto->appliedPriceComponents);
        $registrationComponent = $priceDto->appliedPriceComponents[0];
        $staffelComponent = $priceDto->appliedPriceComponents[1];
        self::assertInstanceOf(RegistrationPriceComponent::class, $registrationComponent);
        self::assertInstanceOf(RegistrationStaffelPriceComponent::class, $staffelComponent);
        self::assertSame(123, $registrationComponent->price);
        self::assertSame(1, $registrationComponent->appliedOrder);
        self::assertSame(124, $staffelComponent->newPrice);
        self::assertSame(2, $staffelComponent->appliedOrder);
        self::assertSame(124, $priceDto->calculatedPrice);
    }

    #[Test]
    public function proRateIsStackedOnTopOfRegistration(): void
    {
        $this->travelTo(CarbonImmutable::create(2024, 9));

        $customer = new CustomerFactory()->createOne();
        $product = new ProductFactory()->for(new ProductGroupFactory()->microsoft365())->createOne();
        $priceModel = new ProductPriceComponentFactory()->for($product)->registration()->createOne([
            'price' => 902,
            'billing_period' => 12,
            'contract_period' => 12,
        ]);
        new SubscriptionFactory()->for($customer)->for($product)->createOne([
            'contract_period' => 12,
            'billing_period' => 12,
            'start_date' => CarbonImmutable::create(2024, 9),
        ]);

        $this->travel(10)->months();

        $priceRequest = new PriceRequest([new RegistrationPriceRequest($product)], $customer);
        $priceList = $this->priceResolver->getPriceList($priceRequest);
        $priceDto = $priceList->getProductPrice($product->slug, $priceModel->contract_period, $priceModel->billing_period);

        self::assertCount(2, $priceDto->appliedPriceComponents);
        $registrationComponent = $priceDto->appliedPriceComponents[0];
        $proRateComponent = $priceDto->appliedPriceComponents[1];
        self::assertInstanceOf(RegistrationPriceComponent::class, $registrationComponent);
        self::assertInstanceOf(ProRatePriceComponent::class, $proRateComponent);
        self::assertSame(902, $registrationComponent->price);
        self::assertSame(1, $registrationComponent->appliedOrder);
        self::assertSame(153, $proRateComponent->newPrice);
        self::assertSame(2, $proRateComponent->appliedOrder);
        self::assertSame(153, $priceDto->calculatedPrice);
    }

    #[Test]
    public function proRateIsStackedOnTopOfPromotion(): void
    {
        $this->travelTo(CarbonImmutable::create(2025, 5));

        $customer = new CustomerFactory()->createOne();
        $product = new ProductFactory()->for(new ProductGroupFactory()->microsoft365())->createOne();
        $priceModel = new ProductPriceComponentFactory()->for($product)->registration()->createOne([
            'price' => 123,
            'billing_period' => 12,
            'contract_period' => 12,
        ]);
        new ProductPriceComponentFactory()->for($product)->createOne([
            'type' => PriceComponentType::PROMOTION,
            'price' => 789,
            'contract_period' => 12,
            'billing_period' => 12,
        ]);

        new SubscriptionFactory()->for($customer)->for($product)->createOne([
            'contract_period' => 12,
            'billing_period' => 12,
            'start_date' => CarbonImmutable::create(2025, 5),
        ]);

        $this->travel(6)->months();

        $priceRequest = new PriceRequest([new RegistrationPriceRequest($product)], $customer);
        $priceList = $this->priceResolver->getPriceList($priceRequest);
        $priceDto = $priceList->getProductPrice($product->slug, $priceModel->contract_period, $priceModel->billing_period);

        self::assertCount(3, $priceDto->appliedPriceComponents);
        $registrationComponent = $priceDto->appliedPriceComponents[0];
        $promotionComponent = $priceDto->appliedPriceComponents[1];
        $proRateComponent = $priceDto->appliedPriceComponents[2];
        self::assertInstanceOf(RegistrationPriceComponent::class, $registrationComponent);
        self::assertInstanceOf(PromotionPriceComponent::class, $promotionComponent);
        self::assertInstanceOf(ProRatePriceComponent::class, $proRateComponent);
        self::assertSame(123, $registrationComponent->price);
        self::assertSame(1, $registrationComponent->appliedOrder);
        self::assertSame(789, $promotionComponent->newPrice);
        self::assertSame(2, $promotionComponent->appliedOrder);
        self::assertSame(391, $proRateComponent->newPrice);
        self::assertSame(3, $proRateComponent->appliedOrder);
        self::assertSame(391, $priceDto->calculatedPrice);
    }

    #[Test]
    public function proRateIsStackedOnTopOfProductGroup(): void
    {
        $this->travelTo(CarbonImmutable::create(2023, 2));

        $customer = new CustomerFactory()->createOne();
        $productGroup = new ProductGroupFactory()->microsoft365()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();
        $customer->productGroups()->attach($productGroup->id, ['discount' => 17]);
        $priceModel = new ProductPriceComponentFactory()->for($product)->registration()->createOne([
            'price' => 9362,
            'billing_period' => 12,
            'contract_period' => 12,
        ]);
        new SubscriptionFactory()->for($customer)->for($product)->createOne([
            'contract_period' => 12,
            'billing_period' => 12,
            'start_date' => CarbonImmutable::create(2023, 2),
        ]);

        $this->travel(4)->months();

        $priceRequest = new PriceRequest([new RegistrationPriceRequest($product)], $customer);
        $priceList = $this->priceResolver->getPriceList($priceRequest);
        $priceDto = $priceList->getProductPrice($product->slug, $priceModel->contract_period, $priceModel->billing_period);

        $registrationPriceComponent = $priceDto->appliedPriceComponents[0];
        $productGroupPriceComponent = $priceDto->appliedPriceComponents[1];
        $proratePriceComponent = $priceDto->appliedPriceComponents[2];
        self::assertInstanceOf(RegistrationPriceComponent::class, $registrationPriceComponent);
        self::assertInstanceOf(ProductGroupPriceComponent::class, $productGroupPriceComponent);
        self::assertInstanceOf(ProRatePriceComponent::class, $proratePriceComponent);

        self::assertSame(9362, $registrationPriceComponent->price);
        self::assertSame(1, $registrationPriceComponent->appliedOrder);
        self::assertSame(7770, $productGroupPriceComponent->newPrice);
        self::assertSame(2, $productGroupPriceComponent->appliedOrder);
        self::assertSame(5215, $proratePriceComponent->newPrice);
        self::assertSame(3, $proratePriceComponent->appliedOrder);
        self::assertSame(5215, $priceDto->calculatedPrice);
    }

    #[Test]
    public function proRateIsStackedOnTopOfStaffel(): void
    {
        $this->travelTo(CarbonImmutable::create(2020, 3));

        $productDiscountService = self::resolve(VolumeDiscountService::class);
        $customer = new CustomerFactory()->createOne();
        $productGroup = new ProductGroupFactory()->microsoft365()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();
        $priceModel = new ProductPriceComponentFactory()->for($product)->registration()->createOne([
            'price' => 123,
            'billing_period' => 12,
            'contract_period' => 12,
        ]);
        new SubscriptionFactory()->for($customer)->for($product)->createOne([
            'contract_period' => 12,
            'billing_period' => 12,
            'start_date' => CarbonImmutable::create(2020, 3),
        ]);
        $staffel = new ProductDiscountFactory()->for($product)->createOne();
        new CustomerProductDiscountFactory()->for($customer)->for($staffel)->createOne();
        $registrationStaffelPrice = new ProductPriceComponentFactory()->for($product)->createOne([
            'price' => 3434,
            'type' => PriceComponentType::REGISTRATION_STAFFEL,
        ]);
        $productDiscountService->attachPrice($staffel, $registrationStaffelPrice);

        $this->travel(1)->months();

        $priceRequest = new PriceRequest([new RegistrationPriceRequest($product)], $customer);
        $priceList = $this->priceResolver->getPriceList($priceRequest);
        $priceDto = $priceList->getProductPrice($product->slug, $priceModel->contract_period, $priceModel->billing_period);

        self::assertCount(3, $priceDto->appliedPriceComponents);
        $registrationComponent = $priceDto->appliedPriceComponents[0];
        $staffelComponent = $priceDto->appliedPriceComponents[1];
        $proRateComponent = $priceDto->appliedPriceComponents[2];
        self::assertInstanceOf(RegistrationPriceComponent::class, $registrationComponent);
        self::assertInstanceOf(RegistrationStaffelPriceComponent::class, $staffelComponent);
        self::assertInstanceOf(ProRatePriceComponent::class, $proRateComponent);
        self::assertSame(123, $registrationComponent->price);
        self::assertSame(1, $registrationComponent->appliedOrder);
        self::assertSame(3434, $staffelComponent->newPrice);
        self::assertSame(2, $staffelComponent->appliedOrder);
        self::assertSame(3142, $proRateComponent->newPrice);
        self::assertSame(3, $proRateComponent->appliedOrder);
        self::assertSame(3142, $priceDto->calculatedPrice);
    }

    #[Test]
    public function staffelTakesPrecedenceOverEverything(): void
    {
        // Spawn every possible pricing structure we have, in order to see if staffel is taken.
        $productDiscountService = self::resolve(VolumeDiscountService::class);
        $customer = new CustomerFactory()->createOne();
        $productGroup = new ProductGroupFactory()->extension()->createOne();
        $customer->productGroups()->attach($productGroup->id, ['discount' => 64]);
        $product = new ProductFactory()->for($productGroup)->createOne();
        new ProductPriceComponentFactory()->for($product)->createMany([
            ['price' => 456, 'type' => PriceComponentType::PROMOTION],
            ['price' => 111, 'type' => PriceComponentType::CUSTOM_ONE_OFF],
            ['price' => 123, 'type' => PriceComponentType::REGISTRATION],
            ['price' => 987, 'type' => PriceComponentType::PROLONGATION],
            ['price' => 789, 'type' => PriceComponentType::INTRODUCTION],
        ]);
        $staffel = new ProductDiscountFactory()->for($product)->createOne();
        new CustomerProductDiscountFactory()->for($customer)->for($staffel)->createOne();
        $registrationStaffelPrice = new ProductPriceComponentFactory()->for($product)->createOne([
            'price' => 124,
            'type' => PriceComponentType::REGISTRATION_STAFFEL,
        ]);
        $prolongationStaffelPrice = new ProductPriceComponentFactory()->for($product)->createOne([
            'price' => 3821,
            'type' => PriceComponentType::PROLONGATION_STAFFEL,
        ]);
        $productDiscountService->attachPrice($staffel, $registrationStaffelPrice);
        $productDiscountService->attachPrice($staffel, $prolongationStaffelPrice);
        new ProductIntroductionDiscountsFactory()->for($product)->createOne(['max_uses_per_customer' => 2, 'contract_period' => 12]);

        $priceRequest = new PriceRequest([new RegistrationPriceRequest($product)], $customer);
        $priceList = $this->priceResolver->getPriceList($priceRequest);
        $priceDto = $priceList->getProductPrice($product->slug, 12, 12);

        // Make sure that there are as many possible priceComponents as priceComponent enum variants. In other words, make sure
        // that every possible pricing structure is set up for the product. We need to be certain that when a new
        // pricing structure is introduced we are forced to think about how that interacts with the registration scenario.
        self::assertCount(count(PriceComponentType::cases()) - 4, array_unique($priceDto->possiblePriceComponents, SORT_REGULAR));
        // Pro-rate is an exception, since that can't be combined with a transfer price, which is only available for domain products.
        self::assertNotContains(PriceComponentType::PRO_RATE, array_column($priceDto->possiblePriceComponents, 'type'));
        // Custom one-off is an exception, since that is a price used to override a subscription price.
        self::assertNotContains(PriceComponentType::CUSTOM_ONE_OFF, array_column($priceDto->possiblePriceComponents, 'type'));
        // Custom indefinite is an exception, since that is a price used to override a subscription price.
        self::assertNotContains(PriceComponentType::CUSTOM_INDEFINITE, array_column($priceDto->possiblePriceComponents, 'type'));
        // Voucher is an exception, since that is a price that will only appear in appliedPriceComponents.
        self::assertNotContains(PriceComponentType::VOUCHER, array_column($priceDto->possiblePriceComponents, 'type'));

        self::assertCount(2, $priceDto->appliedPriceComponents);
        $registrationComponent = $priceDto->appliedPriceComponents[0];
        $staffelComponent = $priceDto->appliedPriceComponents[1];
        self::assertInstanceOf(RegistrationPriceComponent::class, $registrationComponent);
        self::assertInstanceOf(RegistrationStaffelPriceComponent::class, $staffelComponent);
        self::assertSame(123, $registrationComponent->price);
        self::assertSame(1, $registrationComponent->appliedOrder);
        self::assertSame(124, $staffelComponent->newPrice);
        self::assertSame(2, $staffelComponent->appliedOrder);
        self::assertSame(124, $priceDto->calculatedPrice);
    }

    #[Test]
    public function usesPricesTableWhenNoProductPriceExists(): void
    {
        $product = new ProductFactory()->nlDomain()->createOne();
        new ProductPriceComponentFactory()->for($product)->registration()->createOne();

        $priceResolver = self::resolve(PriceResolver::class);
        $priceList = $priceResolver->getPriceList(new PriceRequest([new RegistrationPriceRequest($product)], null));

        self::assertCount(1, $priceList);
        $priceEntry = $priceList->getProductPrice($product->slug, 12, 12);
        self::assertCount(1, $priceEntry->appliedPriceComponents);
        self::assertSame([PriceComponentType::REGISTRATION], array_map(fn (PriceComponent $priceComponent) => $priceComponent->type, $priceEntry->appliedPriceComponents));
    }
}
