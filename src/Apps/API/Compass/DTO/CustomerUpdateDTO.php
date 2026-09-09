<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\DTO;

use Waterfront\Domain\Customers\DTO\PhoneDTO;
use Waterfront\Domain\Customers\Enums\Gender;
use Waterfront\Domain\Customers\Enums\PaymentType;
use Waterfront\Domain\Customers\Models\Customer;

readonly class CustomerUpdateDTO
{
    public function __construct(
        public Customer $customer,
        public string $firstName,
        public string $lastName,
        public PhoneDTO $phoneNumber,
        public Gender $gender,
        public string $email,
        public PaymentType $paymentType,
        public string $street_name,
        public string $street_number,
        public ?string $street_number_addition,
        public string $countryCode,
        public string $zip_code,
        public string $city,
        public int $paymentTerm,
        public int $creditLimit,
        public ?string $organization,
        public ?string $department,
        public ?string $cocNumber,
        public ?string $vatNumber,
    ) {
    }
}
