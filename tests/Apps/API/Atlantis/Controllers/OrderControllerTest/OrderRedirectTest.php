<?php

declare(strict_types=1);

namespace Tests\Apps\API\Atlantis\Controllers\OrderControllerTest;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\OrderController;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(OrderController::class)]
class OrderRedirectTest extends IntegrationTestCase
{
    #[Test]
    public function orderRedirect(): void
    {
        $testDomain = 'test-redirect-domain.com'; // Matches json

        $redirectProduct = ProductFactory::new()->redirect()->createOne();

        new ProductPriceComponentFactory()
            ->registration()
            ->for($redirectProduct)
            ->createOne();

        $customer = CustomerFactory::new()->withAddress()->createOne();

        $jsonData = (string) file_get_contents(__DIR__ . '/data/order_payload_redirect.json');

        if (! json_validate($jsonData)) {
            $this->fail('Invalid order JSON, could not run test');
        }

        /** @var array<mixed, mixed> $orderData */
        $orderData = json_decode($jsonData, associative: true);

        $response = $this->actingAsCustomer($customer)->json(
            'post',
            $this->generateRoute('partners.order.order'),
            $orderData,
        );

        $response->assertOk();
        $response->assertJsonFragment([
            'status' => 'ok',
        ]);

        self::assertDatabaseHas(Subscription::class, [
            'customer_id' => $customer->id,
            'product_uuid' => $redirectProduct->uuid,
            'domain' => $testDomain,
            'administrative_status' => AdministrativeStatus::ACTIVE->value,
        ]);
    }
}
