<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Services;

use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Invoices\Repositories\InvoiceRepository;

class CreditLimitService
{
    public function __construct(
        private readonly InvoiceRepository $invoiceRepository,
    ) {
    }

    public function getDisposableAmount(Customer $customer): int
    {
        return $customer->credit_limit - $this->invoiceRepository->getOpenInvoiceAmount($customer);
    }

    public function isOrderAmountAllowed(Customer $customer, int $orderAmount): bool
    {
        if ($this->invoiceRepository->getOpenInvoiceAmount($customer) === 0) {
            return true;
        }

        $disposableAmount = $this->getDisposableAmount($customer);

        return $disposableAmount >= $orderAmount;
    }
}
