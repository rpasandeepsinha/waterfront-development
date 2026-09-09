<?php

declare(strict_types=1);

namespace Waterfront\Apps\OneOffScripts\Microsoft365;

use Psr\Log\LoggerInterface;
use Waterfront\Domain\Microsoft365\Exceptions\OrderSummaryCustomerNotFoundException;
use Waterfront\Domain\Microsoft365\Exceptions\OrderSummaryException;
use Waterfront\Domain\Microsoft365\Helpers\Microsoft365Helper;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;
use Waterfront\Domain\Microsoft365\Services\Microsoft365Service;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;
use Webmozart\Assert\Assert;

class AddMissingTenantOrderIdJob extends AbstractQueueableJob
{
    public function __construct(
        private readonly Microsoft365CustomerInfo $microsoft365CustomerInfo,
    ) {
        parent::__construct();
    }

    public function handle(
        LoggerInterface $logger,
        Microsoft365Service $microsoft365Service,
    ): void {
        $kpnCustomerId = $this->microsoft365CustomerInfo->kpn_customer_id;

        Assert::notNull($kpnCustomerId);

        $numericCustomerId = Microsoft365Helper::customerIdToInt($kpnCustomerId);

        try {
            $tenantOrderId = $microsoft365Service->getTenantOrderId($numericCustomerId);
        } catch (OrderSummaryException|OrderSummaryCustomerNotFoundException $e) {
            $logger->info(
                sprintf('No tenant order summary found for KPN customer id: [%s]', $kpnCustomerId),
                [
                    LoggingContextKeys::ONE_OFF_SCRIPT => NovaAddMissingTenantOrderIdAction::SLUG,
                    LoggingContextKeys::EXCEPTION => $e,
                    LoggingContextKeys::META => [
                        'customer_info_id' => $this->microsoft365CustomerInfo->id,
                        'kpn_customer_id' => $kpnCustomerId,
                    ],
                ]
            );

            return;
        }

        if ($tenantOrderId === null) {
            $logger->info(
                sprintf('No tenant order found for KPN customer id: [%s]', $kpnCustomerId),
                [
                    LoggingContextKeys::ONE_OFF_SCRIPT => NovaAddMissingTenantOrderIdAction::SLUG,
                    LoggingContextKeys::META => [
                        'customer_info_id' => $this->microsoft365CustomerInfo->id,
                        'kpn_customer_id' => $kpnCustomerId,
                    ],
                ]
            );

            return;
        }

        $logger->info(
            sprintf('Found tenant order id [%d] for KPN customer id: [%s]', $tenantOrderId, $kpnCustomerId),
            [
                LoggingContextKeys::ONE_OFF_SCRIPT => NovaAddMissingTenantOrderIdAction::SLUG,
                LoggingContextKeys::META => [
                    'customer_info_id' => $this->microsoft365CustomerInfo->id,
                    'kpn_customer_id' => $kpnCustomerId,
                    'tenant_order_id' => $tenantOrderId,
                ],
            ]
        );

        $this->microsoft365CustomerInfo->tenant_order_id = $tenantOrderId;
        $this->microsoft365CustomerInfo->save();
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::ONE_TIME_SCRIPTS;
    }
}
