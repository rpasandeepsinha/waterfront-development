<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\EventListener;

use Psr\Log\LoggerInterface;
use SandwaveIo\Office365\Entity\TenantOrder;
use SandwaveIo\Office365\Library\Observer\Status\Status;
use SandwaveIo\Office365\Library\Observer\Tenant\TenantObserverInterface;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365OrderStatus;
use Waterfront\Domain\Microsoft365\Repositories\Microsoft365CustomerInfoRepository;
use Waterfront\Domain\Microsoft365\Services\Microsoft365Service;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Support\Enums\LoggingContextKeys;

class TenantCreateListener implements TenantObserverInterface
{
    public function __construct(
        private readonly Microsoft365CustomerInfoRepository $microsoft365CustomerInfoRepository,
        private readonly Microsoft365Service $microsoft365Service,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(TenantOrder $tenantOrder, ?Status $status): void
    {
        $kpnCustomerId = $tenantOrder->getCustomerId();

        if ($kpnCustomerId === null) {
            $this->logger->error('Tenant order received without customer id', [
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::M365,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::MICROSOFT_IRMA,
                LoggingContextKeys::META => [
                    'kpn_order_id' => $tenantOrder->getOrderId(),
                ],
            ]);

            return;
        }

        $customerInfo = $this->microsoft365CustomerInfoRepository->findByKpnCustomerId($kpnCustomerId);
        $tenantOrderMeta = [
            'PartnerReferenceHeader' => $tenantOrder->getPartnerReferenceHeader(),
            'CustomerId' => $tenantOrder->getCustomerId(),
            'ProductCode' => $tenantOrder->getProductCode(),
            'IsExistingTenant' => $tenantOrder->isExistingTenant(),
            'TenantName' => $tenantOrder->getTenantName(),
            'TenantId' => $tenantOrder->getTenantId(),
            'FirstName' => $tenantOrder->getFirstName(),
            'LastName' => $tenantOrder->getLastName(),
            'Email' => $tenantOrder->getEmail(),
            'OrderId' => $tenantOrder->getOrderId(),
        ];

        if ($customerInfo === null) {
            $this->logger->error(
                sprintf('No customer info found for KPN customer id: [%s]', $kpnCustomerId),
                [
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::M365,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::MICROSOFT_IRMA,
                    LoggingContextKeys::META => [
                        'tenant_order' => $tenantOrderMeta,
                    ],
                ],
            );

            return;
        }

        $this->logger->info(
            sprintf('Tenant "%s" created for KPN customer id: [%d]', $tenantOrder->getTenantName(), $kpnCustomerId),
            [
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::M365,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::MICROSOFT_IRMA,
                LoggingContextKeys::META => [
                    'tenant_order' => $tenantOrderMeta,
                ],
            ],
        );

        $customerInfo->tenant_order_id = $tenantOrder->getOrderId();
        $customerInfo->save();

        if ($status !== null && strtolower($status->getStatusCode()) === Microsoft365OrderStatus::ACTIVE->value) {
            $this->logger->info(
                'Tenant order is active, preparing orders for customer {customer.kpn_id}',
                [
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::M365,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::MICROSOFT_IRMA,
                    LoggingContextKeys::META => [
                        'tenant_order' => $tenantOrderMeta,
                    ],
                ],
            );

            $this->microsoft365Service->prepareOrders(
                microsoft365CustomerInfo: $customerInfo,
            );
        }
    }
}
