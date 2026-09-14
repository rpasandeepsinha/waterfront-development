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
use Tests\IntegrationTestCase;
use Waterfront\Domain\Pricing\DTO\PriceComponents\ProlongationPriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\ProlongationStaffelPriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\ProRatePriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\RegistrationPriceComponent;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Products\DTO\PriceRequest;
use Waterfront\Domain\Products\DTO\UpgradePriceRequest;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Domain\Products\VolumeDiscountService;

#[CoversClass(PriceResolver::class)]
class PriceResolverUpgradeRequestTest extends IntegrationTestCase
{
    private PriceResolver $priceResolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->priceResolver = self::resolve(PriceResolver::class);
    }

    #[Test]
    public function staffelTakesPrecedenceOverEverythingAndProRateIsApplied(): void
    {
        // Spawn every possible pricing structure we have, in order to see if staffel is taken.
        CarbonImmutable::setTestNow(CarbonImmutable::create(2023, 3, 14));
        $productDiscountService = self::resolve(VolumeDiscountService::class);
        $customer = new CustomerFactory()->createOne();
        $productGroup = new ProductGroupFactory()->extension()->createOne();
        $customer->productGroups()->attach($productGroup->id, ['discount' => 70]);
        $product = new ProductFactory()->for($productGroup)->createOne();
        new ProductPriceComponentFactory()->for($product)->createMany([
            ['price' => 456, 'type' => PriceComponentType::PROMOTION],
            ['price' => 111, 'type' => PriceComponentType::CUSTOM_ONE_OFF],
            ['price' => 987, 'type' => PriceComponentType::PROLONGATION],
            ['price' => 123, 'type' => PriceComponentType::REGISTRATION],
            ['price' => 789, 'type' => PriceComponentType::INTRODUCTION],
            ['price' => 321, 'type' => PriceComponentType::EXPERIMENT_PRICE_LADDER],
        ]);
        $staffel = new ProductDiscountFactory()->for($product)->createOne();
        new CustomerProductDiscountFactory()
            ->for($customer)
            ->for($staffel)
            ->createOne();
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
        new ProductIntroductionDiscountsFactory()->for($product)->createOne([
            'max_uses_per_customer' => 5,
            'contract_period' => 12,
        ]);

        $priceRequest = new PriceRequest([new UpgradePriceRequest(
            $product,
            new CarbonImmutable('2025-07-04'),
            123,
        )], $customer);
        $priceList = $this->priceResolver->getPriceList($priceRequest);
        $priceDto = $priceList->getProductPrice($product->slug, 12, 12);

        // Make sure that there are as many possible priceComponents as priceComponent enum variants. In other words, make sure
        // that every possible pricing structure is set up for the product. We need to be certain that when a new
        // pricing structure is introduced we are forced to think about how that interacts with the upgrade scenario.
        self::assertCount(
            count(PriceComponentType::cases()) - 4,
            array_unique($priceDto->possiblePriceComponents, SORT_REGULAR),
        );
        // Custom one-off is an exception, since that is a price used to override a subscription price.
        self::assertNotContains(PriceComponentType::CUSTOM_ONE_OFF, array_column(
            $priceDto->possiblePriceComponents,
            'type',
        ));
        // Custom indefinite is an exception, since that is a price used to override a subscription price.
        self::assertNotContains(PriceComponentType::CUSTOM_INDEFINITE, array_column(
            $priceDto->possiblePriceComponents,
            'type',
        ));
        // Voucher is an exception, since that is a price that will only appear in appliedPriceComponents.
        self::assertNotContains(PriceComponentType::VOUCHER, array_column($priceDto->possiblePriceComponents, 'type'));
        // The price ladder is an exception, since an upgrade is not a renewal. It is covered by experimentPriceIsNeverAppliedDuringAnUpgrade.
        self::assertNotContains(PriceComponentType::EXPERIMENT_PRICE_LADDER, array_column(
            $priceDto->possiblePriceComponents,
            'type',
        ));

        self::assertCount(4, $priceDto->appliedPriceComponents);
        self::assertInstanceOf(RegistrationPriceComponent::class, $priceDto->appliedPriceComponents[0]);
        self::assertInstanceOf(ProlongationPriceComponent::class, $priceDto->appliedPriceComponents[1]);
        self::assertInstanceOf(ProlongationStaffelPriceComponent::class, $priceDto->appliedPriceComponents[2]);
        self::assertInstanceOf(ProRatePriceComponent::class, $priceDto->appliedPriceComponents[3]);
        self::assertSame(123, $priceDto->appliedPriceComponents[0]->newPrice);
        self::assertSame(987, $priceDto->appliedPriceComponents[1]->newPrice);
        self::assertSame(3821, $priceDto->appliedPriceComponents[2]->newPrice);
        self::assertSame(3698, $priceDto->appliedPriceComponents[3]->newPrice);
        self::assertSame(3698, $priceDto->calculatedPrice);
    }

    /**
     * UpgradePriceRequest routes through the prolongation calculation, so this guards that an upgrade can never pick
     * up the renewal-only variant.
     */
    #[Test]
    public function experimentPriceIsNeverAppliedDuringAnUpgrade(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::create(2023, 3, 14));
        $customer = new CustomerFactory()->createOne();
        $product = new ProductFactory()->for(new ProductGroupFactory()->extension())->createOne();
        new ProductPriceComponentFactory()->for($product)->createMany([
            ['price' => 123, 'type' => PriceComponentType::REGISTRATION],
            ['price' => 987, 'type' => PriceComponentType::PROLONGATION],
            ['price' => 654, 'type' => PriceComponentType::EXPERIMENT_PRICE_LADDER],
        ]);

        $priceRequest = new PriceRequest([new UpgradePriceRequest(
            $product,
            new CarbonImmutable('2025-07-04'),
            123,
        )], $customer);
        $priceList = $this->priceResolver->getPriceList($priceRequest);
        $priceDto = $priceList->getProductPrice($product->slug, 12, 12);

        self::assertNotContains(PriceComponentType::EXPERIMENT_PRICE_LADDER, array_column(
            $priceDto->appliedPriceComponents,
            'type',
        ));
        self::assertNotContains(PriceComponentType::EXPERIMENT_PRICE_LADDER, array_column(
            $priceDto->possiblePriceComponents,
            'type',
        ));
    }
}
