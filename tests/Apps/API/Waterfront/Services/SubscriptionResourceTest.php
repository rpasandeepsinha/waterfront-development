<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\DataProvider\DomainSubscriptionDataProvider;
use Tests\DataProvider\HostingSubscriptionDataProvider;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SslDeploymentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Actions\GetNextInvoicePriceAction;
use Waterfront\Domain\Subscriptions\DTO\NextInvoicePriceDTO;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\SubscriptionResource;

#[CoversNothing]
class SubscriptionResourceTest extends IntegrationTestCase
{
    private Customer $customer;

    private Subscription $extensionSubscription;

    public function setUp(): void
    {
        parent::setUp();

        $this->extensionSubscription = DomainSubscriptionDataProvider::subscription();
        $this->customer = $this->extensionSubscription->customer;

        $nextInvoicePriceMock = self::createStub(GetNextInvoicePriceAction::class);
        $nextInvoicePriceMock
            ->method('execute')
            ->willReturn(
                new NextInvoicePriceDTO(
                    $this->extensionSubscription->product,
                    0,
                    0,
                    0,
                    null,
                    CarbonImmutable::now(),
                    CarbonImmutable::now(),
                ),
            );

        $this->app->bind(GetNextInvoicePriceAction::class, fn () => $nextInvoicePriceMock);
    }

    #[Test]
    public function subscriptionResource(): void
    {
        DomainSubscriptionDataProvider::deployment($this->extensionSubscription);
        $resource = SubscriptionResource::make($this->extensionSubscription);
        $request = Request::create($this->generateRoute('partners.subscriptions.show', [
            'subscription' => $this->extensionSubscription,
        ]));

        $data = $resource->toArray($request);
        self::assertArrayHasKey('id', $data);
        self::assertSame($data['id'], $this->extensionSubscription->id);

        self::assertArrayHasKey('uuid', $data);
        self::assertSame($data['uuid'], $this->extensionSubscription->uuid);

        self::assertArrayHasKey('customer_id', $data);

        self::assertArrayHasKey('start_date', $data);
        self::assertSame($data['start_date'], $this->extensionSubscription->start_date->toW3cString());

        self::assertArrayHasKey('end_date', $data);
        self::assertSame($data['end_date'], $this->extensionSubscription->end_date->toW3cString());

        self::assertArrayHasKey('contract_period', $data);
        self::assertSame($data['contract_period'], $this->extensionSubscription->contract_period);

        self::assertArrayHasKey('billing_period', $data);
        self::assertSame($data['billing_period'], $this->extensionSubscription->billing_period);

        self::assertArrayHasKey('domain', $data);
        self::assertSame($data['domain'], $this->extensionSubscription->domain);

        self::assertArrayHasKey('in_transfer', $data);
        self::assertIsBool($data['in_transfer']);

        self::assertArrayHasKey('type', $data);
        self::assertSame($data['type'], $this->extensionSubscription->product->productGroup->slug);

        self::assertArrayHasKey('product_name', $data);
        self::assertSame($data['product_name'], $this->extensionSubscription->product->name);

        self::assertArrayHasKey('product_slug', $data);
        self::assertSame($data['product_slug'], $this->extensionSubscription->product->slug);

        self::assertArrayHasKey('available_actions', $data);
        self::assertIsArray($data['available_actions']);

        self::assertArrayHasKey('has_hosting', $data);
        self::assertFalse($data['has_hosting']);

        self::assertArrayHasKey('service_provider', $data);
        self::assertSame('openprovider', $data['service_provider']);

        self::assertArrayHasKey('parent_subscription_id', $data);
        self::assertNull($data['parent_subscription_id']);
    }

    #[Test]
    public function subscriptionResourcePlaceholderWithZone(): void
    {
        $mockDnsService = $this->mock(DnsService::class);
        $mockDnsService->expects('hasDnsZone')->with($this->extensionSubscription->domain)->andReturns(true);

        $this->app->bind(DnsService::class, fn () => $mockDnsService);

        new DomainDeploymentFactory()
            ->withPlaceholderProvider()
            ->for($this->extensionSubscription, 'subscription')
            ->createOne();

        $this->extensionSubscription->fresh();

        $resource = SubscriptionResource::make($this->extensionSubscription);
        $request = Request::create(
            $this->generateRoute('partners.subscriptions.show', ['subscription' => $this->extensionSubscription]),
        );
        $data = $resource->toArray($request);

        self::assertTrue($data['has_dns_zone']);
        self::assertSame($data['domain'], $this->extensionSubscription->domain);
    }

