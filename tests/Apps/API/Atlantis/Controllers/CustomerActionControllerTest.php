<?php

declare(strict_types=1);

namespace Tests\Apps\API\Atlantis\Controllers;

use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\Factories\CustomerFactory;
use Tests\Factories\OrderFactory;
use Tests\Factories\OrderLineItemFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Atlantis\Controllers\CustomerActionController;
use Waterfront\Domain\CustomerActionNeeded\Enums\CustomerActionSlug;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Orders\Models\Order;
use Waterfront\Domain\Products\Enums\ProductGroupType;

#[CoversClass(CustomerActionController::class)]
class CustomerActionControllerTest extends IntegrationTestCase
{
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->withAddress()->createOne();
    }

    #[Test]
    public function customerActions(): void
    {
        $order = $this->createOrderWithBackupSubscription($this->customer);

        $response = $this->actingAsCustomer($this->customer)->getJson($this->generateRoute(
            'storefront.customer-actions.show',
            [
                'order' => $order->uuid,
            ],
        ));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.productGroupSlug', ProductGroupType::BACKUP->value);
        $response->assertJsonPath('data.0.slug', CustomerActionSlug::ACRONIS_BACKUP);
        $response->assertJsonPath('data.0.productSlug', 'acronis-cyber-protect');
        $response->assertJsonStructure([
            'data' => [
                ['title', 'message', 'productGroupSlug', 'productSlug'],
            ],
        ]);
    }

    #[Test]
    public function accountVerificationAction(): void
    {
        $customer = new CustomerFactory()
            ->withAddress()
            ->createOne(['is_verified' => false]);
        $order = $this->createOrderWithBackupSubscription($customer);

        $response = $this->actingAsCustomer($customer, verified: false)->getJson($this->generateRoute(
            'storefront.customer-actions.show',
            [
                'order' => $order->uuid,
            ],
        ));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        $response->assertJsonPath('data.1.title', 'customer-action.account-verification.title');
        $response->assertJsonPath('data.1.slug', CustomerActionSlug::ACCOUNT_VERIFICATION);
        $response->assertJsonPath('data.1.productGroupSlug', null);
        $response->assertJsonPath('data.1.productSlug', null);
    }

    #[Test]
    public function emptyCustomerActions(): void
    {
        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();
        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($product)
            ->createOne();

        $order = new OrderFactory()->for($this->customer)->createOne();
        new OrderLineItemFactory()->createOne([
            'order_id' => $order->id,
            'subscription_uuid' => $subscription->uuid,
        ]);

        $response = $this->actingAsCustomer($this->customer)->getJson($this->generateRoute(
            'storefront.customer-actions.show',
            [
                'order' => $order->uuid,
            ],
        ));

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    #[Test]
    public function orderUuidShouldBeValid(): void
    {
        $response = $this->actingAsCustomer($this->customer)->getJson($this->generateRoute(
            'storefront.customer-actions.show',
            [
                'order' => 'not-a-uuid',
            ],
        ));

        $response->assertNotFound();
    }

    #[Test]
    public function orderShouldExist(): void
    {
        Config::set('app.env', 'production');
        Config::set('app.debug', false);

        $response = $this->actingAsCustomer($this->customer)->getJson($this->generateRoute(
            'storefront.customer-actions.show',
            [
                'order' => Uuid::uuid4()->toString(),
            ],
        ));

        $response->assertNotFound();
    }

    #[Test]
    public function forbiddenForAnOrderOfAnotherCustomer(): void
    {
        $order = $this->createOrderWithBackupSubscription(new CustomerFactory()->withAddress()->createOne());

        $response = $this->actingAsCustomer($this->customer)->getJson($this->generateRoute(
            'storefront.customer-actions.show',
            [
                'order' => $order->uuid,
            ],
        ));

        $response->assertForbidden();
    }

    #[Test]
    public function customerActionRequiresAuthentication(): void
    {
        $response = $this->getJson($this->generateRoute(
            'storefront.customer-actions.show',
            [
                'order' => Uuid::uuid4()->toString(),
            ],
        ));

        $response->assertUnauthorized();
    }

    private function createOrderWithBackupSubscription(Customer $customer): Order
    {
        $productGroup = new ProductGroupFactory()->backup()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne(['slug' => 'acronis-cyber-protect']);
        $subscription = new SubscriptionFactory()
            ->for($customer)
            ->for($product)
            ->createOne();

        $order = new OrderFactory()->for($customer)->createOne();
        new OrderLineItemFactory()->createOne([
            'order_id' => $order->id,
            'subscription_uuid' => $subscription->uuid,
        ]);

        return $order;
    }
}
