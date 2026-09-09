<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Ferry\Controllers;

use Illuminate\Http\JsonResponse;
use Waterfront\Apps\API\Ferry\Request\SubscriptionMigrationRequest;
use Waterfront\Domain\Customers\DTO\CreateSubscriptionsDTO;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Ferry\Actions\Subscriptions\StoreSubscriptionAction;
use Waterfront\Domain\Ferry\Dto\ResponseDto;

class SubscriptionController
{
    public function __construct(
        private readonly StoreSubscriptionAction $storeSubscriptionAction
    ) {
    }

    public function create(SubscriptionMigrationRequest $request, Customer $customer): JsonResponse
    {
        $responseDto = new ResponseDto();
        $referencedCustomerId = $request->str('reference_customer_id')->value();

        $subscriptions = $request->input('subscriptions');
        assert(is_array($subscriptions));

        $subscriptionsDto = CreateSubscriptionsDTO::create(
            $customer,
            $referencedCustomerId,
            $subscriptions,
        );

        $this->storeSubscriptionAction->execute($subscriptionsDto, $responseDto);

        return new JsonResponse($responseDto->toArray());
    }
}
