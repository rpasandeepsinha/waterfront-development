<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Ferry\Controllers;

use Illuminate\Http\JsonResponse;
use Waterfront\Apps\API\Ferry\Request\Convertors\MigrationCustomerPayloadToDtoConverter;
use Waterfront\Apps\API\Ferry\Request\CustomerMigrationCreateRequest;
use Waterfront\Domain\Ferry\Actions\Customers\StoreMigratedCustomerAction;

class CustomerController
{
    public function __construct(
        private readonly StoreMigratedCustomerAction $storeMigratedCustomerAction,
        private readonly MigrationCustomerPayloadToDtoConverter $converter,
    ) {
    }

    public function create(CustomerMigrationCreateRequest $request): JsonResponse
    {
        $customerDto = $this->converter->convert($request->all());

        $customerResult = $this->storeMigratedCustomerAction->execute($customerDto);

        return new JsonResponse($customerResult);
    }
}
