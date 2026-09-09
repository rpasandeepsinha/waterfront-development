<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Services;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Config;
use libphonenumber\NumberParseException;
use Psr\Log\LoggerInterface;
use RealtimeRegister\Domain\CertificateCollection;
use RealtimeRegister\Domain\Enum\DomainContactRoleEnum;
use RealtimeRegister\RealtimeRegister;
use Throwable;
use Waterfront\Domain\Customers\DTO\PhoneDTO;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\DTO\RetrieveCustomerResponse;
use Waterfront\Domain\Domains\Exceptions\DomainContactException;
use Waterfront\Domain\Domains\Models\DomainContact;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Domains\Models\DomainProviderBusinessUnit;
use Waterfront\Domain\Domains\Repositories\DomainContactRepository;
use Waterfront\Domain\Domains\Repositories\DomainProviderBusinessUnitRepository;
use Waterfront\Domain\Ferry\Dto\Domains\TechnicalMigrationDomain;
use Waterfront\Domain\Ferry\Exceptions\DomainBusinessUnitNotFoundException;
use Waterfront\Domain\Ferry\Exceptions\DomainContactHandleException;
use Waterfront\Domain\Ferry\Exceptions\NoCredentialsForDomainBusinessUnitException;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\RtrClient\Exceptions\ContactDoesNotExistException;
use Waterfront\Support\Enums\LoggingContextKeys;

class DomainAndSslMigrationService
{
    public function __construct(
        private readonly DomainService $domainService,
        private readonly LoggerInterface $logger,
        private readonly RealtimeRegister $realtimeRegister,
        private readonly DomainProviderBusinessUnitRepository $domainBuRepository,
        private readonly DomainContactRepository $domainContactRepository,
    ) {
    }

    public function listRtrSslCertificates(string $domain): CertificateCollection
    {
        return $this->realtimeRegister->certificates->listCertificates(
            parameters:
            [
                'domainName:eq' => $domain,
            ]
        );
    }

    /**
     * @throws ContactDoesNotExistException
     * @throws DomainContactException
     * @throws DomainContactHandleException
     *
     * @see https://yh-jira.atlassian.net/browse/SWD-8464
     */
    public function createMigratedDomainContact(
        MigratedCustomer $migratedCustomer,
        Customer $customer,
        Subscription $subscription,
        Provider $domainProvider,
        TechnicalMigrationDomain $migrationDomain,
    ): DomainContact {
        // We always need to have a single owner contact at the end of this process.
        $ownerHandle = $migrationDomain->domainDetails->registrant;

        // Ensure we have the latest data of our subscription when dealing with the contact handles
        $subscription->load('domainDeployment.businessUnit');

        $ownerContact = $this->findDomainContactByHandle(
            customer: $customer,
            providerSlug: $domainProvider->slug,
            handle: $ownerHandle,
            businessUnitId: $subscription->domainDeployment?->businessUnit?->id,
        );

        $ownerContact ??= $this->createDomainContactWithHandle(
            customer: $customer,
            migratedCustomer: $migratedCustomer,
            migrationDomain: $migrationDomain,
            subscription: $subscription,
            domainProvider: $domainProvider,
            handleId: $ownerHandle,
            role: DomainContactRoleEnum::ROLE_REGISTRANT,
        );

        //        if ($migrationDomain->domainDetails->contacts !== null) {
        //            foreach ($migrationDomain->domainDetails->contacts->entities as $contact) {
        //                $handleId = $contact->handle;
        //                $role = $contact->role;
        //
        //                if ($this->handleAlreadyExists(
        //                    customer: $customer,
        //                    providerSlug: $domainProvider->slug,
        //                    handle: $handleId
        //                )) {
        //                    continue;
        //                }
        //
        //                $this->createDomainContactWithHandle(
        //                    customer: $customer,
        //                    migratedCustomer: $migratedCustomer,
        //                    migrationDomain: $migrationDomain,
        //                    subscription: $subscription,
        //                    domainProvider: $domainProvider,
        //                    handleId: $handleId,
        //                    role: $role,
        //                );
        //            }
        //        }

        return $ownerContact;
    }

