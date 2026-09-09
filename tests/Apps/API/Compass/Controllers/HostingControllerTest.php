<?php

declare(strict_types=1);

namespace Tests\Apps\API\Compass\Controllers;

use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Controllers\HostingController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Hosting\Enums\HostingRetryType;
use Waterfront\Domain\Hosting\Events\CreateHosting;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(HostingController::class)]
class HostingControllerTest extends IntegrationTestCase
{
    public const string DOMAIN = 'retry-hosting.nl';

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();
    }

    #[Test]
    public function retryHostingDispatchesCreateHostingForSelectedServer(): void
    {
        Event::fake([CreateHosting::class]);

        $subscription = $this->makeSubscription(new ProductFactory()->hostingBrons()->createOne());
        $server = new ServerFactory()->createOne();

        $response = $this->actingAsEmployee()
            ->postJson(
                $this->generateRoute('admin.hosting.retry.hosting', ['subscription' => $subscription->uuid]),
                ['hosting_type' => HostingRetryType::BASIC->value, 'server_id' => $server->id]
            )
            ->assertOk();

        self::assertSame(
            self::resolve(TranslatorInterface::class)->translate('action.retry-hosting.retried-successfully'),
            $response->json('message')
        );

        Event::assertDispatched(
            CreateHosting::class,
            fn (CreateHosting $event): bool => $event->subscriptionUuid === $subscription->uuid
                && $event->serverId === $server->id
        );
    }

    #[Test]
    public function retryHostingWithoutServerIdIsAccepted(): void
    {
        Event::fake([CreateHosting::class]);

        $subscription = $this->makeSubscription(new ProductFactory()->hostingBrons()->createOne());

        $this->actingAsEmployee()
            ->postJson(
                $this->generateRoute('admin.hosting.retry.hosting', ['subscription' => $subscription->uuid]),
                ['hosting_type' => HostingRetryType::BASIC->value]
            )
            ->assertOk();

        Event::assertDispatched(
            CreateHosting::class,
            fn (CreateHosting $event): bool => $event->serverId === null
        );
    }

    #[Test]
    public function retryHostingReturnsUnprocessableForSubscriptionOutsideHostingProductGroups(): void
    {
        Event::fake([CreateHosting::class]);

        $subscription = $this->makeSubscription(new ProductFactory()->sslSingleDomain()->createOne());

        $response = $this->actingAsEmployee()
            ->postJson(
                $this->generateRoute('admin.hosting.retry.hosting', ['subscription' => $subscription->uuid]),
                ['hosting_type' => HostingRetryType::BASIC->value]
            )
            ->assertUnprocessable();

        self::assertSame(
            self::resolve(TranslatorInterface::class)->translate('action.retry-hosting.subscription-invalid-for-retry'),
            $response->json('message')
        );

        Event::assertNotDispatched(CreateHosting::class);
    }

    #[Test]
    public function retryHostingRejectsUnknownHostingType(): void
    {
        Event::fake([CreateHosting::class]);

        $subscription = $this->makeSubscription(new ProductFactory()->hostingBrons()->createOne());

        $this->actingAsEmployee()
            ->postJson(
                $this->generateRoute('admin.hosting.retry.hosting', ['subscription' => $subscription->uuid]),
                ['hosting_type' => 'not-a-hosting-type']
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('hosting_type');

        Event::assertNotDispatched(CreateHosting::class);
    }

    #[Test]
    public function retryHostingRejectsUnknownServer(): void
    {
        Event::fake([CreateHosting::class]);

        $subscription = $this->makeSubscription(new ProductFactory()->hostingBrons()->createOne());

        $this->actingAsEmployee()
            ->postJson(
                $this->generateRoute('admin.hosting.retry.hosting', ['subscription' => $subscription->uuid]),
                ['hosting_type' => HostingRetryType::BASIC->value, 'server_id' => 999999]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('server_id');

        Event::assertNotDispatched(CreateHosting::class);
    }

    private function makeSubscription(Product $product): Subscription
    {
        return new SubscriptionFactory()
            ->for($this->customer)
            ->for($product)
            ->technicalStatus(TechnicalStatus::FAILED->value)
            ->createOne(['domain' => self::DOMAIN]);
    }
}