    #[Test]
    public function subscriptionResourcePlaceholderWithoutZone(): void
    {
        $mockDnsService = $this->mock(DnsService::class);
        $mockDnsService->expects('hasDnsZone')->with($this->extensionSubscription->domain)->andReturns(false);

        $this->app->bind(DnsService::class, fn () => $mockDnsService);

        new DomainDeploymentFactory()
            ->withPlaceholderProvider()
            ->for($this->extensionSubscription, 'subscription')
            ->createOne();

        $this->extensionSubscription->fresh();

        $resource = SubscriptionResource::make($this->extensionSubscription);
        $request = Request::create(
            $this->generateRoute('partners.subscriptions.show', ['subscription' => $this->extensionSubscription]),
        );
        $data = $resource->toArray($request);

        self::assertFalse($data['has_dns_zone']);
        self::assertSame($data['domain'], $this->extensionSubscription->domain);
    }

    #[Test]
    public function subscriptionResourceNoKeyWithoutPlaceholderProvider(): void
    {
        $mockDnsService = self::createStub(DnsService::class);
        $this->app->bind(DnsService::class, fn () => $mockDnsService);

        DomainSubscriptionDataProvider::deployment($this->extensionSubscription);

        $resource = SubscriptionResource::make($this->extensionSubscription);
        $request = Request::create(
            $this->generateRoute('partners.subscriptions.show', ['subscription' => $this->extensionSubscription]),
        );
        $data = $resource->toArray($request);

        self::assertArrayNotHasKey('has_dns_zone', $data);
        self::assertSame($data['domain'], $this->extensionSubscription->domain);
    }

    #[DataProvider('statusProvider')]
    #[Test]
    public function subscriptionResourceActiveStatus(
        string $adminstrativeStatus,
        string $technicalStatus,
        string $expected,
    ): void {
        $productGroup = new ProductGroupFactory()->manualSubscription()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();

        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($product)
            ->administrativeStatus($adminstrativeStatus)
            ->technicalStatus($technicalStatus)
            ->createOne();

        $resource = SubscriptionResource::make($subscription);
        $request = Request::create($this->generateRoute('partners.subscriptions.show', [
            'subscription' => $subscription,
        ]));

        $data = $resource->toArray($request);

        self::assertSame($data['active_status'], $expected);
    }

    #[Test]
    public function resourceHostingSubscription(): void
    {
        $subscription = HostingSubscriptionDataProvider::administrativeSubscription();
        $techSubscription = HostingSubscriptionDataProvider::technicalSubscription($subscription);

        $resource = SubscriptionResource::make($subscription);
        $request = Request::create($this->generateRoute('partners.subscriptions.show', [
            'subscription' => $subscription,
        ]));

        $data = $resource->toArray($request);

        self::assertArrayHasKey('has_hosting', $data);
        self::assertTrue($data['has_hosting']);

        self::assertArrayHasKey('service_provider', $data);
        self::assertSame($data['service_provider'], $techSubscription->provider?->slug->value);

        self::assertArrayHasKey('server_name', $data);
        self::assertArrayHasKey('username', $data);
        self::assertArrayHasKey('ftps_host', $data);
    }

    #[Test]
    public function parentSubscriptionIdInResource(): void
    {
        $addonGroup = new ProductGroupFactory()->addon()->createOne();

        $parentHostingSubscription = HostingSubscriptionDataProvider::administrativeSubscription();

        $hostingAddon = new ProductFactory()->for($addonGroup)->createOne();

        $addonSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($hostingAddon)
            ->for($parentHostingSubscription, 'parent')
            ->administrativeStatusActive()
            ->createOne();

        $resource = SubscriptionResource::make($addonSubscription);
        $request = Request::create(
            $this->generateRoute('partners.subscriptions.show', ['subscription' => $addonSubscription]),
        );

        $data = $resource->toArray($request);

        self::assertArrayHasKey('children', $data);
        self::assertEmpty($data['children']);
        self::assertSame($parentHostingSubscription->id, $data['parent_subscription_id']);
    }

    #[Test]
    public function childrenSubscriptionInResource(): void
    {
        $addonGroup = new ProductGroupFactory()->addon()->createOne();
        $parentHostingSubscription = HostingSubscriptionDataProvider::administrativeSubscription();

        $hostingAddons = new ProductFactory()->for($addonGroup)->createMany(5);

        $hostingAddons->each(function (Product $hostingAddon) use ($parentHostingSubscription): void {
            new SubscriptionFactory()
                ->for($this->customer)
                ->for($hostingAddon)
                ->for($parentHostingSubscription, 'parent')
                ->administrativeStatusActive()
                ->createOne();
        });

        new SubscriptionFactory()
            ->for($this->customer)
            ->for(new ProductFactory()->for($addonGroup))
            ->for($parentHostingSubscription, 'parent')
            ->administrativeStatusInactive()
            ->createOne();

        $resource = SubscriptionResource::make($parentHostingSubscription);
        $request = Request::create(
            $this->generateRoute('partners.subscriptions.show', ['subscription' => $parentHostingSubscription]),
        );

        $data = $resource->toArray($request);

        self::assertArrayHasKey('children', $data);
        self::assertInstanceOf(Collection::class, $data['children']);
        self::assertCount(6, $data['children']); // 5 active, 1 inactive

        foreach ($data['children'] as $child) {
            self::assertInstanceOf(Subscription::class, $child);
            self::assertCount(2, $child->attributesToArray());

            self::assertArrayHasKey('id', $child->attributesToArray());
            self::assertArrayHasKey('product_uuid', $child->attributesToArray());
        }
    }