    /**
     * @throws NumberParseException
     */
    public function createDomainContactFromCustomerHandle(
        RetrieveCustomerResponse $customerHandle,
        Customer $customer,
        string $role,
    ): DomainContact {
        // fetch contact based the handle retrieveCustomerHandleForRegistrant in a separate service
        [$phoneCountryCode, $phoneAreaCode, $phoneSubscriberNumber] = $this->parseRemotePhone($customerHandle);

        $domainContact = $this->domainContactRepository->createOrFindDomainContact(
            email: $customerHandle->getEmail(),
            firstName: $customerHandle->getFirstName(),
            lastName: $customerHandle->getLastName(),
            phoneCountryCode: $phoneCountryCode,
            areaCode: $phoneAreaCode,
            subscriberNumber: $phoneSubscriberNumber,
            organization: $customerHandle->getOrganization(),
            streetName: $customerHandle->getStreet() ?? '',
            streetNumber: $customerHandle->getStreetNumber() ?? '',
            zipCode: $customerHandle->getZip() ?? '',
            city: $customerHandle->getCity() ?? '',
            customerId: $customer->id,
            countryCode: $customer->address->country_code ?? 'NL'
        );

        $domainContact->default_owner = false;
        if ($role === DomainContactRoleEnum::ROLE_REGISTRANT && ! $this->customerAlreadyHasDefaultOwner($customer)) {
            $domainContact->default_owner = true;
        }

        return $domainContact;
    }

    public function attachOwnerToDeployment(DomainContact $domainContact, DomainDeployment $domainDeployment): void
    {
        $domainDeployment->contactOwner()->associate($domainContact);
        $domainDeployment->save();
    }

    public function attachProviderToDeployment(Provider $domainProvider, DomainDeployment $domainDeployment): void
    {
        $domainDeployment->provider()->associate($domainProvider);
        $domainDeployment->save();
    }

    /**
     * @throws DomainBusinessUnitNotFoundException
     * @throws NoCredentialsForDomainBusinessUnitException
     */
    public function attachBusinessUnitToDomainDeployment(DomainDeployment $deployment, string $businessUnitSlug, ?ProviderSlug $providerSlug = null): bool
    {
        $businessUnit = $this->getProviderBusinessUnit($businessUnitSlug, $providerSlug ?? $deployment->provider->slug);

        $deployment->businessUnit()->associate($businessUnit);
        return $deployment->save();
    }

    /**
     * @throws NumberParseException
     *
     * @return string[]
     */
    public function parseRemotePhone(RetrieveCustomerResponse $customerHandle): array
    {
        try {
            $phone = new PhoneDTO($customerHandle->getPhone());

            $phoneCountryCode = $phone->getCountryCode();
            $phoneAreaCode = $phone->getAreaCode();
            $phoneSubscriberNumber = $phone->getNumber();
        } catch (NumberParseException) {
            // Regex from: https://dm.realtimeregister.com/docs/api/contacts/create
            if ((bool) preg_match('/\+[0-9]{1,3}\.[0-9]{1,14}/', $customerHandle->getPhone())) {
                $phoneNumberExploded = explode('.', $customerHandle->getPhone());

                $phoneCountryCode = str_replace('+', '', $phoneNumberExploded[0]);
                $phoneAreaCode = substr($phoneNumberExploded[1], 0, 3);
                $phoneSubscriberNumber = substr($phoneNumberExploded[1], 3);
            } else {
                $phone = new PhoneDTO(Config::string('bu.phone_number'));

                $phoneCountryCode = $phone->getCountryCode();
                $phoneAreaCode = $phone->getAreaCode();
                $phoneSubscriberNumber = $phone->getNumber();
            }
        }
        return [
            $phoneCountryCode,
            $phoneAreaCode,
            $phoneSubscriberNumber,
        ];
    }

