<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\Actions;

use JsonException;
use SandwaveIo\Office365\Exception\Office365Exception;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365ProcessStatus;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;
use Waterfront\Domain\Microsoft365\Services\Microsoft365Service;

class RetryCreateKpnCustomerAction
{
    public function __construct(
        private readonly Microsoft365Service $microsoft365Service,
    ) {
    }

    /**
     * @throws Office365Exception|TooManyRequestsHttpException|JsonException
     */
    public function execute(Microsoft365CustomerInfo $microsoft365CustomerInfo): bool
    {
        if ($microsoft365CustomerInfo->kpn_customer_id !== null) {
            return true;
        }

        $successful = $this->microsoft365Service->createKpnCustomer(
            customer: $microsoft365CustomerInfo->customer,
            customerInfoId: (string) $microsoft365CustomerInfo->id,
        );

        if (! $successful) {
            $microsoft365CustomerInfo->technical_status = Microsoft365ProcessStatus::FAILED;
            $microsoft365CustomerInfo->save();
        }

        return $successful;
    }
}
