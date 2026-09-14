<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Actions\Customers;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\Eloquent\Builder;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Customers\Actions\StoreCustomerAction;
use Waterfront\Domain\Customers\Actions\StoreCustomerAddressAction;
use Waterfront\Domain\Customers\Actions\StoreCustomerContactAction;
use Waterfront\Domain\Customers\Actions\StoreCustomerWalletAction;
use Waterfront\Domain\Customers\Actions\StoreProductGroupDiscountAction;
use Waterfront\Domain\Customers\Actions\StoreProductsDiscountAction;
use Waterfront\Domain\Customers\DTO\CustomerDTO;
use Waterfront\Domain\Customers\DTO\PhoneDTO;
use Waterfront\Domain\Customers\Exceptions\InvalidWalletCreditBalanceException;
use Waterfront\Domain\Customers\Exceptions\MandateTypeNotSupportedException;
use Waterfront\Domain\Customers\Exceptions\StoreCustomerAddressNoExistingCustomerException;
use Waterfront\Domain\Customers\Jobs\UpdateCustomerVatRate;
use Waterfront\Domain\Customers\Models\Customer as CustomerModel;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Ferry\Actions\DNS\StoreMigratedDnsTemplateAction;
use Waterfront\Domain\Ferry\Exceptions\StoreMigratedCustomerNoExistingCustomerException;
use Waterfront\Domain\Ferry\Jobs\CreateDirectDebitMandateJob;
use Waterfront\Domain\Ferry\Repositories\MigrationCustomerRepository;
use Waterfront\Domain\Subscriptions\Services\LabelService;
use Waterfront\Infra\MollieClient\DTO\Mandates\MollieMandateDirectDebitCreateDTO;

class StoreMigratedCustomerAction
{
    public function __construct(
        private readonly StoreCustomerAction $storeCustomerAction,
        private readonly StoreCustomerAddressAction $storeCustomerAddressAction,
        private readonly StoreCustomerContactAction $storeCustomerContactAction,
        private readonly StoreCustomerWalletAction $storeCustomerWalletAction,
        private readonly StoreProductsDiscountAction $storeProductsDiscountAction,
        private readonly StoreProductGroupDiscountAction $storeProductGroupDiscountAction,
        private readonly StoreMigratedDnsTemplateAction $storeMigratedDnsTemplateAction,
        private readonly MigrationCustomerRepository $migrationCustomerRepository,
        private readonly LabelService $labelService,
        private readonly Dispatcher $dispatcher,
    ) {
    }

    /**
     * @throws InvalidWalletCreditBalanceException
     * @throws StoreCustomerAddressNoExistingCustomerException
     * @throws StoreMigratedCustomerNoExistingCustomerException
     * @throws MandateTypeNotSupportedException
     *
     * @return array<string,int|string|array<array<string,int|string>>>
     */
    public function execute(CustomerDTO $customer): array
    {
        $customerModel = $this->findOrCreateCustomer($customer);

        $migratedCustomer = $this->findOrCreateMigratedCustomerRecord($customerModel, $customer);

        if (is_int($customer->walletCreditBalance)) {
            $this->storeCustomerWalletAction->execute($customerModel, $customer->walletCreditBalance);
        }

        foreach ($customer->addresses as $address) {
            $this->storeCustomerAddressAction->execute(
                customer: $customerModel,
                streetName: $address->streetName,
                streetNumber: $address->streetNumber,
                streetNumberAddition: $address->streetNumberAddition,
                zipCode: $address->zipCode,
                city: $address->city,
                countryCode: $address->countryCode,
                type: $address->type,
            );
        }

        foreach ($customer->contacts as $contact) {
            $this->storeCustomerContactAction->execute(
                customer: $customerModel,
                type: $contact->type,
                email: $contact->email,
                firstName: $contact->firstName,
                lastName: $contact->lastName,
                company: $contact->company,
            );
        }

        $this->labelService->createLabels(
            values: $customer->labels,
            customer: $customerModel,
        );

        foreach ($customer->discounts as $discount) {
            $this->storeProductsDiscountAction->execute($discount, $customerModel);
        }

        foreach ($customer->productGroupDiscounts as $productGroupDiscount) {
            $this->storeProductGroupDiscountAction->execute($productGroupDiscount, $customerModel);
        }

        foreach ($customer->mandates as $mandateDTO) {
            $job = match ($mandateDTO::class) {
                MollieMandateDirectDebitCreateDTO::class => new CreateDirectDebitMandateJob(
                    customer: $customerModel,
                    mollieMandateDirectDebitCreateDTO: $mandateDTO,
                    migratedCustomer: $migratedCustomer,
                ),
                default => throw new MandateTypeNotSupportedException($mandateDTO->getMethod()->value),
            };

            $this->dispatcher->dispatch($job);
        }

        foreach ($customer->dnsTemplates as $dnsTemplate) {
            $this->storeMigratedDnsTemplateAction->execute($dnsTemplate, $customerModel, $migratedCustomer);
        }

        if ($customerModel->vat_rate === null) {
            $this->dispatcher->dispatch(new UpdateCustomerVatRate($customerModel));
        }

        $migratedCustomer->update(['administrative_successful' => true]);

        return [
            'customerId' => $customerModel->id,
            'customerNumber' => $customerModel->customer_number,
            'referenceCustomerId' => $customer->buCustomerNumber,
        ];
    }

