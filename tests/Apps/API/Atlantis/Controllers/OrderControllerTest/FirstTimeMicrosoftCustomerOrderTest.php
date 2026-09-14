<?php

declare(strict_types=1);

namespace Tests\Apps\API\Atlantis\Controllers\OrderControllerTest;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use SandwaveIo\Office365\Entity\CloudLicense;
use SandwaveIo\Office365\Entity\Customer as KpnCustomer;
use SandwaveIo\Office365\Entity\TenantOrder;
use SandwaveIo\Office365\Helper\EntityHelper;
use SandwaveIo\Office365\Library\Observer\Status\Status;
use Tests\Factories\CustomerFactory;
use Tests\Factories\Microsoft365KpnProductFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProviderFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\OrderController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\DNS\Events\CreateDns;
use Waterfront\Domain\Domains\DTO\CheckResult;
use Waterfront\Domain\Domains\Events\CreateDomain;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365OrderStatus;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365ProcessStatus;
use Waterfront\Domain\Microsoft365\EventListener\CloudLicenseListener;
use Waterfront\Domain\Microsoft365\EventListener\CustomerCreateListener;
use Waterfront\Domain\Microsoft365\EventListener\TenantCreateListener;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;
use Waterfront\Domain\Microsoft365\Repositories\Microsoft365CustomerInfoRepository;
use Waterfront\Domain\Microsoft365\Services\Microsoft365Service;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\RtrClient\Services\RtrService;

#[CoversClass(OrderController::class)]
class FirstTimeMicrosoftCustomerOrderTest extends IntegrationTestCase
{
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading(false);

        $this->customer = new CustomerFactory()->withAddress()->createOne();

        ProviderFactory::new()->createOne([
            'type' => ProviderType::DOMAIN,
            'slug' => ProviderSlug::REALTIME_REGISTER,
            'enabled' => true,
            'default' => true,
        ]);

        Event::fake(
            [
                CreateDns::class,
                CreateDomain::class,
            ],
        );

        $nlProduct = new ProductFactory()->nlDomain()->createOne();
        new ProductPriceComponentFactory()
            ->for($nlProduct)
            ->registration()
            ->createOne([
                'billing_period' => 12,
                'contract_period' => 12,
                'price' => 99,
            ]);

        $dnsProduct = new ProductFactory()->freeDns()->createOne();
        new ProductPriceComponentFactory()
            ->for($dnsProduct)
            ->registration()
            ->createOne([
                'billing_period' => 12,
                'contract_period' => 12,
                'price' => 0,
            ]);

        $productGroup = new ProductGroupFactory()->microsoft365()->createOne();
        $parentProduct = new ProductFactory()->for($productGroup)->createOne([
            'slug' => 'microsoft-business-standard-parent',
            'name' => 'Business Standard parent',
        ]);

        new ProductPriceComponentFactory()
            ->for($parentProduct)
            ->registration()
            ->createOne([
                'billing_period' => 1,
                'contract_period' => 1,
            ]);

        new Microsoft365KpnProductFactory()->for($parentProduct)->createOne([
            'kpn_product_code' => 'ABC',
        ]);

        $childProduct = new ProductFactory()->for($productGroup)->createOne([
            'slug' => 'microsoft-business-standard',
            'name' => 'Business Standard',
        ]);

