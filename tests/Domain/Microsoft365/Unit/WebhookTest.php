<?php

declare(strict_types=1);

namespace Tests\Domain\Microsoft365\Unit;

use Exception;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use SandwaveIo\Office365\Office\OfficeClient;
use Tests\Factories\CustomerFactory;
use Tests\Factories\Microsoft365CustomerInfoFactory;
use Tests\Factories\Microsoft365DeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Microsoft365\EventListener\CloudLicenseListener;
use Waterfront\Domain\Microsoft365\EventListener\CustomerCreateListener;
use Waterfront\Domain\Microsoft365\EventListener\OrderMessageListener;
use Waterfront\Domain\Microsoft365\EventListener\TenantCreateListener;
use Waterfront\Domain\Microsoft365\Http\Controllers\WebhookController;
use Waterfront\Domain\Microsoft365\Listeners\Microsoft365WebhookLogListener;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;
use Waterfront\Domain\Microsoft365\Models\Microsoft365HttpLog;
use Waterfront\Domain\Microsoft365\Services\Microsoft365Service;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(WebhookController::class)]
#[AllowMockObjectsWithoutExpectations]
class WebhookTest extends IntegrationTestCase
{
    private const int KPN_ORDER_ID = 10264351;

    private Microsoft365CustomerInfo $microsoft365CustomerInfo;

    private Subscription $parentSubscription;

    private Subscription $firstChildSubscription;

    private Microsoft365Deployment $microsoft365Deployment;

    private LoggerInterface&MockObject $loggerMock;

    protected function setUp(): void
    {
        parent::setUp();

        $customer = new CustomerFactory()->createOne();

        $productGroup = new ProductGroupFactory()->microsoft365()->createOne();

        $parentProduct = new ProductFactory()->for($productGroup)->createOne([
            'slug' => 'microsoft-business-standard-parent',
        ]);

        $this->microsoft365CustomerInfo = new Microsoft365CustomerInfoFactory()->for($customer)->createOne([
            'kpn_customer_id' => 'CID1323371',
        ]);

        $childProduct = new ProductFactory()->for($productGroup)->createOne([
            'slug' => 'microsoft-business-standard',
        ]);

        $this->parentSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->technicalStatusOk()
            ->createOne([
                'product_uuid' => $parentProduct->uuid,
            ]);

        $this->firstChildSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->parentSubscription($this->parentSubscription)
            ->technicalStatusOk()
            ->createOne([
                'product_uuid' => $childProduct->uuid,
            ]);

        $this->microsoft365Deployment = new Microsoft365DeploymentFactory()
            ->for($this->parentSubscription)
            ->for($this->microsoft365CustomerInfo)
            ->createOne([
                'kpn_order_id' => self::KPN_ORDER_ID,
            ]);

        $this->loggerMock = self::createMock(LoggerInterface::class);
    }

    #[Test]
    public function incomingResponseLogXmlBody(): void
    {
        $this->processIncomingCall(__DIR__ . '/../Data/ModifyOrderQuantityResponse.xml');

        $log = Microsoft365HttpLog::firstOrFail();

        self::assertStringContainsString(
            '<ModifyOrderQuantityResponse_V1>',
            (string) $log->log,
        );
    }

    #[Test]
    public function incomingNewCustomerRequest(): void
    {
        $mockCustomerCreateListener = self::createMock(CustomerCreateListener::class);
        $this->app->bind(CustomerCreateListener::class, fn (): CustomerCreateListener => $mockCustomerCreateListener);

        $this->processIncomingCall(__DIR__ . '/../Data/NewCustomerResponse.xml');

        $log = Microsoft365HttpLog::firstOrFail();

        self::assertSame($this->microsoft365CustomerInfo->kpn_customer_id, $log->kpn_customer_id);
        self::assertSame('NewCustomerResponse_V3', $log->xml_root_name);
        self::assertSame($this->parentSubscription->id, $log->subscription_id);
    }

    #[Test]
    public function incomingNewCloudLicenseOrderRequest(): void
    {
        $this->processIncomingCall(__DIR__ . '/../Data/NewCloudLicenseOrderResponse.xml');

        $log = Microsoft365HttpLog::firstOrFail();

        self::assertSame(strval($this->microsoft365Deployment->kpn_order_id), $log->kpn_order_id);
        self::assertSame('NewCloudLicenseOrderResponse_V4', $log->xml_root_name);
        self::assertSame($this->parentSubscription->id, $log->subscription_id);
        self::assertSame($this->microsoft365CustomerInfo->kpn_customer_id, $log->kpn_customer_id);
    }

    #[Test]
    public function incomingModifyOrderQuantityRequest(): void
    {
        $this->processIncomingCall(__DIR__ . '/../Data/ModifyOrderQuantityResponse.xml');

        $log = Microsoft365HttpLog::firstOrFail();

        self::assertSame(strval($this->microsoft365Deployment->kpn_order_id), $log->kpn_order_id);
        self::assertSame($this->microsoft365CustomerInfo->kpn_customer_id, $log->kpn_customer_id);
        self::assertSame('ModifyOrderQuantityResponse_V1', $log->xml_root_name);
        self::assertSame($this->parentSubscription->id, $log->subscription_id);
    }

