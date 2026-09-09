<?php

declare(strict_types=1);

namespace Tests\Domain\Subscriptions\Actions;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\SubscriptionMutationFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;
use Waterfront\Domain\Pricing\Services\PricePersistService;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\DTO\PriceList;
use Waterfront\Domain\Products\Enums\CustomPriceReasonType;
use Waterfront\Domain\Products\Enums\ProductPriceType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Actions\GetRenewalInfoAction;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(GetRenewalInfoAction::class)]
class GetRenewalInfoActionTest extends IntegrationTestCase
{
    private Product $product;

    private ProductPriceComponent $prolongationPrice;

    private GetRenewalInfoAction $getRenewalInfoAction;

    private Subscription $subscription;

    public function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::now());

        $this->getRenewalInfoAction = self::resolve(GetRenewalInfoAction::class);

        $this->product = new ProductFactory()->hostingBrons()->createOne();

        $registrationPrice = new ProductPriceComponentFactory()->for($this->product)->registration()->createOne(['price' => 200]);
        $promotionPrice = new ProductPriceComponentFactory()->for($this->product)->createOne(['type' => PriceComponentType::PROMOTION, 'price' => 100]);

        $this->prolongationPrice = new ProductPriceComponentFactory()->for($this->product)->prolongation()->createOne([
            'billing_period' => 12,
            'contract_period' => 12,
            'price' => 300,
        ]);

        $this->subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($this->product)
            ->createOne([
                'contract_period' => $registrationPrice->contract_period,
                'billing_period' => $registrationPrice->billing_period,
                'gross_price' => $registrationPrice->price,
                'net_price' => $promotionPrice->price,
                'end_date' => CarbonImmutable::now(),
                'next_billing_date' => CarbonImmutable::now(),
            ]);
    }

    #[Test]
    public function renewalInfoShouldGetProlongationPrice(): void
    {
        $renewalInfo = $this->getRenewalInfoAction->execute($this->subscription, null);

        self::assertSame($this->subscription->product->id, $renewalInfo->product->id);
        self::assertSame($this->subscription->contract_period, $renewalInfo->contractPeriod);
        self::assertSame($this->subscription->billing_period, $renewalInfo->billingPeriod);
        self::assertSame($this->prolongationPrice->price, $renewalInfo->grossPrice);
        self::assertSame($this->prolongationPrice->price, $renewalInfo->netPrice);
        self::assertSame(CarbonImmutable::now()->startOfDay()->getTimestamp(), $renewalInfo->startDate->startOfDay()->getTimestamp());
        self::assertSame(CarbonImmutable::now()->addMonths($this->subscription->contract_period)->startOfDay()->getTimestamp(), $renewalInfo->endDate->startOfDay()->getTimestamp());
    }

    #[Test]
    public function willGiveBackAvailableMutationForRenewalInfo(): void
    {
        $otherProduct = new ProductFactory()->nlDomain()->createOne();

        new SubscriptionMutationFactory()
            ->for($this->subscription)
            ->for($otherProduct)
            ->create([
                'contract_period' => 1,
                'billing_period' => 1,
                'gross_price' => 50,
                'net_price' => 40,
            ]);

        $renewalInfo = $this->getRenewalInfoAction->execute($this->subscription, null);

        self::assertSame($renewalInfo->product->id, $otherProduct->id);
        self::assertSame(1, $renewalInfo->contractPeriod);
        self::assertSame(1, $renewalInfo->billingPeriod);
        self::assertSame(50, $renewalInfo->grossPrice);
        self::assertSame(40, $renewalInfo->netPrice);
        self::assertSame(CarbonImmutable::now()->startOfDay()->getTimestamp(), $renewalInfo->startDate->startOfDay()->getTimestamp());
        self::assertSame(CarbonImmutable::now()->addMonth()->startOfDay()->getTimestamp(), $renewalInfo->endDate->startOfDay()->getTimestamp());
    }

    #[Test]
    public function customPriceListShouldBeUsedIfGiven(): void
    {
        $customRegistrationPrice = new Price(
            ProductPriceType::REGISTRATION,
            12,
            $this->product->id,
            'uuid',
            999,
            12,
            true,
            true,
            calculatedPrice: 999
        );

        $priceList = self::createStub(PriceList::class);
        $priceList->method('getProductPrice')->willReturn($customRegistrationPrice);

        $renewalInfo = $this->getRenewalInfoAction->execute($this->subscription, $priceList);

        self::assertSame($this->subscription->product->id, $renewalInfo->product->id);
        self::assertSame($this->subscription->contract_period, $renewalInfo->contractPeriod);
        self::assertSame($this->subscription->billing_period, $renewalInfo->billingPeriod);
        self::assertSame($customRegistrationPrice->regularPrice, $renewalInfo->grossPrice);
        self::assertSame($customRegistrationPrice->calculatedPrice, $renewalInfo->netPrice);
    }

    #[Test]
    public function indefiniteCustomPriceShouldBeKept(): void
    {
        $persistPersistService = self::resolve(PricePersistService::class);

        $persistPersistService->persistCustomPrice($this->subscription, 123, false, CustomPriceReasonType::FIXED_MIGRATION_PRICE);

        $renewalInfo = $this->getRenewalInfoAction->execute($this->subscription, null);

        self::assertSame(200, $renewalInfo->grossPrice);
        self::assertSame(123, $renewalInfo->netPrice);
    }
}
