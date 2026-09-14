<?php

declare(strict_types=1);

namespace Tests\Domain\Products\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\OrderFactory;
use Tests\Factories\OrderLineItemFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductIntroductionDiscountsFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProviderFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Orders\Models\Order;
use Waterfront\Domain\Pricing\DTO\PriceComponents\IntroductionPriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\PriceComponent;
use Waterfront\Domain\Pricing\Models\ProductIntroductionDiscount;
use Waterfront\Domain\Products\DTO\PriceRequest;
use Waterfront\Domain\Products\DTO\Product as ProductDto;
use Waterfront\Domain\Products\DTO\RegistrationPriceRequest;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;

#[CoversClass(ProductIntroductionDiscount::class)]
class IntroductionDiscountPriceResponseTest extends IntegrationTestCase
{
    private Product $product;

    private Order $order;

    private Customer $customer;

    private int $introductionPrice = 100;

    private ProductIntroductionDiscount $introductionDiscount;

    protected function setUp(): void
    {
        parent::setUp();

        ProviderFactory::new()->createOne([
            'type' => ProviderType::DOMAIN,
            'slug' => ProviderSlug::OPEN_PROVIDER,
            'enabled' => true,
            'default' => true,
        ]);

        $this->customer = new CustomerFactory()->createOneQuietly();

        $this->product = new ProductFactory()->for(new ProductGroupFactory()->hosting()->createOne())->createOne([
            'slug' => 'nl_domain',
            'name' => '.nl',
        ]);

        new ProductPriceComponentFactory()
            ->for($this->product)
            ->registration()
            ->createOne(['price' => 1000]);
        new ProductPriceComponentFactory()
            ->for($this->product)
            ->introduction()
            ->createOne(['price' => $this->introductionPrice]);

        $this->order = new OrderFactory()->createOneQuietly(['customer_id' => $this->customer->id]);

        // the rule that determines how many of 1 product can be ordered
        // before the introduction discount does not apply anymore
        // set the maximum uses of orderable products to 5, after the 5th the introduction discount
        // must not be applied anymore
        $this->introductionDiscount = new ProductIntroductionDiscountsFactory()->createOne([
            'product_id' => $this->product->id,
            'max_uses_per_customer' => 5,
            'contract_period' => 12,
        ]);
    }

    // test must prove that if customer never ordered, intro discount availability must match uses of intro discount maximum uses per customer
    #[Test]
    public function priceResolverResponseAvailabilityAll(): void
    {
        $priceResolver = self::resolve(PriceResolver::class);

        $priceRequest = new PriceRequest([new RegistrationPriceRequest($this->product)], $this->customer);
        $priceList = $priceResolver->getPriceList($priceRequest);

        $first = $priceList->first();
        self::assertInstanceOf(ProductDto::class, $first);

        $priceDto = $first->prices->firstOrFail();

        $introductionPriceComponent = array_find(
            $priceDto->possiblePriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent instanceof IntroductionPriceComponent,
        );

        self::assertSame(
            $this->introductionDiscount->max_uses_per_customer,
            $introductionPriceComponent?->remainingUses,
        );
    }

    // test must prove that if customer already ordered 4 of same product/period
    // intro discount availability must match uses of intro discount maximum uses per customer minus number of already ordered products
    #[Test]
    public function priceResolverResponseAvailabilityOne(): void
    {
        $numberOfPreviousOrderedProducts = 4;

        new OrderLineItemFactory()
            ->count($numberOfPreviousOrderedProducts)
            ->createQuietly([
                'subscription_uuid' => null,
                'order_id' => $this->order->id,
                'domain' => 'test-domein.nl',
                'product_uuid' => $this->product->uuid,
                'contract_period' => 12,
            ]);

        $priceResolver = self::resolve(PriceResolver::class);
        $priceRequest = new PriceRequest([new RegistrationPriceRequest($this->product)], $this->customer);
        $priceList = $priceResolver->getPriceList($priceRequest);

        $first = $priceList->first();

        self::assertInstanceOf(ProductDto::class, $first);

        $priceDto = $first->prices->firstOrFail();

        $introductionPriceComponent = array_find(
            $priceDto->possiblePriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent instanceof IntroductionPriceComponent,
        );

        self::assertSame(1, $introductionPriceComponent?->remainingUses);
    }

    #[Test]
    public function introductionPricesWithoutUsageAmount(): void
    {
        new OrderLineItemFactory()->create([
            'subscription_uuid' => null,
            'order_id' => $this->order->id,
            'product_uuid' => $this->product->uuid,
            'contract_period' => 12,
        ]);

        $this->introductionDiscount->max_uses_per_customer = null;
        $this->introductionDiscount->save();

        $priceResolver = self::resolve(PriceResolver::class);
        $priceRequest = new PriceRequest(
            [new RegistrationPriceRequest($this->product)],
            $this->customer,
            requestingForOrder: true,
        );
        $priceList = $priceResolver->getPriceList($priceRequest);
        $price = $priceList->getProductPrice($this->product->slug, 12, 12);

        self::assertSame($this->introductionPrice, $price->calculatedPrice);
    }

    // test must prove that if user is not logged in, all discount prices incl max availability must be
    // present in the price resolver response
    #[Test]
    public function priceResolverResponseNotLoggedIn(): void
    {
        $priceResolver = self::resolve(PriceResolver::class);
        $priceRequest = new PriceRequest([new RegistrationPriceRequest($this->product)], $this->customer);
        $priceList = $priceResolver->getPriceList($priceRequest);

        $first = $priceList->first();

        self::assertInstanceOf(ProductDto::class, $first);

        $priceDto = $first->prices->firstOrFail();

        $introductionPriceComponent = array_find(
            $priceDto->possiblePriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent instanceof IntroductionPriceComponent,
        );

        self::assertSame(
            $this->introductionDiscount->max_uses_per_customer,
            $introductionPriceComponent?->remainingUses,
        );
    }
}
