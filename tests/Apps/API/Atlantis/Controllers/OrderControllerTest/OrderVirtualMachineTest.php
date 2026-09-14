<?php

declare(strict_types=1);

namespace Tests\Apps\API\Atlantis\Controllers\OrderControllerTest;

use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\SshKeyFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\OrderController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\VPS\Events\CreateVps;

#[CoversClass(OrderController::class)]
class OrderVirtualMachineTest extends IntegrationTestCase
{
    private Customer $customer;

    private Product $osProductWithSshKey;

    private Product $osProductWithoutSshKey;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake(
            [
                CreateVps::class,
            ],
        );

        $this->customer = new CustomerFactory()->withAddress()->createOne();

        $vpsGroup = new ProductGroupFactory()->vps()->createOne();
        $vpsProduct = new ProductFactory()->for($vpsGroup)->createOne(['slug' => 'vps-32-red']);

        $cloudstackOsGroup = new ProductGroupFactory()->cloudstackOs()->createOne();
        $this->osProductWithoutSshKey = new ProductFactory()->for($cloudstackOsGroup)->createOne([
            'slug' => 'ubuntu-lts-20.04',
        ]);

        $this->osProductWithSshKey = new ProductFactory()->for($cloudstackOsGroup)->createOne([
            'slug' => 'ubuntu-lts-20.04-ssh',
        ]);
        new ProductSpecFactory()->for($this->osProductWithSshKey)->createOne([
            'name' => ProductSpecName::SSH_KEY_REQUIRED->value,
            'value' => 1,
        ]);

        new ProductPriceComponentFactory()
            ->for($vpsProduct)
            ->registration()
            ->createOne(['price' => 120]);
        new ProductPriceComponentFactory()->for($vpsProduct)->createOne([
            'type' => PriceComponentType::PROMOTION,
            'price' => 96,
        ]);

        new ProductPriceComponentFactory()
            ->for($this->osProductWithoutSshKey)
            ->registration()
            ->createOne([
                'price' => 0,
                'billing_period' => 1,
                'contract_period' => 1,
            ]);

        new ProductPriceComponentFactory()
            ->for($this->osProductWithSshKey)
            ->registration()
            ->createOne([
                'price' => 0,
                'billing_period' => 1,
                'contract_period' => 1,
            ]);
    }

    #[Test]
    public function orderVpsWithoutSshKey(): void
    {
        /** @var array<mixed, mixed> $orderData */
        $orderData = json_decode(
            (string) file_get_contents(__DIR__ . '/data/order_payload_vps.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->actingAsCustomer($this->customer)
            ->postJson($this->generateRoute('partners.order.order'), $orderData)
            ->assertOk()
            ->assertJsonFragment([
                'status' => 'ok',
            ]);

        Event::assertDispatched(CreateVps::class);
        self::assertDatabaseHas(
            'order_line_items',
            [
                'product_uuid' => $this->osProductWithoutSshKey->uuid,
                'meta_data' => '{"ssh_key_uuid":null,"type":"cloudstack-os"}',
            ],
        );
    }

    #[Test]
    public function orderVpsWithSshKey(): void
    {
        new SshKeyFactory()->for($this->customer)->createOne(['uuid' => '80782a1b-b337-42c5-bb84-7d461420e1ea']);

        /** @var array<mixed, mixed> $orderData */
        $orderData = json_decode(
            (string) file_get_contents(__DIR__ . '/data/order_payload_vps_with_ssh_key.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->actingAsCustomer($this->customer)
            ->postJson($this->generateRoute('partners.order.order'), $orderData)
            ->assertOk()
            ->assertJsonFragment([
                'status' => 'ok',
            ]);

        Event::assertDispatched(CreateVps::class);
        self::assertDatabaseHas(
            'order_line_items',
            [
                'product_uuid' => $this->osProductWithSshKey->uuid,
                'meta_data' => '{"ssh_key_uuid":"80782a1b-b337-42c5-bb84-7d461420e1ea","type":"cloudstack-os"}',
            ],
        );
    }
}
