<?php

declare(strict_types=1);

namespace Tests\Domain\Microsoft365\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\Microsoft365CustomerInfoFactory;
use Tests\Factories\Microsoft365DeploymentFactory;
use Tests\Factories\Microsoft365KpnProductFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Invoices\Repositories\InvoiceRepository;
use Waterfront\Domain\Microsoft365\Enums\CustomerInfoType;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365OrderStatus;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365ProcessStatus;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;
use Waterfront\Domain\Microsoft365\Repositories\Microsoft365KpnProductRepository;
use Waterfront\Domain\Microsoft365\Services\Microsoft365Service;
use Waterfront\Domain\Microsoft365\Services\Microsoft365SubscriptionService;
use Waterfront\Domain\Microsoft365\Services\Microsoft365TenantService;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\SubscriptionService;

#[CoversClass(Microsoft365SubscriptionService::class)]
class Microsoft365ServiceCustomerSharedTest extends IntegrationTestCase
{
    private Subscription $orderedSeatSubscription;

    private Customer $customer;

    private ProductGroup $productGroup;

    private Product $parentProduct;

    private Microsoft365Service&MockObject $mockMicrosoft365ModuleMicrosoftService;

    private Microsoft365SubscriptionService $microsoft365Service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();
        $this->productGroup = new ProductGroupFactory()->microsoft365()->createOne();
        $this->parentProduct = new ProductFactory()->for($this->productGroup)->createOne([
            'slug' => 'microsoft-business-standard-parent',
        ]);
        $childProduct = new ProductFactory()->for($this->productGroup)->createOne([
            'slug' => 'microsoft-business-standard',
        ]);
        $this->orderedSeatSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($childProduct)
            ->createOne([
                'net_price' => 100,
                'gross_price' => 100,
                'contract_period' => 1,
                'product_uuid' => $childProduct->uuid,
            ]);