        new ProductPriceComponentFactory()
            ->for($childProduct)
            ->registration()
            ->createOne([
                'billing_period' => 1,
                'contract_period' => 1,
                'price' => 12,
            ]);
    }

    #[Test]
    public function firstTimeCustomerOrderWithDomain(): void
    {
        $rtrMock = self::mock(RtrService::class);
        $rtrMock
            ->shouldReceive('check')
            ->with('domain-order-test.nl')
            ->andReturn(new CheckResult('domain-order-test.nl', 'free'));

        $rtrMock->shouldReceive('setHandle')->andReturnSelf();
        $rtrMock->shouldReceive('setClient')->andReturnSelf();

        $this->app->bind(RtrService::class, fn () => $rtrMock);

        $mockMicrosoftModuleMicrosoftService = $this->createMock(Microsoft365Service::class);
        $mockMicrosoftModuleMicrosoftService->expects(self::once())->method('createKpnCustomer')->willReturn(true);
        $this->app->bind(Microsoft365Service::class, fn () => $mockMicrosoftModuleMicrosoftService);

        $mockMailer = $this->createMock(MailerInterface::class);
        $mockMailer->expects(self::exactly(2))->method('send');
        $this->app->bind(MailerInterface::class, fn () => $mockMailer);

        $json = (string) file_get_contents(__DIR__ . '/data/order_payload_microsoft365_domain.json');

        /** @var array<int, array<string>> $orderPayload */
        $orderPayload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->actingAsCustomer($this->customer)
            ->postJson($this->generateRoute('partners.order.order'), $orderPayload)
            ->assertOk();

        // Customer info is connected to a Waterfront customer and contains external references like the tenant name and KPN customer id.
        $microsoft365CustomerInfo = Microsoft365CustomerInfo::where('customer_id', $this->customer->id)->firstOrFail();
        self::assertSame(Microsoft365ProcessStatus::INITIATED, $microsoft365CustomerInfo->technical_status);

        // If this is the first time a customer places an Microsoft order the system will send a customer creation request to KPN.
        // The response will land in the webhook. We mock the KPN response down below.
        $this->triggerKPNCustomerCreationResponseEvent($this->customer->id, $microsoft365CustomerInfo->id);
        $microsoft365CustomerInfo->refresh();
        self::assertSame(Microsoft365ProcessStatus::CUSTOMER_CREATED, $microsoft365CustomerInfo->technical_status);

        $subscription = Subscription::query()
            ->where('customer_id', $this->customer->id)
            ->whereNull('parent_subscription_id')
            ->whereProductGroupType(ProductGroupType::MICROSOFT_365)
            ->whereProductSlug('microsoft-business-standard-parent')
            ->firstOrFail();

        $microsoft365Deployment = Microsoft365Deployment::where('subscription_id', $subscription->id)->firstOrFail();
        $this->triggerKpnTenantCreatedResponseEvent($microsoft365Deployment->id);
        $this->triggerKPNOrderAcceptedResponseEvent($microsoft365Deployment->id);
        $this->triggerKPNOrderCreatedResponseEvent();

        // All processes are now completed. Let's see if everything is set up correctly.

        $microsoft365CustomerInfo->refresh();
        self::assertSame(Microsoft365ProcessStatus::ACTIVE, $microsoft365CustomerInfo->technical_status);
        self::assertNotNull($microsoft365CustomerInfo->kpn_customer_id);

        $microsoft365Deployment->refresh();
        self::assertNotNull($microsoft365Deployment->kpn_order_id);

        $subscription = Subscription::where('id', $microsoft365Deployment->subscription_id)->firstOrFail();
        self::assertSame(TechnicalStatus::OK->value, $subscription->technical_status);
        self::assertCount(2, $subscription->children);

        $subscription->children->each(function (Subscription $seatSubscription): void {
            self::assertSame(TechnicalStatus::OK->value, $seatSubscription->technical_status);
        });
    }

    #[Test]
    public function firstTimeCustomerOrder(): void
    {
        $mockMicrosoftModuleMicrosoftService = $this->createMock(Microsoft365Service::class);
        $mockMicrosoftModuleMicrosoftService->expects(self::once())->method('createKpnCustomer')->willReturn(true);
        $this->app->bind(Microsoft365Service::class, fn () => $mockMicrosoftModuleMicrosoftService);

        $mockMailer = $this->createMock(MailerInterface::class);
        $mockMailer->expects(self::exactly(2))->method('send');
        $this->app->bind(MailerInterface::class, fn () => $mockMailer);

        $json = (string) file_get_contents(__DIR__ . '/data/order_payload_microsoft365.json');

        /** @var array<int, array<string>> $orderPayload */
        $orderPayload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->actingAsCustomer($this->customer)
            ->postJson($this->generateRoute('partners.order.order'), $orderPayload)
            ->assertOk();

        // Customer info is connected to a Waterfront customer and contains external references like the tenant name and KPN customer id.
        $microsoft365CustomerInfo = Microsoft365CustomerInfo::where('customer_id', $this->customer->id)->firstOrFail();
        self::assertSame(Microsoft365ProcessStatus::INITIATED, $microsoft365CustomerInfo->technical_status);

        // If this is the first time a customer places an Microsoft order the system will send a customer creation request to KPN.
        // The response will land in the webhook. We mock the KPN response down below.
        $this->triggerKPNCustomerCreationResponseEvent($this->customer->id, $microsoft365CustomerInfo->id);
        $microsoft365CustomerInfo->refresh();
        self::assertSame(Microsoft365ProcessStatus::CUSTOMER_CREATED, $microsoft365CustomerInfo->technical_status);

        $subscription = Subscription::where('customer_id', $this->customer->id)
            ->whereNull('parent_subscription_id')
            ->firstOrFail();
        $microsoft365Deployment = Microsoft365Deployment::where('subscription_id', $subscription->id)->firstOrFail();
        $this->triggerKpnTenantCreatedResponseEvent($microsoft365Deployment->id);
        $this->triggerKPNOrderAcceptedResponseEvent($microsoft365Deployment->id);
        $this->triggerKPNOrderCreatedResponseEvent();

        // All processes are now completed. Let's see if everything is set up correctly.

        $microsoft365CustomerInfo->refresh();
        self::assertSame(Microsoft365ProcessStatus::ACTIVE, $microsoft365CustomerInfo->technical_status);
        self::assertNotNull($microsoft365CustomerInfo->kpn_customer_id);

        $microsoft365Deployment->refresh();
        self::assertNotNull($microsoft365Deployment->kpn_order_id);

        $subscription = Subscription::where('id', $microsoft365Deployment->subscription_id)->firstOrFail();
        self::assertSame(TechnicalStatus::OK->value, $subscription->technical_status);
        self::assertCount(2, $subscription->children);

        $subscription->children->each(function (Subscription $seatSubscription): void {
            self::assertSame(TechnicalStatus::OK->value, $seatSubscription->technical_status);
        });
    }

    private function triggerKPNCustomerCreationResponseEvent(
        int $waterfrontCustomerId,
        int $microsoft365CustomerInfoId,
    ): void {
        $kpnCustomer = EntityHelper::deserializeArray(KpnCustomer::class, [
            'Name' => 'John Doe',
            'Street' => 'Javalaan',
            'HouseNr' => 14,
            'ZipCode' => '8712AK',
            'CountryCode' => 'NLD',
            'Phone1' => '0693827493',
            'Email' => 'john@example.org',
            'LegalStatus' => 'Onbekend',
            'Header' => [
                'PartnerReference' => 'WF-CUSTOMER-' . $waterfrontCustomerId . '-' . $microsoft365CustomerInfoId,
            ],
        ]);

        self::assertInstanceOf(KpnCustomer::class, $kpnCustomer);

        self::resolve(CustomerCreateListener::class)->execute($kpnCustomer, null);
    }

    private function triggerKpnTenantCreatedResponseEvent(int $microsoft365DeploymentId): void
    {
        $tenantOrder = EntityHelper::deserializeArray(TenantOrder::class, [
            'CustomerId' => '123',
            'OrderId' => 22,
            'ProductCode' => '282A00001B',
            'TenantName' => 'test.onmicrosoft.com',
            'FirstName' => 'John',
            'LastName' => 'Doe',
            'Header' => [
                'PartnerReference' => sprintf('WF-ORDER-123-%d', $microsoft365DeploymentId),
                'DateCreated' => '2023-01-01T00:00:00',
            ],
        ]);

        self::assertInstanceOf(TenantOrder::class, $tenantOrder);

        $status = new Status(Microsoft365OrderStatus::ACCEPTED->value, []);

        new TenantCreateListener(
            microsoft365CustomerInfoRepository: self::resolve(Microsoft365CustomerInfoRepository::class),
            microsoft365Service: self::createStub(Microsoft365Service::class),
            logger: self::createStub(LoggerInterface::class),
        )->execute($tenantOrder, $status);
    }

    private function triggerKPNOrderAcceptedResponseEvent(int $microsoft365DeploymentId): void
    {
        $cloudLicense = EntityHelper::deserializeArray(CloudLicense::class, [
            'CustomerId' => '123',
            'Quantity' => 2,
            'OrderId' => 16,
            'Header' => [
                'PartnerReference' => sprintf('WF-ORDER-123-%d', $microsoft365DeploymentId),
                'DateCreated' => '2023-01-01T00:00:00',
            ],
        ]);

        self::assertInstanceOf(CloudLicense::class, $cloudLicense);

        $status = new Status(Microsoft365OrderStatus::ACCEPTED->value, []);

        new CloudLicenseListener(self::createStub(Microsoft365Service::class))->execute($cloudLicense, $status);
    }

    private function triggerKPNOrderCreatedResponseEvent(): void
    {
        $cloudLicense = EntityHelper::deserializeArray(CloudLicense::class, [
            'CustomerId' => '123',
            'Quantity' => 2,
            'OrderId' => 16,
            'Header' => [
                'DateCreated' => '2023-01-01T00:00:00',
            ],
        ]);

        self::assertInstanceOf(CloudLicense::class, $cloudLicense);

        $status = new Status(Microsoft365OrderStatus::ACTIVE->value, []);

        new CloudLicenseListener(self::createStub(Microsoft365Service::class))->execute($cloudLicense, $status);
    }
}