    private function findOrCreateCustomer(CustomerDTO $customer): CustomerModel
    {
        // Check based on migrated customer and e-mail
        $existingCustomerBasedOnMigration = CustomerModel::query()
            ->where('email', 'ilike', $customer->email)
            ->whereHas('migratedCustomers', function (Builder $query) use ($customer) {
                $query->where('reference_customer_number', $customer->buCustomerNumber)->where(
                    'reference_name',
                    'ilike',
                    $customer->buName,
                );
            })
            ->first();

        if ($existingCustomerBasedOnMigration instanceof CustomerModel) {
            return $existingCustomerBasedOnMigration;
        }

        // Create a new customer
        return $this->storeCustomerAction->execute(
            customerUuid: Uuid::uuid4(),
            firstName: $customer->firstName,
            lastName: $customer->lastName,
            email: $customer->email,
            gender: $customer->gender->value,
            phone: new PhoneDTO($customer->phone),
            locale: $customer->language->value,
            paymentType: $customer->paymentType,
            organization: $customer->organization,
            department: $customer->department,
            cocNumber: $customer->cocNumber,
            vat_number: $customer->vatNumber,
            paymentTerms: $customer->paymentTerms,
            purchaseReference: $customer->purchaseReference,
            creditLimit: $customer->creditLimit,
            note: $customer->internalNote,
            customerSince: $customer->customerSince,
        );
    }

    private function findOrCreateMigratedCustomerRecord(
        CustomerModel $customerModel,
        CustomerDTO $customer,
    ): MigratedCustomer {
        if ($customerModel->id === null) {
            throw new StoreMigratedCustomerNoExistingCustomerException(
                $customerModel->name,
                $customer->buCustomerNumber,
            );
        }

        $existingMigratedCustomer = $this->migrationCustomerRepository->findMigratedCustomerByCustomerNumberAndBU(
            customerNumber: $customer->buCustomerNumber,
            buName: $customer->buName,
        );

        if ($existingMigratedCustomer instanceof MigratedCustomer) {
            $existingMigratedCustomer->customers()->syncWithoutDetaching($customerModel);

            return $existingMigratedCustomer;
        }

        $migratedCustomer = new MigratedCustomer();
        $migratedCustomer->reference_customer_number = $customer->buCustomerNumber;
        $migratedCustomer->reference_name = $customer->buName;
        $migratedCustomer->group_type = $customer->groupType;
        $migratedCustomer->successful = false;
        $migratedCustomer->save();
        $migratedCustomer->customers()->attach($customerModel);

        return $migratedCustomer;
    }
}