    #[Test]
    public function incomingResponseLogPartnerReference(): void
    {
        $this->processIncomingCall(__DIR__ . '/../Data/ModifyOrderQuantityResponse.xml');

        $log = Microsoft365HttpLog::firstOrFail();

        self::assertSame('MICROSOFT365-ID-14775-29241', $log->partner_reference);
    }

    #[Test]
    public function incomingMicrosoftTenantOrderResponse(): void
    {
        $this->microsoft365CustomerInfo->tenant_order_id = null;
        $this->microsoft365CustomerInfo->save();

        $this->processIncomingCall(__DIR__ . '/../Data/MicrosoftTenantOrderResponse.xml');

        $log = Microsoft365HttpLog::firstOrFail();

        self::assertSame(strval(self::KPN_ORDER_ID), $log->kpn_order_id);
        self::assertSame($this->microsoft365CustomerInfo->kpn_customer_id, $log->kpn_customer_id);
        self::assertSame('MicrosoftTenantOrderResponse_V1', $log->xml_root_name);
        self::assertSame($this->parentSubscription->id, $log->subscription_id);
        self::assertSame('WF-CUSTOMER-3547-1054', $log->partner_reference);
        self::assertSame('testm33456.onmicrosoft.com', $log->tenant_name);

        $this->microsoft365CustomerInfo->refresh();
        self::assertSame(self::KPN_ORDER_ID, $this->microsoft365CustomerInfo->tenant_order_id);
    }

    #[Test]
    public function incomingOrderMessageActiveResponse(): void
    {
        $this->microsoft365CustomerInfo->tenant_order_id = self::KPN_ORDER_ID;
        $this->microsoft365CustomerInfo->save();

        $microsoft365ServiceMock = self::createMock(Microsoft365Service::class);
        $microsoft365ServiceMock
            ->expects(self::once())
            ->method('prepareOrders')
            ->with(self::callback(
                fn (Microsoft365CustomerInfo $customerInfo): bool => (
                    $customerInfo->id === $this->microsoft365CustomerInfo->id
                ),
            ));
        $this->app->bind(Microsoft365Service::class, fn (): Microsoft365Service => $microsoft365ServiceMock);

        $this->processIncomingCall(__DIR__ . '/../Data/OrderMessageActiveResponse.xml');

        $log = Microsoft365HttpLog::firstOrFail();

        self::assertSame(strval(self::KPN_ORDER_ID), $log->kpn_order_id);
        self::assertSame($this->microsoft365CustomerInfo->kpn_customer_id, $log->kpn_customer_id);
        self::assertSame('OrderMessage_V1', $log->xml_root_name);
        self::assertSame($this->parentSubscription->id, $log->subscription_id);
    }

    #[Test]
    public function incomingTerminateOrderResponse(): void
    {
        $this->parentSubscription->administrative_status = AdministrativeStatus::ARCHIVING->value;
        $this->parentSubscription->save();

        $this->firstChildSubscription->administrative_status = AdministrativeStatus::ARCHIVING->value;
        $this->firstChildSubscription->save();

        $this->processIncomingCall(__DIR__ . '/../Data/TerminateOrderResponse.xml');

        $this->parentSubscription->refresh();
        $this->firstChildSubscription->refresh();

        $log = Microsoft365HttpLog::firstOrFail();

        self::assertSame(strval($this->microsoft365Deployment->kpn_order_id), $log->kpn_order_id);
        self::assertSame($this->microsoft365CustomerInfo->kpn_customer_id, $log->kpn_customer_id);
        self::assertSame('TerminateOrderResponse_V1', $log->xml_root_name);
        self::assertSame($this->parentSubscription->id, $log->subscription_id);
        self::assertSame(AdministrativeStatus::ARCHIVED->value, $this->parentSubscription->administrative_status);
        self::assertSame(AdministrativeStatus::ARCHIVED->value, $this->firstChildSubscription->administrative_status);
    }

    #[Test]
    public function incomingModifyOrderQuantityAndTerminationRequest(): void
    {
        $this->processIncomingCall(__DIR__ . '/../Data/ModifyOrderQuantityResponse.xml');

        $modifyLog = Microsoft365HttpLog::where('xml_root_name', 'ModifyOrderQuantityResponse_V1')->firstOrFail();

        self::assertSame(strval($this->microsoft365Deployment->kpn_order_id), $modifyLog->kpn_order_id);
        self::assertSame($this->parentSubscription->id, $modifyLog->subscription_id);

        $this->microsoft365Deployment->kpn_order_id = 10264559;
        $this->microsoft365Deployment->save();

        $this->processIncomingCall(__DIR__ . '/../Data/TerminateOrderResponse.xml');

        $terminateLog = Microsoft365HttpLog::where('xml_root_name', 'TerminateOrderResponse_V1')->firstOrFail();

        self::assertSame(strval(self::KPN_ORDER_ID), $terminateLog->kpn_order_id);
        self::assertSame($this->parentSubscription->id, $terminateLog->subscription_id);
    }

