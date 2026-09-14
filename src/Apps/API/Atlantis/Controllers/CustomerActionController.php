<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Atlantis\Controllers;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Ramsey\Uuid\Nonstandard\Uuid;
use Waterfront\Apps\API\Atlantis\Resources\CustomerActionResource;
use Waterfront\Apps\API\Waterfront\Policies\CustomerPolicy;
use Waterfront\Domain\CustomerActionNeeded\Services\CustomerActionService;
use Waterfront\Domain\Orders\Models\Order;

class CustomerActionController
{
    public function __construct(
        private readonly CustomerActionService $customerActionService,
        private readonly CustomerPolicy $customerPolicy,
    ) {
    }

    /**
     * @throws AuthenticationException
     */
    public function show(Order $order): JsonResponse
    {
        $this->customerPolicy->assertCanAccess($order->customer);

        return CustomerActionResource::collection(
            $this->customerActionService->getCustomerActionsFromOrderUuid(Uuid::fromString($order->uuid)),
        )->response();
    }
}
