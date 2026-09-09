<?php

declare(strict_types=1);

namespace Waterfront\Domain\Voucher\DTO;

use Carbon\CarbonImmutable;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Voucher\Enum\VoucherAmountType;

readonly class VoucherDTO
{
    public function __construct(
        public string $displayName,
        public string $internalName,
        public ?string $description,
        public string $code,
        public int $amount,
        public VoucherAmountType $amountType,
        public ?int $maxClaims,
        public ?int $billingPeriod,
        public ?int $contractPeriod,
        public ?CarbonImmutable $expirationDate,
        public bool $applyWithDiscount,
        public bool $allowMultipleClaimsSameCustomer,
        public ?string $productSlug,
        public ?ProductGroupType $productGroupSlug,
    ) {
    }
}