    #[Test]
    public function incomingNewCustomerDeclinedRequest(): void
    {
        $newCustomerDeclinedXml = (string) file_get_contents(__DIR__ . '/../Data/NewCustomerDeclinedV1.xml');
        $this->processIncomingCall('NewCustomerDeclined_V1', str_replace(
            'KPN_CUSTOMER_ID',
            strval($this->microsoft365CustomerInfo->id),
            $newCustomerDeclinedXml,
        ));

        $log = Microsoft365HttpLog::where('xml_root_name', 'NewCustomerDeclined_V1')->firstOrFail();

        self::assertSame('WF-CUSTOMER-1-' . $this->microsoft365CustomerInfo->id, $log->partner_reference);
        self::assertSame(strval($this->microsoft365CustomerInfo->kpn_customer_id), $log->kpn_customer_id);
    }

    #[Test]
    public function incomingOrderDeclinedRequest(): void
    {
        $this->microsoft365Deployment->kpn_order_id = null;
        $this->microsoft365Deployment->save();

        $newOrderDeclinedXml = (string) file_get_contents(__DIR__ . '/../Data/OrderDeclinedV2.xml');
        $this->processIncomingCall('OrderDeclinedV2', str_replace(
            'KPN_DEPLOYMENT_ID',
            strval($this->microsoft365Deployment->id),
            $newOrderDeclinedXml,
        ));

        $log = Microsoft365HttpLog::where('xml_root_name', 'OrderDeclined_V2')->firstOrFail();

        self::assertNull($log->kpn_order_id);
        self::assertSame($this->microsoft365CustomerInfo->kpn_customer_id, $log->kpn_customer_id);
        self::assertSame(TechnicalStatus::OK->value, $this->parentSubscription->refresh()->technical_status);
    }

    #[Test]
    public function incomingTenantOrderDeclinedRequest(): void
    {
        $this->microsoft365Deployment->kpn_order_id = null;
        $this->microsoft365Deployment->save();

        $newOrderDeclinedXml = (string) file_get_contents(__DIR__ . '/../Data/OrderDeclinedV2_TenantOrder.xml');
        $this->processIncomingCall('NewCustomerDeclined_V1', str_replace(
            'KPN_CUSTOMER_ID',
            strval($this->microsoft365CustomerInfo->id),
            $newOrderDeclinedXml,
        ));

        $log = Microsoft365HttpLog::where('xml_root_name', 'OrderDeclined_V2')->firstOrFail();

        self::assertNull($log->kpn_order_id);
        self::assertSame($this->microsoft365CustomerInfo->kpn_customer_id, $log->kpn_customer_id);
        self::assertSame(TechnicalStatus::OK->value, $this->parentSubscription->refresh()->technical_status);
    }

    #[Test]
    public function incomingOrderDeclinedNoPartnerReferenceRequest(): void
    {
        $this->processIncomingCall(__DIR__ . '/../Data/OrderDeclinedV2NoPartnerReference.xml');

        $log = Microsoft365HttpLog::where('xml_root_name', 'OrderDeclined_V2')->firstOrFail();

        self::assertSame(strval($this->microsoft365Deployment->kpn_order_id), $log->kpn_order_id);
        self::assertSame(strval($this->microsoft365CustomerInfo->kpn_customer_id), $log->kpn_customer_id);
    }

    #[Test]
    public function webhookLoggerException(): void
    {
        $exception = new Exception();
        $mockMicrosoft365WebhookLogListener = self::createMock(Microsoft365WebhookLogListener::class);
        $mockMicrosoft365WebhookLogListener->method('handle')->willThrowException($exception);
        $this->app->bind(
            Microsoft365WebhookLogListener::class,
            fn (): Microsoft365WebhookLogListener => $mockMicrosoft365WebhookLogListener,
        );

        $this->loggerMock
            ->expects(self::once())
            ->method('error')
            ->with('Something went wrong while logging the Microsoft365 webhook', [
                LoggingContextKeys::EXCEPTION => $exception,
            ]);

        $this->processIncomingCall(__DIR__ . '/../Data/OrderDeclinedV2NoPartnerReference.xml');
    }

    private function processIncomingCall(string $fileName, ?string $fileContent = null): void
    {
        $client = new OfficeClient('example.com', 'test', 'test');

        $request = new Request(
            content: $fileContent ?? (string) file_get_contents($fileName),
        );

        self::resolve(WebhookController::class)
            ->incomingCall(
                request: $request,
                microsoftClient: $client,
                cloudLicenseListener: new CloudLicenseListener(self::createStub(Microsoft365Service::class)),
                customerCreateListener: self::resolve(CustomerCreateListener::class),
                tenantCreateListener: self::resolve(TenantCreateListener::class),
                orderMessageListener: self::resolve(OrderMessageListener::class),
                eventDispatcher: self::resolve(Dispatcher::class),
                logger: $this->loggerMock,
            );
    }
}
