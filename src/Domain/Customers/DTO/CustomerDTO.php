<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\DTO;

use Carbon\CarbonImmutable;
use Waterfront\Domain\Customers\Enums\Gender;
use Waterfront\Domain\Customers\Enums\Locale;
use Waterfront\Domain\Customers\Enums\PaymentType;

readonly class CustomerDTO
{
    /**
     * @param array<int, DnsTemplateDTO>          $dnsTemplates
     * @param array<int, non-empty-string>        $labels
     * @param array<int, ProductGroupDiscountDTO> $productGroupDiscounts
     */
    public function __construct(
        public string $firstName,
        public string $lastName,
        public Gender $gender,
        public string $email,
        public string $phone,
        public Locale $language,
        public AddressSetDTO $addresses,
        public ContactSetDTO $contacts,
        public ?string $department,
        public ?string $organization,
        public ?string $cocNumber,
        public ?string $vatNumber,
        public int $creditLimit,
        public ?string $purchaseReference,
        public int $paymentTerms,
        public string $buName,
        public string $buCustomerNumber,
        public string $groupType,
        public PaymentType $paymentType,
        public ?string $internalNote,
        public ?int $walletCreditBalance,
        public DiscountSetDTO $discounts,
        public array $productGroupDiscounts,
        public MandateSetDTO $mandates,
        public array $dnsTemplates,
        public array $labels,
        public ?CarbonImmutable $customerSince,
    ) {
    }
}
