<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\EventListener;

use Psr\Log\LoggerInterface;
use SandwaveIo\Office365\Entity\OrderMessage;
use SandwaveIo\Office365\Library\Observer\OrderMessage\OrderMessageObserverInterface;
use SandwaveIo\Office365\Library\Observer\Status\Status;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;
use Waterfront\Domain\Microsoft365\Repositories\Microsoft365CustomerInfoRepository;
use Waterfront\Domain\Microsoft365\Services\Microsoft365Service;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Support\Enums\LoggingContextKeys;

class OrderMessageListener implements OrderMessageObserverInterface
{
    public function __construct(
        private readonly Microsoft365Service $microsoft365Service,
        private readonly Microsoft365CustomerInfoRepository $microsoft365CustomerInfoRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(OrderMessage $orderMessage, ?Status $status): void
    {
        $loggingContext = [
            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::M365,
            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::MICROSOFT_IRMA,
            LoggingContextKeys::META => [
                'm365_order_message' => [
                    'status' => $orderMessage->getStatus(),
                    'order_data' => [
                        'OrderId' => $orderMessage->getOrderData()?->getOrderId(),
                        'CustomerId' => $orderMessage->getOrderData()?->getCustomerId(),
                        'OrderState' => $orderMessage->getOrderData()?->getOrderState(),
                        'ProductId' => $orderMessage->getOrderData()?->getProductId(),
                        'Quantity' => $orderMessage->getOrderData()?->getQuantity(),
                        'ProductName' => $orderMessage->getOrderData()?->getProductName(),
                        'ProductGroup' => $orderMessage->getOrderData()?->getProductGroup(),
                    ],
                ],
            ],
        ];

        $this->logger->info(
            'OrderMessage received from IRMA',
            $loggingContext,
        );

        if (
            strtolower($orderMessage->getStatus()?->getCode() ?? '') !== 'active'
            || $orderMessage->getOrderData()?->getOrderId() === null
        ) {
            $this->logger->info(
                'Skipped handling of order_message because it is not active or has no order_id.',
                $loggingContext,
            );
            return;
        }

        if ($orderMessage->getOrderData()->getProductId() !== Microsoft365Service::MICROSOFT_TENANT_PRODUCT_CODE) {
            $this->logger->info(
                'Skipped handling of order_message because it is not related to a Microsoft tenant order.',
                $loggingContext,
            );
            return;
        }

        $customerInfo = $this->microsoft365CustomerInfoRepository->findByTenantOrderId($orderMessage->getOrderData()->getOrderId());
        if (! $customerInfo instanceof Microsoft365CustomerInfo) {
            $this->logger->error(
                sprintf('Could not find KPN customer info for order_id: %d', $orderMessage->getOrderData()->getOrderId()),
                $loggingContext,
            );
            return;
        }

        $this->microsoft365Service->prepareOrders(
            microsoft365CustomerInfo: $customerInfo,
        );
    }
}
