<?php

declare(strict_types=1);

namespace Waterfront\Domain\Cart\DTO;

use Symfony\Component\Serializer\Attribute\SerializedName;

class VoucherInformation
{
    public function __construct(
        public string $code,
        #[SerializedName('claimed_amount')]
        public int $claimedAmount,
        public bool $valid,
        public ?string $description,
        public ?string $name,
        public ?string $message,
    ) {
    }
}