    /**
     * @throws DomainBusinessUnitNotFoundException
     * @throws NoCredentialsForDomainBusinessUnitException
     */
    public function getProviderBusinessUnit(string $businessUnitSlug, ProviderSlug $providerSlug): DomainProviderBusinessUnit
    {
        try {
            $businessUnit = $this->domainBuRepository->findBySlug($businessUnitSlug);
        } catch (ModelNotFoundException $exception) {
            throw new DomainBusinessUnitNotFoundException(
                businessUnitSlug: $businessUnitSlug,
                previous: $exception
            );
        }

        if ($providerSlug !== ProviderSlug::OPEN_PROVIDER && $providerSlug !== ProviderSlug::REALTIME_REGISTER) {
            throw new NoCredentialsForDomainBusinessUnitException(
                businessUnitSlug: $businessUnitSlug,
                providerSlug: $providerSlug,
            );
        }

        $hasCredentials = $providerSlug === ProviderSlug::REALTIME_REGISTER
            ? $this->domainBuRepository->hasRealtimeRegisterCredentials($businessUnit->id)
            : $this->domainBuRepository->hasOpenProviderCredentials($businessUnit->id);

        if (! $hasCredentials) {
            throw new NoCredentialsForDomainBusinessUnitException(
                businessUnitSlug: $businessUnitSlug,
                providerSlug: $providerSlug,
            );
        }

        return $businessUnit;
    }

    /**
     * @throws NumberParseException
     * @throws DomainContactHandleException
     */
    private function createDomainContactWithHandle(
        Customer $customer,
        MigratedCustomer $migratedCustomer,
        TechnicalMigrationDomain $migrationDomain,
        Subscription $subscription,
        Provider $domainProvider,
        string $handleId,
        string $role
    ): DomainContact {
        try {
            $customerHandle = $this->domainService->retrieveContactHandle($handleId, $domainProvider->slug, $subscription->domainDeployment?->businessUnit);
        } catch (Throwable $exception) {
            $this->logger->error(
                'Unable to fetch remote contact',
                [
                    LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                    LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                    LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $migratedCustomer->reference_customer_number,
                    LoggingContextKeys::PROVISIONING_PROVIDER => $domainProvider->slug->value,
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::META => [
                        'handle' => $handleId,
                    ],
                ]
            );

            throw new DomainContactHandleException(
                sprintf(
                    'Unable to fetch remote contact for handle: %s for domain %s message: %s',
                    $handleId,
                    $migrationDomain->domainDetails->domainName,
                    $exception->getMessage()
                ),
                $exception->getCode(),
                $exception
            );
        }

        $domainContact = $this->createDomainContactFromCustomerHandle(
            customerHandle: $customerHandle,
            customer: $customer,
            role: $role,
        );

        $businessUnitId = $subscription->domainDeployment?->domain_business_unit_id;
        return $this->saveAndAttachDomainContact($domainContact, $domainProvider, $handleId, $businessUnitId);
    }

    private function customerAlreadyHasDefaultOwner(Customer $customer): bool
    {
        return $customer->domainContacts()->where('default_owner', true)->exists();
    }

    //    private function handleAlreadyExists(Customer $customer, ProviderSlug $providerSlug, string $handle): bool
    //    {
    //        // Making a active choice here to run multiple queries here instead of optimization for simpler code since there is no risk
    //        // for thousands of contacts. (we are expecting 3 ish at most per domain)
    //        return $this->findDomainContactByHandle(customer: $customer, providerSlug: $providerSlug, handle: $handle) !== null;
    //    }

    private function findDomainContactByHandle(Customer $customer, ProviderSlug $providerSlug, string $handle, ?int $businessUnitId): DomainContact|null
    {
        $customer->refresh();
        $contacts = $customer->domainContacts;

        // Making an active choice here to run multiple queries here instead of optimization for simpler code since there is no risk
        // for thousands of contacts. (we are expecting 3 ish at most per domain)
        return $contacts->filter(fn (DomainContact $contact): bool => $contact->providers()
            ->where('slug', $providerSlug)
            ->where('type', ProviderType::DOMAIN)
            ->wherePivot('external_contact', $handle)
            ->wherePivot('domain_business_unit_id', $businessUnitId)
            ->exists())->first();
    }

    /**
     * We need to have a separate method to save and attach a domain contact
     * so that the Migration dry runs can call the `createDomainContactFromCustomerHandle`
     * without database interaction.
     */
    private function saveAndAttachDomainContact(DomainContact $domainContact, Provider $domainProvider, string $handleId, ?int $businessUnitId): DomainContact
    {
        $domainContact->save();

        $domainContact->providers()->attach($domainProvider, [
            'external_contact' => $handleId,
            'domain_business_unit_id' => $businessUnitId,
        ]);

        return $domainContact;
    }
}
