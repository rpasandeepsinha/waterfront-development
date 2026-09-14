<?php

declare(strict_types=1);

namespace Waterfront\Domain\Admin\Actions;

use Carbon\CarbonImmutable;
use JsonException;
use Waterfront\Apps\API\Compass\Exceptions\AnonymizeCustomerException;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\CustomerAddress;
use Waterfront\Domain\Invoices\Repositories\InvoiceRepository;
use Waterfront\Domain\Lighthouse\Exceptions\ResourceNotFoundException;
use Waterfront\Domain\Marketing\Crm;
use Waterfront\Domain\Orders\Repositories\OrderRepository;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;

class AnonymizeCustomerAction
{
    public function __construct(
        private readonly Crm $crm,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly AnonymizeIdentitiesForCustomerAction $anonymizeIdentitiesForCustomerAction,
        private readonly OrderRepository $orderRepository,
        private readonly AnonymizeEmailHistoryForReceiverUuidAction $anonymizeEmailHistoryForReceiverUuidAction,
        private readonly InvoiceRepository $invoiceRepository,
    ) {
    }

    /**
     * @throws AnonymizeCustomerException
     * @throws JsonException
     */
    public function execute(Customer $customer): void
    {
        if ($customer->anonymized_at !== null) {
            throw AnonymizeCustomerException::alreadyAnonymized($customer);
        }

        if ($customer->activeSubscriptions()->count() >= 1) {
            throw AnonymizeCustomerException::stillHasActiveSubscriptions($customer);
        }

        if ($this->subscriptionRepository->getCancelledSubscriptionsForCustomer($customer)->count() >= 1) {
            throw AnonymizeCustomerException::stillHasCancelledSubscriptions($customer);
        }

        if ($this->orderRepository->hasOrdersThatPreventAnonymization($customer)) {
            throw AnonymizeCustomerException::stillHasOpenOrders($customer);
        }

        if ($this->invoiceRepository->getOpenInvoiceAmount($customer) >= 1) {
            throw AnonymizeCustomerException::stillHasUnpaidInvoiceAmount($customer);
        }

        try {
            $this->anonymizeIdentitiesForCustomerAction->execute($customer->customer_number);
        } catch (ResourceNotFoundException) {
            // Customer has no related identities, we can ignore this.
            // @ignoreException
        }

        $anonymizedCustomer = $this->anonymizeCustomerData($customer);

        if ($customer->address !== null) {
            $anonymizedCustomerAddress = $this->anonymizeCustomerAddress($customer->address);
            $anonymizedCustomerAddress->saveQuietly();
        }

        $anonymizedCustomer->anonymized_at = CarbonImmutable::now();
        $anonymizedCustomer->saveQuietly();

        $this->anonymizeEmailHistoryForReceiverUuidAction->execute($customer->uuid);

        $this->crm->anonymizeCustomer($anonymizedCustomer);
    }

    private function anonymizeCustomerData(Customer $customer): Customer
    {
        $customer->first_name = "anonymized-first_name-{$customer->customer_number}";
        $customer->last_name = "anonymized-last_name-{$customer->customer_number}";
        $customer->email = "anonymized.customer.{$customer->customer_number}@sandwave.io";
        $customer->organization = 'anonymized-organisation';
        $customer->department = 'anonymized-department';
        $customer->phone_country_code = '31';
        $customer->phone_area_code = '6';
        $customer->phone_subscriber_number = '12345678';
        $customer->coc_number = '1234567890';
        $customer->vat_number = '1234567890';

        return $customer;
    }

    private function anonymizeCustomerAddress(CustomerAddress $customerAddress): CustomerAddress
    {
        $customerAddress->street_name = 'anonymized-street';
        $customerAddress->street_number = '1';
        $customerAddress->street_number_addition = 'A';
        $customerAddress->zip_code = '1234AB';
        $customerAddress->city = 'anonymized-city';
        $customerAddress->country_code = 'NL';

        return $customerAddress;
    }
}