    #[Test]
    public function deletedChildrenSubscriptionNotInResource(): void
    {
        $addonGroup = new ProductGroupFactory()->addon()->createOne();
        $parentHostingSubscription = HostingSubscriptionDataProvider::administrativeSubscription();

        $hostingAddons = new ProductFactory()->for($addonGroup)->createMany(5);

        $hostingAddons->each(function (Product $hostingAddon) use ($parentHostingSubscription): void {
            new SubscriptionFactory()
                ->for($this->customer)
                ->for($hostingAddon)
                ->for($parentHostingSubscription, 'parent')
                ->administrativeStatusActive()
                ->createOne();
        });

        new SubscriptionFactory()
            ->for($this->customer)
            ->for(new ProductFactory()->for($addonGroup))
            ->for($parentHostingSubscription, 'parent')
            ->administrativeStatusArchived()
            ->createOne();

        $resource = SubscriptionResource::make($parentHostingSubscription);
        $request = Request::create(
            $this->generateRoute('partners.subscriptions.show', ['subscription' => $parentHostingSubscription]),
        );

        $data = $resource->toArray($request);

        self::assertArrayHasKey('children', $data);
        self::assertInstanceOf(Collection::class, $data['children']);
        self::assertCount(5, $data['children']); // 5 active, 1 deleted

        foreach ($data['children'] as $child) {
            self::assertInstanceOf(Subscription::class, $child);
            self::assertCount(2, $child->attributesToArray());

            self::assertArrayHasKey('id', $child->attributesToArray());
            self::assertArrayHasKey('product_uuid', $child->attributesToArray());
        }
    }

    #[Test]
    public function resourceDomainSubscription(): void
    {
        DomainSubscriptionDataProvider::deployment($this->extensionSubscription);
        $resource = SubscriptionResource::make($this->extensionSubscription);
        $request = Request::create($this->generateRoute('partners.subscriptions.show', [
            'subscription' => $this->extensionSubscription,
        ]));

        $data = $resource->toArray($request);

        self::assertArrayHasKey('has_hosting', $data);
        self::assertFalse($data['has_hosting']);

        self::assertArrayHasKey('service_provider', $data);

        self::assertSame(
            $data['service_provider'],
            $this->extensionSubscription->domainDeployment?->provider->slug->value,
        );
    }

    #[Test]
    public function resourceSslDeployment(): void
    {
        $productGroup = new ProductGroupFactory()->ssl()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();
        $provider = ProviderFactory::new()->createOne([
            'type' => ProviderType::SSL,
            'slug' => ProviderSlug::OPEN_PROVIDER,
            'enabled' => true,
            'default' => true,
        ]);

        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($product)
            ->createOne();

        new SslDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'provider_id' => $provider->id,
        ]);

        $resource = SubscriptionResource::make($subscription);
        $request = Request::create($this->generateRoute('partners.subscriptions.show', [
            'subscription' => $subscription,
        ]));

        $data = $resource->toArray($request);

        self::assertArrayHasKey('has_hosting', $data);
        self::assertFalse($data['has_hosting']);

        self::assertArrayHasKey('service_provider', $data);
        self::assertSame($data['service_provider'], $provider->slug->value);

        self::assertIsArray($data['certificates']);
        self::assertIsBool($data['has_custom_csr']);
        self::assertIsBool($data['has_reissued']);
        self::assertIsString($data['last_status']);
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function statusProvider(): array
    {
        return [
            [
                AdministrativeStatus::ACTIVE->value,
                TechnicalStatus::PENDING->value,
                'processing',
            ],
            [
                AdministrativeStatus::ACTIVE->value,
                TechnicalStatus::FAILED->value,
                'registration_failed',
            ],
            [
                AdministrativeStatus::ACTIVE->value,
                TechnicalStatus::OK->value,
                'active',
            ],
            [
                AdministrativeStatus::ACTIVE->value,
                DomainStatus::ACTIVE->value,
                'active',
            ],
            [
                AdministrativeStatus::CANCELED->value,
                TechnicalStatus::OK->value,
                'canceled',
            ],
            [
                AdministrativeStatus::CANCELED->value,
                DomainStatus::ACTIVE->value,
                'canceled',
            ],
        ];
    }
}
