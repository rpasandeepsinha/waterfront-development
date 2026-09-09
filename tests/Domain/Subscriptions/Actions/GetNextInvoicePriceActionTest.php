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
use Waterfront\Domain\Pricing\Models\ProductPriceComponent as PriceModel;
use Waterfront\Domain\Pricing\Models\SubscriptionPrice;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\DTO\PriceList;
use Waterfront\Domain\Products\Enums\ProductPriceType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Actions\GetNextInvoicePriceAction;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(GetNextInvoicePriceAction::class)]
class GetNextInvoicePriceActionTest extends IntegrationTestCase
{
    private Product $product;

    private PriceModel $registrationPrice;

    private PriceModel $prolongationPrice;

    private GetNextInvoicePriceAction $getNextInvoicePriceAction;

    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::now());

        $this->getNextInvoicePriceAction = self::resolve(GetNextInvoicePriceAction::class);

        $this->product = new ProductFactory()->hostingBrons()->createOne();

        $this->registrationPrice = new ProductPriceComponentFactory()->for($this->product)->registration()->createOne(['price' => 200]);
        $promotionPrice = new ProductPriceComponentFactory()->for($this->product)->createOne(['type' => PriceComponentType::PROMOTION, 'price' => 100]);

        $this->prolongationPrice = new ProductPriceComponentFactory()->for($this->product)->prolongation()->createOne([
            'billing_period' => 12,
            'contract_period' => 12,
            'price' => 300,
        ]);

        $this->subscription = new SubscriptionFactory()->withCustomer()->withPrice()->for($this->product)->createOne([
            'contract_period' => $this->registrationPrice->contract_period,
            'billing_period' => $this->registrationPrice->billing_period,
            'gross_price' => $this->registrationPrice->price,
            'net_price' => $promotionPrice->price,
            'end_date' => CarbonImmutable::now(),
            'next_billing_date' => CarbonImmutable::now(),
        ]);
    }

    #[Test]
    public function invoiceForNewContractPeriodShouldGetProlongationPrice(): void
    {
        $nextInvoicePrice = $this->getNextInvoicePriceAction->execute($this->subscription);

        self::assertSame($this->subscription->product->id, $nextInvoicePrice->product->id);
        self::assertSame($this->subscription->billing_period, $nextInvoicePrice->billingPeriod);
        self::assertSame($this->prolongationPrice->price, $nextInvoicePrice->grossPrice);
        self::assertSame($this->prolongationPrice->price, $nextInvoicePrice->netPrice);
        self::assertSame(CarbonImmutable::now()->startOfDay()->getTimestamp(), $nextInvoicePrice->startDate->startOfDay()->getTimestamp());
        self::assertSame(CarbonImmutable::now()->addMonths($this->registrationPrice->billing_period)->startOfDay()->getTimestamp(), $nextInvoicePrice->endDate->startOfDay()->getTimestamp());
    }

    #[Test]
    public function invoiceDuringContractPeriodShouldUseSubscriptionPrice(): void
    {
        $this->subscription->end_date = CarbonImmutable::now()->addYear();
        $this->subscription->save();
        $this->subscription->refresh();

        $nextInvoicePrice = $this->getNextInvoicePriceAction->execute($this->subscription);

        self::assertSame($this->subscription->product->id, $nextInvoicePrice->product->id);
        self::assertSame($this->subscription->billing_period, $nextInvoicePrice->billingPeriod);
        self::assertSame($this->subscription->gross_price, $nextInvoicePrice->grossPrice);
        self::assertSame($this->subscription->net_price, $nextInvoicePrice->netPrice);
        self::assertSame(CarbonImmutable::now()->startOfDay()->getTimestamp(), $nextInvoicePrice->startDate->startOfDay()->getTimestamp());
        self::assertSame(CarbonImmutable::now()->addMonths($this->registrationPrice->billing_period)->startOfDay()->getTimestamp(), $nextInvoicePrice->endDate->startOfDay()->getTimestamp());
    }

    #[Test]
    public function invoiceDuringContractPeriodShouldUsePrecalculatedPriceInsteadOfSubscriptionPrice(): void
    {
        $this->subscription->end_date = CarbonImmutable::now()->addYear();
        $this->subscription->net_price = 300;
        $this->subscription->save();

        $currentPrice = new SubscriptionPrice();
        $currentPrice->subscription_id = $this->subscription->id;
        $currentPrice->net_price = 300;
        $currentPrice->valid_from = $this->subscription->start_date;
        $currentPrice->save();

        $nextPrice = new SubscriptionPrice();
        $nextPrice->subscription_id = $this->subscription->id;
        $nextPrice->net_price = 150;
        $nextPrice->valid_from = $this->subscription->start_date->addMonths($this->subscription->billing_period);
        $nextPrice->save();

        $nextPrice2 = new SubscriptionPrice();
        $nextPrice2->subscription_id = $this->subscription->id;
        $nextPrice2->net_price = 100;
        $nextPrice2->valid_from = $this->subscription->start_date->addMonths($this->subscription->billing_period * 2);
        $nextPrice2->save();

        $nextInvoicePrice = $this->getNextInvoicePriceAction->execute($this->subscription);

        self::assertSame($nextPrice->net_price, $nextInvoicePrice->netPrice);
    }

    #[Test]
    public function customPriceListShouldBeUsedIfGiven(): void
    {
        $customProlongationPrice = new Price(
            ProductPriceType::REGISTRATION,
            12,
            $this->product->id,
            'uuid',
            999,
            12,
            true,
            true,
            calculatedPrice: 999,
        );

        $priceList = self::createStub(PriceList::class);
        $priceList->method('getProductPrice')->willReturn($customProlongationPrice);

        $nextInvoicePrice = $this->getNextInvoicePriceAction->execute($this->subscription, $priceList);

        self::assertSame($this->subscription->product->id, $nextInvoicePrice->product->id);
        self::assertSame($this->subscription->billing_period, $nextInvoicePrice->billingPeriod);
        self::assertSame($customProlongationPrice->regularPrice, $nextInvoicePrice->grossPrice);
        self::assertSame($customProlongationPrice->calculatedPrice, $nextInvoicePrice->netPrice);
    }

    #[Test]
    public function subscriptionWithoutProlongationPriceShouldTakeRegistrationPrice(): void
    {
        $product = new ProductFactory()->nlDomain()->createOne();
        $price = new ProductPriceComponentFactory()->for($product)->registration()->createOne();

        $subscription = new SubscriptionFactory()
            ->for($product)
            ->withCustomer()
            ->createOne([
                'contract_period' => $price->contract_period,
                'billing_period' => $price->billing_period,
                'end_date' => CarbonImmutable::now(),
                'next_billing_date' => CarbonImmutable::now()->addMonth(),
                'gross_price' => $price->price * 2,
                'net_price' => $price->price,
        ]);

        $nextInvoicePrice = $this->getNextInvoicePriceAction->execute($subscription);

        self::assertSame($price->price, $nextInvoicePrice->grossPrice);
        self::assertSame($price->price, $nextInvoicePrice->netPrice);
        self::assertSame($price->billing_period, $nextInvoicePrice->billingPeriod);
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

        $nextInvoicePrice = $this->getNextInvoicePriceAction->execute($this->subscription);

        self::assertSame($otherProduct->id, $nextInvoicePrice->product->id);
        self::assertSame(1, $nextInvoicePrice->billingPeriod);
        self::assertSame(50, $nextInvoicePrice->grossPrice);
        self::assertSame(40, $nextInvoicePrice->netPrice);
        self::assertSame(CarbonImmutable::now()->startOfDay()->getTimestamp(), $nextInvoicePrice->startDate->startOfDay()->getTimestamp());
        self::assertSame(CarbonImmutable::now()->addMonth()->startOfDay()->getTimestamp(), $nextInvoicePrice->endDate->startOfDay()->getTimestamp());
    }

    #[Test]
    public function keepInvoiceEndDateInSyncWithSubscriptionEndDate(): void
    {
        $this->subscription->billing_period = 1;
        $this->subscription->next_billing_date = $this->subscription->end_date->setMonth(3)->setDay(3);
        $this->subscription->end_date = $this->subscription->end_date->setMonth(3)->setDay(31);
        $this->subscription->save();

        $nextInvoicePrice = $this->getNextInvoicePriceAction->execute($this->subscription);

        self::assertSame($this->subscription->end_date->getTimestamp(), $nextInvoicePrice->endDate->getTimestamp());
    }
}
