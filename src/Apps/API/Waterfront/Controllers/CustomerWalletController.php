<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Apps\API\Waterfront\Policies\CustomerPolicy;
use Waterfront\Apps\API\Waterfront\Requests\CustomerWallet\RequestRefundRequest;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\CustomerWallet;
use Waterfront\Domain\Customers\Services\CustomerWalletService;
use Waterfront\Support\Enums\LoggingContextKeys;

class CustomerWalletController
{
    public function __construct(
        private readonly ResponseFactory $responseFactory,
        private readonly CustomerWalletService $customerWalletService,
        private readonly LoggerInterface $logger,
        private readonly CustomerPolicy $customerPolicy,
    ) {
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function show(Customer $customer): JsonResponse
    {
        $this->customerPolicy->assertCanUpdateWallet($customer);

        return new JsonResource($customer->wallet)->response();
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function requestRefund(RequestRefundRequest $refundRequest, Customer $customer): JsonResponse
    {
        $this->customerPolicy->assertCanUpdateWallet($customer);

        if (! $customer->wallet instanceof CustomerWallet) {
            $this->logger->warning(
                'refund request failed, no wallet found',
                [
                    LoggingContextKeys::CUSTOMER_ID => $customer->id,
                    LoggingContextKeys::REQUEST_DATA => (string) json_encode($refundRequest->toArray()),
                ],
            );

            return $this->responseFactory->json(['message' => 'no wallet found'], Response::HTTP_CONFLICT);
        }

        if ($customer->wallet->refund_requested_at !== null) {
            $this->logger->warning(
                'refund request failed, already requested',
                [
                    LoggingContextKeys::CUSTOMER_ID => $customer->id,
                    LoggingContextKeys::REQUEST_DATA => (string) json_encode($refundRequest->toArray()),
                ],
            );

            return $this->responseFactory->json(['message' => 'already requested'], Response::HTTP_CONFLICT);
        }

        $this->customerWalletService->requestRefund(
            $customer->wallet,
            (string) $refundRequest->string('bank_account_name'),
            (string) $refundRequest->string('bank_account_number'),
        );

        return $this->responseFactory->json([], Response::HTTP_NO_CONTENT);
    }
}
