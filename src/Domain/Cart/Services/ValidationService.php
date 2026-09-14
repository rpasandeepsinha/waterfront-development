<?php

declare(strict_types=1);

namespace Waterfront\Domain\Cart\Services;

use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Customers\Enums\PaymentType;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Services\CreditLimitService;
use Waterfront\Domain\Products\DTO\TotalCollectionPrice;

class ValidationService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly CreditLimitService $creditLimitService,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function validateOrderTotalPrice(
        Customer $customer,
        TotalCollectionPrice $calculatedTotalOrderPrice,
        bool $orderedByEmployee,
        int $administrationFees,
    ): void {
        if (! $this->doesCalculatedPriceExceedCustomerCreditAmount(
            $customer,
            $calculatedTotalOrderPrice,
            $orderedByEmployee,
            $administrationFees,
        )) {
            throw ValidationException::withMessages([
                'total_price' => 'Customer has not enough disposable credit available.',
            ]);
        }
    }

    private function doesCalculatedPriceExceedCustomerCreditAmount(
        Customer $customer,
        TotalCollectionPrice $calculatedTotalOrderPrice,
        bool $orderedByEmployee,
        int $administrationFees,
    ): bool {
        if ($customer->payment_type === PaymentType::CREDIT || $orderedByEmployee) {
            return true;
        }

        $disposableAmount = $this->creditLimitService->getDisposableAmount($customer);

        if (! $this->creditLimitService->isOrderAmountAllowed(
            $customer,
            $calculatedTotalOrderPrice->totalExclVatPrice + $administrationFees,
        )) {
            $this->logger->notice(sprintf(
                'Customer tried to order but has not enough credit left. Disposable amount:%s TotalPrice:%s',
                $disposableAmount,
                $calculatedTotalOrderPrice->totalExclVatPrice,
            ));

            return false;
        }

        return true;
    }
}
