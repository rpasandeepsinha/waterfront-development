<?php

declare(strict_types=1);

namespace Tests\Apps\API\Atlantis\Controllers\OrderControllerTest;

use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\OrderController;
use Waterfront\Domain\Backup\Events\CreateBackup;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Products\Models\Product;

#[CoversClass(OrderController::class)]
class OrderBackupTest extends IntegrationTestCase
{
    private Customer $customer;

    private Product $backupProduct;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([CreateBackup::class]);

        $this->customer = new CustomerFactory()->withAddress()->createOne();

        $this->backupProduct = new ProductFactory()->backupAcronis()->createOne();

        new ProductPriceComponentFactory()->for($this->backupProduct)->registration()->createOne(['price' => 120]);
        new ProductPriceComponentFactory()->for($this->backupProduct)->createOne(['type' => PriceComponentType::PROMOTION, 'price' => 96]);
    }

    #[Test]
    public function orderSuccess(): void
    {
        /** @var mixed[] $orderData */
        $orderData = json_decode((string) file_get_contents(__DIR__ . '/data/order_payload_backup.json'), true, 512, JSON_THROW_ON_ERROR);

        $this->actingAsCustomer($this->customer)
            ->postJson($this->generateRoute('partners.order.order'), $orderData)
            ->assertOk()
            ->assertJsonFragment([
                'status' => 'ok',
            ]);

        Event::assertDispatched(CreateBackup::class);
        self::assertDatabaseHas(
            'order_line_items',
            [
                'product_uuid' => $this->backupProduct->uuid,
            ]
        );
    }
}
