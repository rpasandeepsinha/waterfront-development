<?php

declare(strict_types=1);

namespace Waterfront\Domain\Voucher\Repository;

use Illuminate\Support\Facades\DB;
use Waterfront\Domain\Voucher\Models\Voucher;

class VoucherRepository
{
    public function doesVoucherExist(string $voucherCode): bool
    {
        return Voucher::where('code', $voucherCode)->exists();
    }

    public function findByCode(string $code): Voucher
    {
        return Voucher::where('code', $code)->firstOrFail();
    }

    public function hasCustomerClaimedVoucher(int $voucherId, int $customerId): bool
    {
        return DB::table('voucher_claims', 'vc')
            ->join('order_line_items AS ol', 'ol.id', '=', 'vc.order_line_item_id')
            ->join('orders AS o', 'o.id', '=', 'ol.order_id')
            ->where('vc.voucher_id', $voucherId)
            ->where('o.customer_id', $customerId)
            ->exists();
    }
}
