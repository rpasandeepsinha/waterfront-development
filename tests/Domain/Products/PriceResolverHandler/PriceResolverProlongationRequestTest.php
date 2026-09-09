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
use Waterfront\Domain\Pricing\DTO\PriceComponents\PriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\ProductGroupPriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\ProlongationPriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\ProlongationStaffelPriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\RegistrationPriceComponent;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Products\DTO\PriceRequest;
use Waterfront\Domain\Products\DTO\ProlongationPriceRequest;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Domain\Products\VolumeDiscountService;

#[CoversClass(PriceResolver::class)]
class PriceResolverProlongationRequestTest extends IntegrationTestCase
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
        $product = new ProductFactory()->for(new ProductGroupFactory())->createOne();
        $priceModel = new ProductPriceComponentFactory()->for($product)->prolongation()->createOne([
            'price' => 123,
        ]);

        $priceRequest = new PriceRequest([new ProlongationPriceRequest($product)], null);
        $priceList = $this->priceResolver->getPriceList($priceRequest);
        $priceDto = $priceList->getProductPrice($product->slug, $priceModel->contract_period, $priceModel->billing_period);

        self::assertCount(2, $priceDto->possiblePriceComponents);
        self::assertInstanceOf(RegistrationPriceComponent::class, $priceDto->possiblePriceComponents[0]);
        self::assertInstanceOf(ProlongationPriceComponent::class, $priceDto->possiblePriceComponents[1]);
        self::assertSame(123, $priceDto->possiblePriceComponents[0]->price);

        self::assertCount(2, $priceDto->appliedPriceComponents);
        $registrationComponent = $priceDto->appliedPriceComponents[0];
        $prolongationComponent = $priceDto->appliedPriceComponents[1];
        self::assertInstanceOf(RegistrationPriceComponent::class, $registrationComponent);
        self::assertInstanceOf(ProlongationPriceComponent::class, $prolongationComponent);
        self::assertSame(123, $registrationComponent->price);
        self::assertSame(1, $registrationComponent->appliedOrder);
        self::assertSame(123, $prolongationComponent->newPrice);
        self::assertSame(2, $prolongationComponent->appliedOrder);
        self::assertSame(123, $priceDto->calculatedPrice);
    }

    #[Test]
    public function staffelTakesPrecedenceOverEverything(): void
    {
        // Spawn every possible pricing structure we have, in order to see if staffel is taken.
        $productDiscountService = self::resolve(VolumeDiscountService::class);
        $customer = new CustomerFactory()->createOne();
        $productGroup = new ProductGroupFactory()->extension()->createOne();
        $customer->productGroups()->attach($productGroup->id, ['discount' => 70]);
        $product = new ProductFactory()->for($productGroup)->createOne();
        new ProductPriceComponentFactory()->for($product)->createMany([
            ['price' => 789, 'type' => PriceComponentType::PROMOTION],
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
        new ProductIntroductionDiscountsFactory()->for($product)->createOne(['max_uses_per_customer' => 5, 'contract_period' => 12]);

        $priceRequest = new PriceRequest([new ProlongationPriceRequest($product)], $customer);
        $priceList = $this->priceResolver->getPriceList($priceRequest);
        $priceDto = $priceList->getProductPrice($product->slug, 12, 12);

        // Make sure that there are as many possible priceComponents as priceComponent enum variants. In other words, make sure
        // that every possible pricing structure is set up for the product. We need to be certain that when a new
        // pricing structure is introduced we are forced to think about how that interacts with the prolongation scenario.
        self::assertCount(count(PriceComponentType::cases()) - 4, array_unique($priceDto->possiblePriceComponents, SORT_REGULAR));
        // Pro-rate is an exception, since that isn't supported during prolongation.
        self::assertNotContains(PriceComponentType::PRO_RATE, array_column($priceDto->possiblePriceComponents, 'type'));
        // Custom one-off is an exception, since that is a price used to override a subscription price.
        self::assertNotContains(PriceComponentType::CUSTOM_ONE_OFF, array_column($priceDto->possiblePriceComponents, 'type'));
        // Custom indefinite is an exception, since that is a price used to override a subscription price.
        self::assertNotContains(PriceComponentType::CUSTOM_INDEFINITE, array_column($priceDto->possiblePriceComponents, 'type'));
        // Voucher is an exception, since that is a price that will only appear in appliedPriceComponents.
        self::assertNotContains(PriceComponentType::VOUCHER, array_column($priceDto->possiblePriceComponents, 'type'));

        self::assertCount(3, $priceDto->appliedPriceComponents);
        $registrationComponent = $priceDto->appliedPriceComponents[0];
        $prolongationComponent = $priceDto->appliedPriceComponents[1];
        $staffelComponent = $priceDto->appliedPriceComponents[2];
        self::assertInstanceOf(RegistrationPriceComponent::class, $registrationComponent);
        self::assertInstanceOf(ProlongationPriceComponent::class, $prolongationComponent);
        self::assertInstanceOf(ProlongationStaffelPriceComponent::class, $staffelComponent);
        self::assertSame(123, $registrationComponent->price);
        self::assertSame(1, $registrationComponent->appliedOrder);
        self::assertSame(987, $prolongationComponent->newPrice);
        self::assertSame(2, $prolongationComponent->appliedOrder);
        self::assertSame(3821, $staffelComponent->newPrice);
        self::assertSame(3, $staffelComponent->appliedOrder);
        self::assertSame(3821, $priceDto->calculatedPrice);
    }

    #[Test]
    public function takesRegistrationWhenProlongationDoesntExist(): void
    {
        $product = new ProductFactory()->for(new ProductGroupFactory())->createOne();
        $priceModel = new ProductPriceComponentFactory()->for($product)->registration()->createOne([
            'price' => 123,
        ]);

        $priceRequest = new PriceRequest([new ProlongationPriceRequest($product)], null);
        $priceList = $this->priceResolver->getPriceList($priceRequest);
        $priceDto = $priceList->getProductPrice($product->slug, $priceModel->contract_period, $priceModel->billing_period);

        self::assertCount(1, $priceDto->appliedPriceComponents);
        $registrationComponent = $priceDto->appliedPriceComponents[0];
        self::assertInstanceOf(RegistrationPriceComponent::class, $registrationComponent);
        self::assertSame(123, $registrationComponent->price);
        self::assertSame(1, $registrationComponent->appliedOrder);
        self::assertSame(123, $priceDto->calculatedPrice);
    }

    #[Test]
    public function productGroupIsStackedOnTopOfProlongation(): void
    {
        $customer = new CustomerFactory()->createOne();
        $productGroup = new ProductGroupFactory()->createOne();
        $customer->productGroups()->attach($productGroup->id, ['discount' => 43]);
        $product = new ProductFactory()->for($productGroup)->createOne();
        $priceModel = new ProductPriceComponentFactory()->for($product)->registration()->createOne([
            'price' => 123,
        ]);
        new ProductPriceComponentFactory()->for($product)->prolongation()->createOne([
            'price' => 432,
        ]);

        $priceRequest = new PriceRequest([new ProlongationPriceRequest($product)], $customer);
        $priceList = $this->priceResolver->getPriceList($priceRequest);
        $priceDto = $priceList->getProductPrice($product->slug, $priceModel->contract_period, $priceModel->billing_period);

        self::assertCount(3, $priceDto->appliedPriceComponents);
        $registrationComponent = $priceDto->appliedPriceComponents[0];
        $prolongationComponent = $priceDto->appliedPriceComponents[1];
        $productGroupComponent = $priceDto->appliedPriceComponents[2];
        self::assertInstanceOf(RegistrationPriceComponent::class, $registrationComponent);
        self::assertInstanceOf(ProlongationPriceComponent::class, $prolongationComponent);
        self::assertInstanceOf(ProductGroupPriceComponent::class, $productGroupComponent);
        self::assertSame(123, $registrationComponent->price);
        self::assertSame(1, $registrationComponent->appliedOrder);
        self::assertSame(432, $prolongationComponent->newPrice);
        self::assertSame(2, $prolongationComponent->appliedOrder);
        self::assertSame(246, $productGroupComponent->newPrice);
        self::assertSame(3, $productGroupComponent->appliedOrder);
        self::assertSame(246, $priceDto->calculatedPrice);
    }

    #[Test]
    public function productGroupIsStackedOnTopOfRegistration(): void
    {
        $customer = new CustomerFactory()->createOne();
        $productGroup = new ProductGroupFactory()->createOne();
        $customer->productGroups()->attach($productGroup->id, ['discount' => 26]);
        $product = new ProductFactory()->for($productGroup)->createOne();
        $priceModel = new ProductPriceComponentFactory()->for($product)->registration()->createOne([
            'price' => 777,
        ]);

        $priceRequest = new PriceRequest([new ProlongationPriceRequest($product)], $customer);
        $priceList = $this->priceResolver->getPriceList($priceRequest);
        $priceDto = $priceList->getProductPrice($product->slug, $priceModel->contract_period, $priceModel->billing_period);

        self::assertCount(2, $priceDto->appliedPriceComponents);
        $registrationComponent = $priceDto->appliedPriceComponents[0];
        $productGroupComponent = $priceDto->appliedPriceComponents[1];
        self::assertInstanceOf(RegistrationPriceComponent::class, $registrationComponent);
        self::assertInstanceOf(ProductGroupPriceComponent::class, $productGroupComponent);
        self::assertSame(777, $registrationComponent->price);
        self::assertSame(1, $registrationComponent->appliedOrder);
        self::assertSame(575, $productGroupComponent->newPrice);
        self::assertSame(2, $productGroupComponent->appliedOrder);
        self::assertSame(575, $priceDto->calculatedPrice);
    }

    #[Test]
    public function staffelTakesPrecendenceOverProductGroup(): void
    {
        $productDiscountService = self::resolve(VolumeDiscountService::class);
        $customer = new CustomerFactory()->createOne();
        $productGroup = new ProductGroupFactory()->createOne();
        $customer->productGroups()->attach($productGroup->id, ['discount' => 19]);
        $product = new ProductFactory()->for($productGroup)->createOne();
        $priceModel = new ProductPriceComponentFactory()->for($product)->registration()->createOne([
            'price' => 123,
        ]);
        new ProductPriceComponentFactory()->for($product)->prolongation()->createOne([
            'price' => 902,
        ]);

        $staffel = new ProductDiscountFactory()->for($product)->createOne();
        new CustomerProductDiscountFactory()->for($customer)->for($staffel)->createOne();
        $prolongationStaffelPrice = new ProductPriceComponentFactory()->for($product)->createOne([
            'price' => 3420,
            'type' => PriceComponentType::PROLONGATION_STAFFEL,
        ]);
        $productDiscountService->attachPrice($staffel, $prolongationStaffelPrice);

        $priceRequest = new PriceRequest([new ProlongationPriceRequest($product)], $customer);
        $priceList = $this->priceResolver->getPriceList($priceRequest);
        $priceDto = $priceList->getProductPrice($product->slug, $priceModel->contract_period, $priceModel->billing_period);

        self::assertCount(3, $priceDto->appliedPriceComponents);
        $registrationComponent = $priceDto->appliedPriceComponents[0];
        $prolongationComponent = $priceDto->appliedPriceComponents[1];
        $staffelComponent = $priceDto->appliedPriceComponents[2];
        self::assertInstanceOf(RegistrationPriceComponent::class, $registrationComponent);
        self::assertInstanceOf(ProlongationPriceComponent::class, $prolongationComponent);
        self::assertInstanceOf(ProlongationStaffelPriceComponent::class, $staffelComponent);
        self::assertSame(123, $registrationComponent->price);
        self::assertSame(1, $registrationComponent->appliedOrder);
        self::assertSame(902, $prolongationComponent->newPrice);
        self::assertSame(2, $prolongationComponent->appliedOrder);
        self::assertSame(3420, $staffelComponent->newPrice);
        self::assertSame(3, $staffelComponent->appliedOrder);
        self::assertSame(3420, $priceDto->calculatedPrice);
    }

    #[Test]
    public function proRateIsNeverAppliedDuringProlongation(): void
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

        $priceRequest = new PriceRequest([new ProlongationPriceRequest($product)], $customer);
        $priceList = $this->priceResolver->getPriceList($priceRequest);
        $priceDto = $priceList->getProductPrice($product->slug, $priceModel->contract_period, $priceModel->billing_period);

        self::assertCount(1, $priceDto->appliedPriceComponents);
        $registrationComponent = $priceDto->appliedPriceComponents[0];
        self::assertInstanceOf(RegistrationPriceComponent::class, $registrationComponent);
        self::assertSame(902, $registrationComponent->price);
        self::assertSame(1, $registrationComponent->appliedOrder);
        self::assertSame(902, $priceDto->calculatedPrice);
    }

    #[Test]
    public function usesPricesTableWhenNoProductPriceExists(): void
    {
        $product = new ProductFactory()->nlDomain()->createOne();
        new ProductPriceComponentFactory()->for($product)->prolongation()->createOne();

        $priceResolver = self::resolve(PriceResolver::class);
        $priceList = $priceResolver->getPriceList(new PriceRequest([new ProlongationPriceRequest($product)], null));

        self::assertCount(1, $priceList);
        $priceEntry = $priceList->getProductPrice($product->slug, 12, 12);

        self::assertCount(2, $priceEntry->appliedPriceComponents);
        self::assertSame([PriceComponentType::REGISTRATION, PriceComponentType::PROLONGATION], array_map(fn (PriceComponent $priceComponent) => $priceComponent->type, $priceEntry->appliedPriceComponents));
    }
}
