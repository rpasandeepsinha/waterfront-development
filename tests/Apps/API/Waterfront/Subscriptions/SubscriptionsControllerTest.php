<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Subscriptions;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\SubscriptionController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\Translator;

#[CoversClass(SubscriptionController::class)]
class SubscriptionsControllerTest extends IntegrationTestCase
{
    private Customer $customer;

    private Subscription $subscription;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow();

        $this->customer = new CustomerFactory()->createOne();
        $productGroup = new ProductGroupFactory()->extension()->createOne();

        $this->product = new ProductFactory()->for($productGroup)->createOne(['slug' => 'extension_nl']);

        new ProductPriceComponentFactory()
            ->prolongation()
            ->for($this->product)
            ->createOne();

        $this->subscription = new SubscriptionFactory()
            ->for($this->product)
            ->for($this->customer)
            ->administrativeStatusActive()
            ->createOne();
    }

    #[Test]
    public function cancelWhenSubscriptionShouldDowngrade(): void
    {
        new ProductSpecFactory()->for($this->product)->createOne([
            'name' => 'product.downgrade_when_cancelled',
            'value' => true,
        ]);

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.subscriptions.cancel'),
                [
                    'subscriptions' => [
                        [
                            'type' => $this->subscription->product->productGroup->slug->value,
                            'uuid' => $this->subscription->uuid,
                            'cancel' => true,
                            'cancel_type' => SubscriptionCancelType::CANCEL_OTHER,
                            'cancel_reason' => SubscriptionCancelReason::REASON_CANCELLATION,
                        ],
                    ],
                ],
            )
            ->assertUnprocessable()
            ->assertJsonFragment([
                'errors' => [
                    'subscriptions.0.cancel_type' => [
                        sprintf(
                            "Can't cancel subscription with uuid [%s] that should downgrade or be cancelled at end date",
                            $this->subscription->uuid,
                        ),
                    ],
                ],
            ]);
    }

    #[Test]
    public function cancelSucceeds(): void
    {
        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.subscriptions.cancel'),
                [
                    'subscriptions' => [
                        [
                            'type' => $this->subscription->product->productGroup->slug->value,
                            'uuid' => $this->subscription->uuid,
                            'cancel' => true,
                            'cancel_type' => SubscriptionCancelType::CANCEL_END_DATE,
                            'cancel_reason' => SubscriptionCancelReason::REASON_CANCELLATION,
                        ],
                    ],
                ],
            )
            ->assertOk()
            ->assertJsonFragment([
                'administrative_status' => AdministrativeStatus::CANCELED->value,
                'customer_id' => $this->customer->id,
                'domain' => $this->subscription->domain,
                'product_name' => $this->product->name,
                'product_slug' => $this->product->slug,
                'type' => $this->subscription->product->productGroup->slug->value,
                'uuid' => $this->subscription->uuid,
            ]);
    }

    #[Test]
    public function incorrectCancelTypeInRequest(): void
    {
        $translator = self::resolve(Translator::class);

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.subscriptions.cancel'),
                [
                    'subscriptions' => [
                        [
                            'type' => $this->subscription->product->productGroup->slug->value,
                            'uuid' => $this->subscription->uuid,
                            'cancel' => true,
                            'cancel_type' => 'incorrect-cancel-type',
                            'cancel_reason' => SubscriptionCancelReason::REASON_CANCELLATION,
                        ],
                    ],
                ],
            )
            ->assertUnprocessable()
            ->assertJsonFragment([
                'subscriptions.0.cancel_type' => [
                    $translator->translate('validation.enum', ['attribute' => 'subscriptions.0.cancel_type']),
                    'subscriptions.0.cancel_type should be a valid SubscriptionChangeType',
                ],
            ]);
    }

    #[Test]
    public function wrongSubscriptionSupplied(): void
    {
        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.subscriptions.cancel'),
                [
                    'subscriptions' => [
                        [
                            'type' => 'extension',
                            'uuid' => 'wrong-uuid',
                            'cancel' => true,
                            'cancel_type' => SubscriptionCancelType::CANCEL_END_DATE->value,
                        ],
                    ],
                ],
            )
            ->assertUnprocessable();
    }

    #[Test]
    public function getAllEligibleSubscriptionsForServicePlus(): void
    {
        $group = new ProductGroupFactory()->hosting()->createOne();
        $product = new ProductFactory()->for($group)->createOne(['name' => 'nee']);
        new ProductPriceComponentFactory()
            ->for($product)
            ->registration()
            ->createOne();
        new ProductPriceComponentFactory()
            ->for($product)
            ->prolongation()
            ->createOne();

        $noServicePlusSub = new SubscriptionFactory()
            ->for($this->customer)
            ->for($product)
            ->createOne(['domain' => 'geenserviceplus']);
        $parent = new SubscriptionFactory()
            ->for($this->customer)
            ->for($product)
            ->createOne(['domain' => 'geenservicesplusmaarchildwel']);

        $productWithServicePlus = new ProductFactory()->for($group)->createOne(['name' => 'groot']);
        ProductSpecFactory::new()->for($productWithServicePlus)->createOne([
            'name' => ProductSpecName::HAS_SERVICE_PLUS,
            'value' => 'true',
        ]);
        new ProductPriceComponentFactory()
            ->for($productWithServicePlus)
            ->registration()
            ->createOne();
        new ProductPriceComponentFactory()
            ->for($productWithServicePlus)
            ->prolongation()
            ->createOne();
        new SubscriptionFactory()
            ->for($this->customer)
            ->for($productWithServicePlus)
            ->createOne(['domain' => 'hallo']);
        new SubscriptionFactory()
            ->for($this->customer)
            ->for($productWithServicePlus)
            ->createOne(['domain' => 'child', 'parent_subscription_id' => $parent->id]);

        $this->actingAsCustomer($this->customer)
            ->getJson($this->generateRoute('partners.subscriptions.service-plus.eligible'))
            ->assertOk()
            ->assertJsonFragment(['domain' => $noServicePlusSub->domain]);
    }
}
