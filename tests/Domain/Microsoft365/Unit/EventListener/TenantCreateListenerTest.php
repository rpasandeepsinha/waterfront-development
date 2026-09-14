<?php

declare(strict_types=1);

namespace Tests\Domain\Microsoft365\Unit\EventListener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use SandwaveIo\Office365\Entity\TenantOrder;
use SandwaveIo\Office365\Helper\EntityHelper;
use SandwaveIo\Office365\Library\Observer\Status\Status;
use Tests\Factories\CustomerFactory;
use Tests\Factories\Microsoft365CustomerInfoFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Microsoft365\EventListener\TenantCreateListener;
use Waterfront\Domain\Microsoft365\Helpers\Microsoft365Helper;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;
use Waterfront\Domain\Microsoft365\Repositories\Microsoft365CustomerInfoRepository;
use Waterfront\Domain\Microsoft365\Services\Microsoft365Service;

#[CoversClass(TenantCreateListener::class)]
class TenantCreateListenerTest extends IntegrationTestCase
{
    private const int KPN_CUSTOMER_ID = 678302;

    private const int TENANT_ORDER_ID = 22334085;

    private Microsoft365CustomerInfo $microsoft365CustomerInfo;

    private TenantCreateListener $listener;

    private LoggerInterface&MockObject $logger;

    private Microsoft365Service&MockObject $microsoft365Service;

    protected function setUp(): void
    {
        parent::setUp();

        $customer = new CustomerFactory()->createOne();

        $this->microsoft365CustomerInfo = new Microsoft365CustomerInfoFactory()->for($customer)->createOne([
            'kpn_customer_id' => Microsoft365Helper::customerIdToStringWithPrefix(self::KPN_CUSTOMER_ID),
            'tenant_order_id' => null,
        ]);

        $this->logger = self::createMock(LoggerInterface::class);
        $this->microsoft365Service = self::createMock(Microsoft365Service::class);

        $this->listener = new TenantCreateListener(
            self::resolve(Microsoft365CustomerInfoRepository::class),
            $this->microsoft365Service,
            $this->logger,
        );
    }

    #[Test]
    public function executeStoresTenantOrderIdOnCustomerInfo(): void
    {
        $tenantOrder = $this->createTenantOrder(
            customerId: self::KPN_CUSTOMER_ID,
            orderId: self::TENANT_ORDER_ID,
        );

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(self::stringContains(sprintf(
                'Tenant "test.onmicrosoft.com" created for KPN customer id: [%d]',
                self::KPN_CUSTOMER_ID,
            )));

        $this->microsoft365Service->expects(self::never())->method('prepareOrders');

        $this->listener->execute($tenantOrder, null);

        $this->microsoft365CustomerInfo->refresh();

        self::assertSame(self::TENANT_ORDER_ID, $this->microsoft365CustomerInfo->tenant_order_id);
    }

    #[Test]
    public function executeLogsErrorWhenCustomerIdIsMissing(): void
    {
        $tenantOrder = $this->createTenantOrder(customerId: null, orderId: self::TENANT_ORDER_ID);

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with('Tenant order received without customer id', self::isArray());

        $this->microsoft365Service->expects(self::never())->method('prepareOrders');

        $this->listener->execute($tenantOrder, null);

        $this->microsoft365CustomerInfo->refresh();

        self::assertNull($this->microsoft365CustomerInfo->tenant_order_id);
    }

    #[Test]
    public function executeLogsErrorWhenCustomerInfoCannotBeFound(): void
    {
        $tenantOrder = $this->createTenantOrder(customerId: 999999, orderId: self::TENANT_ORDER_ID);

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with(self::stringContains('No customer info found for KPN customer id: [999999]'));

        $this->microsoft365Service->expects(self::never())->method('prepareOrders');

        $this->listener->execute($tenantOrder, null);

        $this->microsoft365CustomerInfo->refresh();

        self::assertNull($this->microsoft365CustomerInfo->tenant_order_id);
    }

    #[Test]
    public function executeCallsPrepareOrdersWhenStatusIsActive(): void
    {
        $tenantOrder = $this->createTenantOrder(
            customerId: self::KPN_CUSTOMER_ID,
            orderId: self::TENANT_ORDER_ID,
        );

        $status = new Status('Active', []);

        $this->microsoft365Service
            ->expects(self::once())
            ->method('prepareOrders')
            ->with(self::callback(
                fn (Microsoft365CustomerInfo $customerInfo): bool => (
                    $customerInfo->id === $this->microsoft365CustomerInfo->id
                ),
            ));

        $this->logger->expects(self::exactly(2))->method('info');

        $this->listener->execute($tenantOrder, $status);

        $this->microsoft365CustomerInfo->refresh();

        self::assertSame(self::TENANT_ORDER_ID, $this->microsoft365CustomerInfo->tenant_order_id);
    }

    #[Test]
    public function executeDoesNotCallPrepareOrdersWhenStatusIsAccepted(): void
    {
        $tenantOrder = $this->createTenantOrder(
            customerId: self::KPN_CUSTOMER_ID,
            orderId: self::TENANT_ORDER_ID,
        );

        $status = new Status('Accepted', []);

        $this->microsoft365Service->expects(self::never())->method('prepareOrders');

        $this->logger->expects(self::once())->method('info');

        $this->listener->execute($tenantOrder, $status);

        $this->microsoft365CustomerInfo->refresh();

        self::assertSame(self::TENANT_ORDER_ID, $this->microsoft365CustomerInfo->tenant_order_id);
    }

    #[Test]
    public function executeDoesNotCallPrepareOrdersWhenStatusIsNull(): void
    {
        $tenantOrder = $this->createTenantOrder(
            customerId: self::KPN_CUSTOMER_ID,
            orderId: self::TENANT_ORDER_ID,
        );

        $this->microsoft365Service->expects(self::never())->method('prepareOrders');

        $this->logger->expects(self::once())->method('info');

        $this->listener->execute($tenantOrder, null);

        $this->microsoft365CustomerInfo->refresh();

        self::assertSame(self::TENANT_ORDER_ID, $this->microsoft365CustomerInfo->tenant_order_id);
    }

    private function createTenantOrder(?int $customerId, ?int $orderId): TenantOrder
    {
        $data = [
            'ProductCode' => '282A00001B',
            'IsExistingTenant' => false,
            'TenantName' => 'test.onmicrosoft.com',
            'TenantId' => '33c0d8ef-8bf7-40ba-a7f6-ccb08cb58f61',
            'FirstName' => 'Sandwave',
            'LastName' => 'Test',
            'Email' => 'test@sandwave.com',
            'OrderId' => $orderId,
            'Header' => [
                'PartnerReference' => 'WF-CUSTOMER-1-1',
                'DateCreated' => '2026-06-01T09:51:40',
            ],
        ];

        if ($customerId !== null) {
            $data['CustomerId'] = $customerId;
        }

        $tenantOrder = EntityHelper::deserializeArray(TenantOrder::class, $data);

        self::assertInstanceOf(TenantOrder::class, $tenantOrder);

        return $tenantOrder;
    }
}
