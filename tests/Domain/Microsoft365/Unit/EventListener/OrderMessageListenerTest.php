<?php

declare(strict_types=1);

namespace Tests\Domain\Microsoft365\Unit\EventListener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use SandwaveIo\Office365\Entity\OrderData;
use SandwaveIo\Office365\Entity\OrderMessage;
use SandwaveIo\Office365\Response\RequestStatus;
use Tests\Factories\CustomerFactory;
use Tests\Factories\Microsoft365CustomerInfoFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Microsoft365\EventListener\OrderMessageListener;
use Waterfront\Domain\Microsoft365\Helpers\Microsoft365Helper;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;
use Waterfront\Domain\Microsoft365\Repositories\Microsoft365CustomerInfoRepository;
use Waterfront\Domain\Microsoft365\Services\Microsoft365Service;

#[CoversClass(OrderMessageListener::class)]
class OrderMessageListenerTest extends IntegrationTestCase
{
    private const int TENANT_ORDER_ID = 22334085;

    private const int KPN_CUSTOMER_ID = 678302;

    private Microsoft365CustomerInfo $microsoft365CustomerInfo;

    private Microsoft365Service&MockObject $microsoft365Service;

    private OrderMessageListener $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $customer = new CustomerFactory()->createOne();

        $this->microsoft365CustomerInfo = new Microsoft365CustomerInfoFactory()->for($customer)->createOne([
            'kpn_customer_id' => Microsoft365Helper::customerIdToStringWithPrefix(self::KPN_CUSTOMER_ID),
            'tenant_order_id' => self::TENANT_ORDER_ID,
        ]);

        $this->microsoft365Service = self::createMock(Microsoft365Service::class);
        $logger = self::createStub(LoggerInterface::class);

        $this->listener = new OrderMessageListener(
            $this->microsoft365Service,
            self::resolve(Microsoft365CustomerInfoRepository::class),
            $logger,
        );
    }

    #[Test]
    public function executeTriggersPrepareOrdersForActiveTenantOrderMessage(): void
    {
        $orderMessage = $this->createOrderMessage(
            statusCode: 'Active',
            orderId: self::TENANT_ORDER_ID,
            productId: Microsoft365Service::MICROSOFT_TENANT_PRODUCT_CODE,
        );

        $this->microsoft365Service->expects(self::once())
            ->method('prepareOrders')
            ->with(self::callback(
                fn (Microsoft365CustomerInfo $customerInfo): bool => $customerInfo->id === $this->microsoft365CustomerInfo->id
            ));

        $this->listener->execute($orderMessage, null);
    }

    #[Test]
    public function executeSkipsPrepareOrdersForNonActiveStatus(): void
    {
        $orderMessage = $this->createOrderMessage(
            statusCode: 'Pending',
            orderId: self::TENANT_ORDER_ID,
            productId: Microsoft365Service::MICROSOFT_TENANT_PRODUCT_CODE,
        );

        $this->microsoft365Service->expects(self::never())
            ->method('prepareOrders');

        $this->listener->execute($orderMessage, null);
    }

    #[Test]
    public function executeSkipsPrepareOrdersWhenOrderIdIsNull(): void
    {
        $orderMessage = $this->createOrderMessage(
            statusCode: 'Active',
            orderId: null,
            productId: Microsoft365Service::MICROSOFT_TENANT_PRODUCT_CODE,
        );

        $this->microsoft365Service->expects(self::never())
            ->method('prepareOrders');

        $this->listener->execute($orderMessage, null);
    }

    #[Test]
    public function executeLogsErrorWhenCustomerInfoCannotBeFound(): void
    {
        $orderMessage = $this->createOrderMessage(
            statusCode: 'Active',
            orderId: 99999999,
            productId: Microsoft365Service::MICROSOFT_TENANT_PRODUCT_CODE,
        );

        $this->microsoft365Service->expects(self::never())
            ->method('prepareOrders');

        $logger = self::createMock(LoggerInterface::class);
        $logger->expects(self::atLeastOnce())
            ->method('info');

        $logger->expects(self::once())
            ->method('error')
            ->with(self::stringContains('Could not find KPN customer info for order_id: 99999999'));

        $listener = new OrderMessageListener(
            $this->microsoft365Service,
            self::resolve(Microsoft365CustomerInfoRepository::class),
            $logger,
        );

        $listener->execute($orderMessage, null);
    }

    #[Test]
    public function executeDoesNotPrepareOrdersForNonTenantProductId(): void
    {
        $orderMessage = $this->createOrderMessage(
            statusCode: 'Active',
            orderId: self::TENANT_ORDER_ID,
            productId: 'SOME_OTHER_PRODUCT_CODE',
        );

        $this->microsoft365Service->expects(self::never())
            ->method('prepareOrders');

        $this->listener->execute($orderMessage, null);
    }

    private function createOrderMessage(string $statusCode, ?int $orderId, ?string $productId): OrderMessage
    {
        $orderData = new OrderData();
        $orderData->setCustomerId(self::KPN_CUSTOMER_ID);
        $orderData->setOrderId($orderId);
        $orderData->setProductId($productId);
        $orderData->setProductName('Microsoft Tenant');
        $orderData->setProductGroup('Cloud');
        $orderData->setOrderState('Active');
        $orderData->setQuantity(1);

        $orderMessage = new OrderMessage();
        $orderMessage->setStatus(new RequestStatus([], $statusCode));
        $orderMessage->setOrderData($orderData);

        return $orderMessage;
    }
}
