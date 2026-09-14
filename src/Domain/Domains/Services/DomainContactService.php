<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Services;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Domains\DTO\HandleParameters;
use Waterfront\Domain\Domains\DTO\RetrieveCustomerResponse;
use Waterfront\Domain\Domains\Models\DomainContact;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Domains\Repositories\DomainContactRepository;

class DomainContactService
{
    public function __construct(
        private readonly DomainContactRepository $domainContactRepository,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    public function findOrCreateDefaultOwner(Customer $customer): DomainContact
    {
        $address = $customer->address;

        if ($address === null) {
            throw new InvalidArgumentException('Cannot create default owner for customer without address.');
        }

        $defaultOwner = $customer->domainContacts()->where('default_owner', true)->first();

        if (! $defaultOwner instanceof DomainContact) {
            $defaultOwner = $this->domainContactRepository->createOrFindDomainContact(
                $customer->email,
                $customer->first_name,
                $customer->last_name,
                $customer->phone_country_code,
                $customer->phone_area_code,
                $customer->phone_subscriber_number,
                $customer->organization,
                $address->street_name,
                $address->street_number,
                $address->zip_code,
                $address->city,
                $customer->id,
                $address->country_code,
            );

            Log::info(sprintf(
                'Created new default domain contact %d',
                $defaultOwner->id,
            ));
        }

        return $defaultOwner;
    }

    public function createContactOwnerFromRemoteCustomerContact(
        DomainDeployment $domainDeployment,
        RetrieveCustomerResponse $remoteCustomerContact,
    ): void {
        $customer = $domainDeployment->subscription->customer;
        $parameters = HandleParameters::createFromRetrieveCustomerResponse($remoteCustomerContact, $customer);

        $domainContact = $this->domainContactRepository->createOrFindDomainContact(
            email: $parameters->getEmail(),
            firstName: $parameters->getFirstName(),
            lastName: $parameters->getLastName(),
            phoneCountryCode: $parameters->getPhoneCountryCode(),
            areaCode: $parameters->getPhoneAreaCode(),
            subscriberNumber: $parameters->getPhoneSubscriberNumber(),
            organization: $parameters->getCompanyName(),
            streetName: $parameters->getAddressStreet(),
            streetNumber: $parameters->getAddressNumber(),
            zipCode: $parameters->getAddressZipcode(),
            city: $parameters->getAddressCity(),
            customerId: $customer->id,
            countryCode: $parameters->getAddressCountry(),
        );

        $defaultOwnerAlreadyExists = $customer->domainContacts()->where('default_owner', true)->exists();

        if (! $defaultOwnerAlreadyExists) {
            $domainContact->default_owner = true;
        }

        $domainContact->save();

        $domainContact->providers()->attach($domainDeployment->provider, [
            'external_contact' => $remoteCustomerContact->getHandle(),
            'domain_business_unit_id' => $domainDeployment->domain_business_unit_id,
        ]);

        $domainDeployment->contactOwner()->associate($domainContact);
        $domainDeployment->save();
    }
}
