<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Waterfront\Apps\API\Compass\Requests\StoreVoucherRequest;
use Waterfront\Apps\API\Compass\Requests\UpdateVoucherRequest;
use Waterfront\Apps\API\Compass\Resources\Voucher\VoucherPresenter;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Voucher\DTO\VoucherDTO;
use Waterfront\Domain\Voucher\Enum\VoucherAmountType;
use Waterfront\Domain\Voucher\Models\Voucher;
use Waterfront\Domain\Voucher\Services\VoucherService;

class VoucherController
{
    public function __construct(
        private readonly VoucherService $voucherService,
        private readonly VoucherPresenter $voucherPresenter,
    ) {
    }

    public function storeVoucher(StoreVoucherRequest $request): JsonResponse
    {
        $voucherDTO = new VoucherDTO(
            displayName: $request->string('displayName')->toString(),
            internalName: $request->string('internalName')->toString(),
            description: $request->has('description') ? $request->string('description')->toString() : null,
            code: $request->string('code')->toString(),
            amount: $request->integer('amount'),
            amountType: VoucherAmountType::from($request->string('amountType')->toString()),
            maxClaims: $request->has('maxClaims') ? $request->integer('maxClaims') : null,
            billingPeriod: $request->has('billingPeriod') ? $request->integer('billingPeriod') : null,
            contractPeriod: $request->has('contractPeriod') ? $request->integer('contractPeriod') : null,
            expirationDate: $request->has('expirationDate')
                ? CarbonImmutable::parse($request->string('expirationDate')->toString())
                : null,
            applyWithDiscount: (bool) $request->input('applyWithDiscount'),
            allowMultipleClaimsSameCustomer: (bool) $request->input('allowMultipleClaimsSameCustomer'),
            productSlug: $request->has('productSlug') ? $request->string('productSlug')->toString() : null,
            productGroupSlug: ProductGroupType::from($request->string('productGroupSlug')->toString()),
        );

        $voucher = $this->voucherService->storeVoucher($voucherDTO);

        return new JsonResponse(['uuid' => $voucher->uuid], Response::HTTP_CREATED);
    }

    public function listVouchers(Request $request): JsonResponse
    {
        $pageSize = is_numeric($request->input('pageSize')) ? (int) $request->input('pageSize') : 100;
        $vouchers = Voucher::query()->withCount('claims')->paginate($pageSize);
        $vouchers->appends('pageSize', (string) $pageSize);

        $data = array_map(fn (Voucher $voucher) => $this->voucherPresenter->toArray($voucher), $vouchers->all());

        return new JsonResponse([
            'data' => $data,
            'meta' => [
                'path' => $vouchers->path(),
                'total' => $vouchers->total(),
                'per_page' => $vouchers->perPage(),
                'current_page' => $vouchers->currentPage(),
                'from' => $vouchers->firstItem(),
                'to' => $vouchers->lastItem(),
                'last_page' => $vouchers->lastPage(),
                'totalVouchers' => $vouchers->total(),
            ],
        ]);
    }

    public function showVoucher(Voucher $voucher): JsonResponse
    {
        $voucher->load(['product', 'productGroup'])->loadCount('claims');

        return new JsonResponse($this->voucherPresenter->toArray($voucher));
    }

    public function updateVoucher(UpdateVoucherRequest $request, Voucher $voucher): Response
    {
        $dto = new VoucherDTO(
            displayName: $voucher->display_name,
            internalName: $voucher->internal_name,
            description: $request->description,
            code: $voucher->code,
            amount: $voucher->amount,
            amountType: $voucher->amount_type,
            maxClaims: $request->maxClaims,
            billingPeriod: $voucher->billing_period,
            contractPeriod: $voucher->contract_period,
            expirationDate: $request->expirationDate !== null ? CarbonImmutable::parse($request->expirationDate) : null,
            applyWithDiscount: $voucher->apply_with_discount,
            allowMultipleClaimsSameCustomer: $voucher->allow_multiple_claims_same_customer,
            productSlug: $voucher->product_uuid,
            productGroupSlug: null,
        );

        $this->voucherService->updateVoucher($voucher, $dto);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
