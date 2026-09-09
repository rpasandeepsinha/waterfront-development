<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Actions;

use Carbon\CarbonImmutable;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Customers\DTO\PhoneDTO;
use Waterfront\Domain\Customers\Enums\PaymentType;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Notes\Actions\StoreNoteAction;
use Webmozart\Assert\Assert;

class StoreCustomerAction
{
    public function __construct(private readonly StoreNoteAction $storeNoteAction)
    {
    }

    public function execute(
        UuidInterface $customerUuid,
        string $firstName,
        string $lastName,
        string $email,
        string $gender,
        ?PhoneDTO $phone,
        string $locale,
        PaymentType $paymentType,
        ?string $organization,
        ?string $department,
        ?string $cocNumber,
        ?string $vat_number,
        int $paymentTerms,
        ?string $purchaseReference = null,
        int $creditLimit = 0,
        ?string $note = null,
        ?CarbonImmutable $customerSince = null,
        ?CarbonImmutable $dataLastConfirmedAt = null,
    ): Customer {
        Assert::greaterThan($paymentTerms, 0);

        $customer = new Customer();
        $customer->uuid = $customerUuid;
        $customer->first_name = $firstName;
        $customer->last_name = $lastName;
        $customer->email = $email;
        $customer->locale = $locale;
        $customer->gender = $gender;
        $customer->credit_limit = $creditLimit;
        $customer->purchase_reference = $purchaseReference;
        $customer->terms_of_payment = $paymentTerms;
        $customer->payment_type = $paymentType;
        $customer->data_last_confirmed_at = $dataLastConfirmedAt;

        if ($phone instanceof PhoneDTO) {
            $customer->phone_country_code = $phone->getCountryCode();
            $customer->phone_area_code = $phone->getAreaCode();
            $customer->phone_subscriber_number = $phone->getNumber();
        } else {
            $customer->phone_country_code = '';
            $customer->phone_area_code = '';
            $customer->phone_subscriber_number = '';
        }

        $customer->organization = $organization;
        $customer->department = $department;
        $customer->coc_number = $cocNumber;
        $customer->vat_number = $vat_number;
        $customer->customer_since = $customerSince ?? CarbonImmutable::now();
        $customer->save();

        if ($note !== null) {
            $this->storeNoteAction->execute($note, $customer);
        }

        return $customer;
    }
}
