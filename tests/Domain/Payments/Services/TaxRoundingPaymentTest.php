<?php

declare(strict_types=1);

namespace Tests\Domain\Payments\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\OrderFactory;
use Tests\Factories\OrderLineItemFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Payments\Services\CustomerSharedPaymentService;

#[CoversClass(CustomerSharedPaymentService::class)]
class TaxRoundingPaymentTest extends IntegrationTestCase
{
    private CustomerSharedPaymentService $paymentService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentService = self::resolve(CustomerSharedPaymentService::class);
    }

    #[DataProvider('getPricesDataProvider')]
    #[Test]
    public function correctPriceRounding(int $price, float $vatPrice, string $correctPrice): void
    {
        $customer = new CustomerFactory()->createOne([
            'vat_rate' => 21.00,
        ]);

        $order = new OrderFactory()->for($customer)->createOne();

        $group = new ProductGroupFactory()->createOne([
            'slug' => 'hosting',
            'name' => 'hosting',
        ]);

        $product = new ProductFactory()->createOne([
            'product_group_id' => $group->id,
            'name' => 'Zilver',
            'slug' => 'hosting_zilver',
        ]);

        $subscription = new SubscriptionFactory()->withCustomer()->createOne([
            'product_uuid' => $product->uuid,
            'customer_id' => $customer->id,
        ]);

        $orderLineItem1 = new OrderLineItemFactory()->makeOne([
            'subscription_uuid' => $subscription->uuid,
            'product_uuid' => $product->uuid,
            'gross_price' => $price,
        ]);

        $order->lineItems()->save($orderLineItem1);

        $vatRate = (1 + ((int) $customer->vat_rate / 100));
        $totalPrice = $order->lineItems()->sum('gross_price') * $vatRate;

        $result = $this->paymentService->correctPrice($totalPrice);

        self::assertSame($vatPrice, $totalPrice);
        self::assertSame($correctPrice, $result);
    }

    /**
     * @return mixed[]
     */
    public static function getPricesDataProvider(): array
    {
        return [
            [99, 119.78999999999999, '1.20'], // Round up the price
            [22, 26.6199999999999979, '0.27'], // Round up the price
            [97, 117.36999999999999, '1.17'], // Round down the price
            [20, 24.2, '0.24'], // Round down the price
        ];
    }
}