        $this->mockMicrosoft365ModuleMicrosoftService = self::createMock(Microsoft365Service::class);
        $this->app->bind(Microsoft365Service::class, fn () => $this->mockMicrosoft365ModuleMicrosoftService);
        $this->microsoft365Service = new Microsoft365SubscriptionService(
            $this->mockMicrosoft365ModuleMicrosoftService,
            self::resolve(InvoiceRepository::class),
            self::createStub(Dispatcher::class),
            self::createStub(Microsoft365TenantService::class),
            self::resolve(Microsoft365KpnProductRepository::class),
            self::createStub(LoggerInterface::class),
            self::resolve(SubscriptionService::class),
        );
    }

    #[Test]
    public function firstTimeCustomer(): void
    {
        $this->mockMicrosoft365ModuleMicrosoftService->expects(self::once())->method('createKpnCustomer');
        $this->microsoft365Service->create(new Collection([$this->orderedSeatSubscription]), null, null);

        self::assertNotNull($this->orderedSeatSubscription->parent);
        $parentSubscription = $this->orderedSeatSubscription->parent;
        self::assertSame(
            $this->orderedSeatSubscription->start_date->toDateString(),
            $parentSubscription->start_date->toDateString(),
        );
        self::assertSame(
            $this->orderedSeatSubscription->end_date->toDateString(),
            $parentSubscription->end_date->toDateString(),
        );
        self::assertSame($this->orderedSeatSubscription->contract_period, $parentSubscription->contract_period);
        self::assertSame(AdministrativeStatus::ACTIVE->value, $parentSubscription->administrative_status);
        self::assertSame(TechnicalStatus::REGISTRATION->value, $parentSubscription->technical_status);
        self::assertSame(0, $parentSubscription->net_price);
        self::assertSame(0, $parentSubscription->gross_price);
        $microsoft365Deployment = $parentSubscription->microsoft365Deployment;
        self::assertInstanceOf(Microsoft365Deployment::class, $microsoft365Deployment);
        self::assertSame(Microsoft365OrderStatus::PLACED, $microsoft365Deployment->kpn_status);
        self::assertSame(CustomerInfoType::REGISTER, $microsoft365Deployment->microsoft365CustomerInfo->type);
    }

    #[Test]
    public function returningCustomerWithSeatForNewProduct(): void
    {
        new Microsoft365CustomerInfoFactory()->for($this->customer)->createOne();
        new Microsoft365KpnProductFactory()->for($this->parentProduct)->createOne(['contract_period' => 1]);

        $this->mockMicrosoft365ModuleMicrosoftService->expects(self::never())->method('createKpnCustomer');
        $this->mockMicrosoft365ModuleMicrosoftService->expects(self::once())->method('createOrder');
        $this->mockMicrosoft365ModuleMicrosoftService->expects(self::never())->method('modifyOrder');

        $this->microsoft365Service->create(new Collection([$this->orderedSeatSubscription]), null, null);
    }

    #[Test]
    public function returningCustomerWithSeatForExistingProduct(): void
    {
        $customerInfo = new Microsoft365CustomerInfoFactory()->for($this->customer)->createOne();

        $parentSubscription = new SubscriptionFactory()->for($this->customer)->createOne([
            'contract_period' => $this->orderedSeatSubscription->contract_period,
            'product_uuid' => $this->parentProduct->uuid,
        ]);

        new Microsoft365DeploymentFactory()
            ->for($customerInfo)
            ->for($parentSubscription)
            ->createOne([
                'kpn_order_id' => '123',
            ]);

        $this->mockMicrosoft365ModuleMicrosoftService->expects(self::never())->method('createKpnCustomer');
        $this->mockMicrosoft365ModuleMicrosoftService->expects(self::once())->method('modifyOrder');
        $this->mockMicrosoft365ModuleMicrosoftService->expects(self::never())->method('createOrder');

        $this->microsoft365Service->create(new Collection([$this->orderedSeatSubscription]), null, null);
    }

    #[Test]
    public function returningCustomerWithSeatForExistingAndNewProduct(): void
    {
        $customerInfo = new Microsoft365CustomerInfoFactory()->for($this->customer)->createOne();

        $parentSubscription = new SubscriptionFactory()->for($this->customer)->createOne([
            'contract_period' => $this->orderedSeatSubscription->contract_period,
            'product_uuid' => $this->parentProduct->uuid,
        ]);

        new Microsoft365DeploymentFactory()
            ->for($customerInfo)
            ->for($parentSubscription)
            ->createOne([
                'kpn_order_id' => '123',
            ]);

        $anotherParentProduct = new ProductFactory()->for($this->productGroup)->createOne([
            'slug' => 'microsoft-business-basic-parent',
        ]);

        new Microsoft365KpnProductFactory()->for($anotherParentProduct)->createOne(['contract_period' => 1]);

        $childProduct = new ProductFactory()->for($this->productGroup)->createOne([
            'slug' => 'microsoft-business-basic',
        ]);

        $anotherSeatSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($childProduct)
            ->createOne([
                'net_price' => 100,
                'gross_price' => 100,
                'contract_period' => 1,
                'product_uuid' => $childProduct->uuid,
            ]);

        $this->mockMicrosoft365ModuleMicrosoftService->expects(self::never())->method('createKpnCustomer');
        $this->mockMicrosoft365ModuleMicrosoftService->expects(self::once())->method('modifyOrder');
        $this->mockMicrosoft365ModuleMicrosoftService->expects(self::once())->method('createOrder');

        $this->microsoft365Service->create(
            new Collection([$this->orderedSeatSubscription, $anotherSeatSubscription]),
            null,
            null,
        );
    }

    #[Test]
    public function returningCustomerWithoutKpnCustomerNumberShouldFail(): void
    {
        new Microsoft365CustomerInfoFactory()->for($this->customer)->createOne([
            'technical_status' => Microsoft365ProcessStatus::INITIATED,
        ]);
        new Microsoft365KpnProductFactory()->for($this->parentProduct)->createOne(['contract_period' => 1]);

        $this->mockMicrosoft365ModuleMicrosoftService->expects(self::never())->method('createKpnCustomer');
        $this->mockMicrosoft365ModuleMicrosoftService->expects(self::never())->method('createOrder');
        $this->mockMicrosoft365ModuleMicrosoftService->expects(self::never())->method('modifyOrder');

        $this->microsoft365Service->create(new Collection([$this->orderedSeatSubscription]), null, null);
        self::assertSame(TechnicalStatus::FAILED->value, $this->orderedSeatSubscription->technical_status);
    }

    #[Test]
    public function reactivateCanceledParentSubscriptionIfOrderedAgain(): void
    {
        $customerInfo = new Microsoft365CustomerInfoFactory()->for($this->customer)->createOne();

        $parentSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->parentProduct)
            ->createOne([
                'contract_period' => $this->orderedSeatSubscription->contract_period,
                'administrative_status' => AdministrativeStatus::CANCELED->value,
                'cancel_date' => CarbonImmutable::now(),
            ]);

        new Microsoft365DeploymentFactory()
            ->for($parentSubscription)
            ->for($customerInfo)
            ->createOne([
                'kpn_order_id' => '123',
            ]);

        $this->orderedSeatSubscription->parent_subscription_id = $parentSubscription->id;
        $this->orderedSeatSubscription->administrative_status = AdministrativeStatus::CANCELED->value;
        $this->orderedSeatSubscription->cancel_date = CarbonImmutable::now();
        $this->orderedSeatSubscription->save();

        //The parent subscription and the seat is currently canceled, it will reactivate the parent subscription.
        $this->mockMicrosoft365ModuleMicrosoftService->expects(self::never())->method('createKpnCustomer');
        $this->mockMicrosoft365ModuleMicrosoftService->expects(self::once())->method('modifyOrder');
        $this->mockMicrosoft365ModuleMicrosoftService->expects(self::never())->method('createOrder');

        $this->microsoft365Service->create(new Collection([$this->orderedSeatSubscription]), null, null);

        //The parent subscription is now active again, it has done a modifyOrder with 1 seat.
        $parentSubscription->refresh();
        self::assertSame(AdministrativeStatus::ACTIVE->value, $parentSubscription->administrative_status);
        self::assertNull($parentSubscription->cancel_date);
        //It has only changed the parent subscription and did not touch the child subscription.
        $this->orderedSeatSubscription->refresh();
        self::assertSame(AdministrativeStatus::CANCELED->value, $this->orderedSeatSubscription->administrative_status);
    }

    #[Test]
    public function buyProductAgainAfterItWasDeleted3MonthsAgo(): void
    {
        $this->travelTo(CarbonImmutable::create(2023));

        $customerInfo = new Microsoft365CustomerInfoFactory()->for($this->customer)->createOne();

        $this->orderedSeatSubscription->administrative_status = AdministrativeStatus::ARCHIVED->value;
        $this->orderedSeatSubscription->cancel_date = CarbonImmutable::now();
        $this->orderedSeatSubscription->save();

        new Microsoft365KpnProductFactory()->for($this->parentProduct)->createOne(['contract_period' => 1]);

        new Microsoft365DeploymentFactory()
            ->for($this->orderedSeatSubscription)
            ->for($customerInfo)
            ->createOne([
                'kpn_order_id' => '1',
            ]);

        $this->travel(3)->months();

        $anotherParentProduct = new ProductFactory()->for($this->productGroup)->createOne([
            'slug' => 'microsoft-business-basic-parent',
        ]);

        new Microsoft365KpnProductFactory()->for($anotherParentProduct)->createOne(['contract_period' => 1]);

        $childProduct = new ProductFactory()->for($this->productGroup)->createOne([
            'slug' => 'microsoft-business-basic',
        ]);

        $childSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($childProduct)
            ->createOne([
                'net_price' => 100,
                'gross_price' => 100,
                'contract_period' => 1,
                'product_uuid' => $childProduct->uuid,
            ]);

        //The parent subscription and the seat is currently canceled, it will reactivate the parent subscription.
        $this->mockMicrosoft365ModuleMicrosoftService->expects(self::never())->method('createKpnCustomer');
        $this->mockMicrosoft365ModuleMicrosoftService->expects(self::never())->method('modifyOrder');
        $this->mockMicrosoft365ModuleMicrosoftService->expects(self::once())->method('createOrder');

        $this->microsoft365Service->create(new Collection([$childSubscription]), null, null);

        //The old subscription (who was canceled 3 months ago) remains deleted.
        $this->orderedSeatSubscription->refresh();
        self::assertSame(AdministrativeStatus::ARCHIVED->value, $this->orderedSeatSubscription->administrative_status);
        self::assertNotNull($this->orderedSeatSubscription->cancel_date);
        //It managed to create a new parent subscription which is active.
        $childSubscription->refresh();
        self::assertSame(AdministrativeStatus::ACTIVE->value, $childSubscription->administrative_status);
        self::assertNotNull($childSubscription->parent);
        $parentSubscription = $childSubscription->parent;
        self::assertSame(
            $childSubscription->start_date->toDateString(),
            $parentSubscription->start_date->toDateString(),
        );
        self::assertSame($childSubscription->end_date->toDateString(), $parentSubscription->end_date->toDateString());
        self::assertSame($childSubscription->contract_period, $parentSubscription->contract_period);
        self::assertSame(AdministrativeStatus::ACTIVE->value, $parentSubscription->administrative_status);
        self::assertSame(TechnicalStatus::REGISTRATION->value, $parentSubscription->technical_status);
        self::assertSame(0, $parentSubscription->net_price);
        self::assertSame(0, $parentSubscription->gross_price);
        $microsoft365Deployment = $parentSubscription->microsoft365Deployment;
        self::assertInstanceOf(Microsoft365Deployment::class, $microsoft365Deployment);
        self::assertSame(Microsoft365OrderStatus::PLACED, $microsoft365Deployment->kpn_status);
        self::assertSame(CustomerInfoType::REGISTER, $microsoft365Deployment->microsoft365CustomerInfo->type);
    }
}
