<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Ferry\Controllers;

use Illuminate\Http\JsonResponse;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Ferry\Actions\Customers\EnableInvoicingForCustomerAction;

class CustomerInvoiceController
{
    public function __construct(
        private readonly EnableInvoicingForCustomerAction $enableInvoicingForCustomerAction,
    ) {
    }

    public function enableInvoicing(Customer $customer): JsonResponse
    {
        $this->enableInvoicingForCustomerAction->execute($customer);

        return new JsonResponse([]);
    }
}
