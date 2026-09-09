<?php

declare(strict_types=1);

namespace Waterfront\Domain\Voucher\Services;

use Carbon\CarbonImmutable;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Voucher\DTO\VoucherDTO;
use Waterfront\Domain\Voucher\Models\Voucher;
use Waterfront\Domain\Voucher\Models\VoucherClaim;
use Waterfront\Domain\Voucher\Repository\VoucherRepository;

class VoucherService
{
    public function __construct(
        private readonly VoucherRepository $voucherRepository,
    ) {
    }

    public function updateVoucher(Voucher $voucher, VoucherDTO $dto): Voucher
    {
        $voucher->description     = $dto->description;
        $voucher->max_claims      = $dto->maxClaims;
        $voucher->expiration_date = $dto->expirationDate;
        $voucher->save();

        return $voucher;
    }

    public function storeVoucher(VoucherDTO $dto): Voucher
    {
        $productGroup = ProductGroup::where('slug', $dto->productGroupSlug)->firstOrFail();

        $voucher = new Voucher();
        $voucher->display_name                       = $dto->displayName;
        $voucher->internal_name                      = $dto->internalName;
        $voucher->description                        = $dto->description;
        $voucher->code                               = $dto->code;
        $voucher->amount                             = $dto->amount;
        $voucher->amount_type                        = $dto->amountType;
        $voucher->max_claims                         = $dto->maxClaims;
        $voucher->billing_period                     = $dto->billingPeriod;
        $voucher->contract_period                    = $dto->contractPeriod;
        $voucher->expiration_date                    = $dto->expirationDate;
        $voucher->apply_with_discount                = $dto->applyWithDiscount;
        $voucher->allow_multiple_claims_same_customer = $dto->allowMultipleClaimsSameCustomer;
        $voucher->product_group_uuid                 = $productGroup->uuid;
        $voucher->product_uuid                       = $dto->productSlug !== null ? Product::where('slug', $dto->productSlug)->firstOrFail()->uuid : null;
        $voucher->save();

        return $voucher;
    }

    public function checkVoucher(Voucher $voucher, Customer $customer): bool
    {
        if ($this->isExpired($voucher)) {
            return false;
        }

        if ($this->noClaimsLeft($voucher)) {
            return false;
        }

        if ($this->cantBeClaimedAgain($voucher, $customer)) {
            return false;
        }

        return true;
    }

    public function claimVoucher(int $voucherId, int $orderLineItemId, int $amount): void
    {
        $claim = new VoucherClaim();
        $claim->voucher_id = $voucherId;
        $claim->order_line_item_id = $orderLineItemId;
        $claim->amount_claimed = $amount;
        $claim->save();

        if ($claim->voucher->max_claims !== null) {
            $claim->voucher->max_claims--;
            $claim->voucher->save();
        }
    }

    public function isExpired(Voucher $voucher): bool
    {
        return $voucher->expiration_date !== null && $voucher->expiration_date < CarbonImmutable::now();
    }

    public function noClaimsLeft(Voucher $voucher): bool
    {
        return $voucher->max_claims === 0;
    }

    public function cantBeClaimedAgain(Voucher $voucher, Customer $customer): bool
    {
        return $this->voucherRepository->hasCustomerClaimedVoucher($voucher->id, $customer->id) && ! $voucher->allow_multiple_claims_same_customer;
    }
}
