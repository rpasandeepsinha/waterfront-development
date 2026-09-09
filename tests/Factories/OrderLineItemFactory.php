<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Orders\Models\OrderLineItem;

/**
 * @extends Factory<OrderLineItem>
 */
class OrderLineItemFactory extends Factory
{
    protected $model = OrderLineItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_uuid' => $this->faker->uuid(),
            'subscription_uuid' => $this->faker->uuid(),
            'domain' => $this->faker->domainName(),
            'gross_price' => 55,
            'product_name' => $this->faker->name(),
            'contract_period' => 12,
            'billing_period' => 12,
            'net_price' => 50,
            'status' => 'registration',
            'should_invoice' => true,
        ];
    }

    public function withPrice(): self
    {
        return $this->afterCreating(function (OrderLineItem $orderLine): void {
            new OrderLinePriceFactory()->createOne(['order_line_item_id' => $orderLine->id]);
        });
    }

    public function parentOrderLineItem(OrderLineItem $orderLineItem): self
    {
        return $this->state(fn (): array => [
            'parent_id' => $orderLineItem->id,
        ]);
    }
}
