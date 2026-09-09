<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Response as HttpResponse;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Apps\API\Compass\Resources\Transfers\TransferResource;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Transfers\Enums\TransferType;
use Waterfront\Domain\Transfers\Exceptions\TransferException;
use Waterfront\Domain\Transfers\Models\Transfer;
use Waterfront\Domain\Transfers\Repositories\ProductTransferRepository;
use Waterfront\Domain\Transfers\Services\TransferService;

readonly class ProductTransferController
{
    public function __construct(
        private ProductTransferRepository $productTransferRepository,
        private TransferService $transferService,
    ) {
    }

    public function listProductTransfers(Request $request, Customer $customer): ResourceCollection
    {
        $pageSize = is_numeric($request->input('pageSize')) ? (int) $request->input('pageSize') : 15;

        $transferTypeParam = $request->input('transferType');
        $transferType = is_string($transferTypeParam) ? TransferType::tryFrom($transferTypeParam) : null;

        $transfers = $this->productTransferRepository->findByTransferTypeAndCustomerPaginated($customer, $transferType, $pageSize);

        return TransferResource::collection($transfers)->additional([
            'meta' => [
                'totalTransfers' => $transfers->total(),
            ],
        ]);
    }

    public function retryProductTransfer(Transfer $productTransfer): Response
    {
        try {
            $this->transferService->retry($productTransfer);
        } catch (InvalidArgumentException|TransferException $exception) {
            return new HttpResponse(['errors' => $exception->getMessage()], status: Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new HttpResponse(status: Response::HTTP_NO_CONTENT);
    }

    public function productTransfers(Request $request, Subscription $subscription): ResourceCollection
    {
        $pageSize = is_numeric($request->input('pageSize')) ? (int) $request->input('pageSize') : 100;

        $results = $this->productTransferRepository->findBySubscriptionIdPaginated($subscription->id, $pageSize);

        return TransferResource::collection($results);
    }
}
