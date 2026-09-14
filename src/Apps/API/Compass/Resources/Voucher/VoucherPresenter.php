<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Voucher;

use Waterfront\Apps\API\Compass\Resources\Products\ProductPresenter;
use Waterfront\Domain\Voucher\Models\Voucher;

class VoucherPresenter
{
    public function __construct(
        private readonly ProductPresenter $productPresenter,
    ) {
    }

    /** @return array<mixed> */
    public function toArray(Voucher $voucher): array
    {
        return [
            'uuid' => $voucher->uuid,
            'displayName' => $voucher->display_name,
            'internalName' => $voucher->internal_name,
            'description' => $voucher->description,
            'code' => $voucher->code,
            'amount' => $voucher->amount,
            'amountType' => $voucher->amount_type->value,
            'maxClaims' => $voucher->max_claims,
            'billingPeriod' => $voucher->billing_period,
            'contractPeriod' => $voucher->contract_period,
            'expirationDate' => $voucher->expiration_date?->toIso8601String(),
            'claimsCount' => $voucher->claims_count,
            'applyWithDiscount' => $voucher->apply_with_discount,
            'allowMultipleClaimsSameCustomer' => $voucher->allow_multiple_claims_same_customer,
            'product' => $voucher->product !== null ? $this->productPresenter->toArray($voucher->product) : null,
        ];
    }
}
