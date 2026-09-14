<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Apps\API\Compass\Resources\Wallets\WalletResource;
use Waterfront\Domain\Customers\Models\CustomerWallet;
use Waterfront\Domain\Customers\Repositories\CustomerWalletRepository;
use Waterfront\Domain\Customers\Services\CustomerWalletService;
use Waterfront\Infra\Common\DateTimeFormat;
use Webmozart\Assert\Assert;

class WalletController
{
    public function __construct(
        private readonly CustomerWalletService $customerWalletService,
        private readonly CustomerWalletRepository $customerWalletRepository,
    ) {
    }

    public function listWallets(Request $request): ResourceCollection
    {
        $pageSize = is_numeric($request->input('pageSize')) ? (int) $request->input('pageSize') : 100;
        $wallets = CustomerWallet::query()->paginate($pageSize);
        $wallets->appends('pageSize', (string) $pageSize);

        return WalletResource::collection($wallets)->additional([
            'meta' => ['totalWallets' => $wallets->total()],
        ]);
    }

    public function requestWalletRefundCsv(Request $request): Response
    {
        $wallets = $request->input('wallet');
        Assert::isArray($wallets);
        Assert::allInteger($wallets);

        if (count($wallets) > 500) {
            return new Response('too many entities', Response::HTTP_BAD_REQUEST);
        }

        $collection = $this->customerWalletRepository->getWalletsByIdsNotExported($wallets);

        $csv = $this->customerWalletService->createRefundCsv($collection);

        $csvFileName = sprintf(
            'customer-wallet-refunds-%s.csv',
            CarbonImmutable::now()->format(DateTimeFormat::FILENAME),
        );

        return new Response($csv, Response::HTTP_OK, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $csvFileName),
            'Content-Length' => strlen($csv),
        ]);
    }
}
