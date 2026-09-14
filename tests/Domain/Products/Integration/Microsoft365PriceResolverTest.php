<?php

declare(strict_types=1);

namespace Tests\Domain\Products\Integration;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\DTO\PriceRequest;
use Waterfront\Domain\Products\DTO\RegistrationPriceRequest;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Domain\Products\ProductPrice\PriceResolverHandler\Microsoft365RemainingDurationComponentPriceHandler;

#[CoversClass(Microsoft365RemainingDurationComponentPriceHandler::class)]
class Microsoft365PriceResolverTest extends IntegrationTestCase
{
    private PriceResolver $priceResolver;

    private Customer $customer;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading(false);

        $this->priceResolver = self::resolve(PriceResolver::class);
        $this->customer = new CustomerFactory()->createOne();
        $this->product = new ProductFactory()->for(new ProductGroupFactory()->microsoft365())->createOne();
    }

    #[DataProvider('dataProvider')]
    #[Test]
    public function ASeatForAnExistingLicenseShouldGiveAProRatePrice(
        int $subscriptionPeriodInMonths,
        CarbonImmutable $startDate,
        int $daysElapsed,
        int $startingPrice,
        int $proRataPrice,
    ): void {
        $this->travelTo($startDate);

        new ProductPriceComponentFactory()
            ->for($this->product)
            ->registration()
            ->createOne([
                'contract_period' => $subscriptionPeriodInMonths,
                'billing_period' => $subscriptionPeriodInMonths,
                'price' => $startingPrice,
            ]);

        // To validate the correct subscription is used in the resolver, we also create a subscription
        // with the same product but a different period and start date (resulting in a different end date).
        new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->product)
            ->createOne([
                'contract_period' => $subscriptionPeriodInMonths === 12 ? 1 : 12,
                'billing_period' => $subscriptionPeriodInMonths === 12 ? 1 : 12,
                'start_date' => $startDate->clone()->subDays(5),
            ]);

        // The actual subscription to do the pro rata calculation on.
        new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->product)
            ->createOne([
                'contract_period' => $subscriptionPeriodInMonths,
                'billing_period' => $subscriptionPeriodInMonths,
                'start_date' => $startDate,
            ]);

        $this->travel($daysElapsed)->days();

        $priceRequest = new PriceRequest([new RegistrationPriceRequest($this->product)], $this->customer);
        $priceList = $this->priceResolver->getPriceList($priceRequest);
        $product = $priceList->firstOrFail();
        $priceDto = $product->prices->firstOrFail();

        self::assertSame($proRataPrice, $priceDto->regularPrice);
        self::assertSame($proRataPrice, $priceDto->calculatedPrice);

        $this->travelBack();
    }

    #[Test]
    public function ANonMicrosoft365PriceShouldNotBeAffected(): void
    {
        // Create a Microsoft 365 subscription to make sure we touch the Microsoft 365 pricing logic.
        new ProductPriceComponentFactory()
            ->for($this->product)
            ->registration()
            ->createOne();
        new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->product)
            ->createOne();

        // Create a random product.
        $productGroup = new ProductGroupFactory()->createOne(['slug' => ProductGroupType::HOSTING]);
        $product = new ProductFactory()->for($productGroup)->createOne();
        $productPrice = new ProductPriceComponentFactory()
            ->for($product)
            ->registration()
            ->createOne(['price' => 10000]);

        $priceRequest = new PriceRequest([new RegistrationPriceRequest($product)], $this->customer);
        $priceList = $this->priceResolver->getPriceList($priceRequest);
        $product = $priceList->firstOrFail();
        $price = $product->prices->firstOrFail();

        // Make sure that the price for the random product don't change.
        self::assertSame($productPrice->price, $price->regularPrice);
    }

    public static function dataProvider(): Iterator
    {
        yield '1 month subscription with 10 days elapsed and starting price of 530' => [
            1,
            CarbonImmutable::create(2022, 5),
            10,
            530,
            359,
        ];
        yield '1 month subscription with 0 days elapsed and starting price of 530' => [
            1,
            CarbonImmutable::create(2022, 5),
            0,
            530,
            530,
        ];
        yield '1 month subscription with 10 days elapsed and starting price of 0' => [
            1,
            CarbonImmutable::create(2022, 5),
            10,
            0,
            0,
        ];
        yield '12 month subscription with 170 days elapsed and starting price of 1234' => [
            12,
            CarbonImmutable::create(2022, 5),
            170,
            1234,
            659,
        ];
        yield '12 month subscription with 0 days elapsed and starting price of 1234' => [
            12,
            CarbonImmutable::create(2022, 5),
            0,
            1234,
            1234,
        ];
        yield '12 month subscription with 170 days elapsed and starting price of 0' => [
            12,
            CarbonImmutable::create(2022, 5),
            170,
            0,
            0,
        ];
    }
}
