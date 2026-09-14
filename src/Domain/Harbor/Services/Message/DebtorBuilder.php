<?php

declare(strict_types=1);

namespace Waterfront\Domain\Harbor\Services\Message;

use SandwaveIo\HarborMessages\Message\DebtorInvoiceLines\Debtor;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Harbor\Exceptions\InvoiceLineToHarborException;

class DebtorBuilder
{
    /**
     * Creates a new Debtor instance based on the given Customer instance.
     *
     * @throws InvoiceLineToHarborException
     */
    public function fromCustomer(Customer $customer): Debtor
    {
        $address = $customer->address ?? throw InvoiceLineToHarborException::addressNotSetForCustomer(
            $customer->name,
            $customer->id,
        );

        $customerContact = $customer->financialContact()->first();

        return new Debtor(
            email: $customerContact->email ?? $customer->email,
            customerNumber: $customer->customer_number,
            firstName: $customerContact->first_name ?? $customer->first_name,
            lastName: $customerContact->last_name ?? $customer->last_name,
            organization: $customer->organization !== '' ? $customer->organization : null,
            department: $customer->department,
            street: implode(' ', [$address->street_name, $address->street_number, $address->street_number_addition]),
            postalCode: $address->zip_code,
            city: $address->city,
            country: $address->country_code,
            phoneNumber: $customer->phone_number !== null && $customer->phone_number !== ''
                ? $this->formatPhoneNumber($customer)
                : null,
            locale: $customer->locale !== '' ? $this->formatLocale($customer->locale) : 'nl_NL',
            paymentTermDays: $customer->terms_of_payment,
            vatNumber: $customer->vat_number !== null && strlen($customer->vat_number) > 0
                ? $customer->vat_number
                : null,
            cocNumber: $customer->coc_number,
            reference: $customer->purchase_reference,
        );
    }

    private function formatPhoneNumber(Customer $customer): string
    {
        return sprintf(
            '+%s%s%s',
            preg_replace('/\s+/', '', $customer->phone_country_code),
            preg_replace('/\s+/', '', $customer->phone_area_code),
            preg_replace('/\s+/', '', $customer->phone_subscriber_number),
        );
    }

    private function formatLocale(string $locale): string
    {
        return str_replace('-', '_', $locale);
    }
}
