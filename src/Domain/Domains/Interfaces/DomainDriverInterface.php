<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Interfaces;

use Carbon\CarbonImmutable;
use Exception;
use RealtimeRegister\Domain\BillableCollection;
use RealtimeRegister\Domain\DomainContactCollection;
use RealtimeRegister\Domain\KeyDataCollection;
use RealtimeRegister\Domain\Zone;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\Domains\DTO\CheckResult;
use Waterfront\Domain\Domains\DTO\DestroyContactResult;
use Waterfront\Domain\Domains\DTO\DomainDetailsDTO;
use Waterfront\Domain\Domains\DTO\HandleParameters;
use Waterfront\Domain\Domains\DTO\Handles;
use Waterfront\Domain\Domains\DTO\RegistrationResult;
use Waterfront\Domain\Domains\DTO\RetrieveCustomerResponse;
use Waterfront\Domain\Domains\DTO\RetrieveResult;
use Waterfront\Domain\Domains\DTO\TransferResult;
use Waterfront\Domain\Domains\Exceptions\ContactValidationRequiredException;
use Waterfront\Domain\Domains\Exceptions\DomainDoesNotExistException;
use Waterfront\Domain\Domains\Exceptions\DomainModificationFailedException;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Infra\PowerDnsClient\Entities\PowerDnsSecKey;
use Waterfront\Support\Exceptions\NotImplementedException;

interface DomainDriverInterface
{
    /**
     * @throws Exception
     */
    public function check(string $domain): CheckResult;

    public function linkContactHandle(string $domain, HandleInterface $handles): void;

    public function createContact(HandleParameters $params): string;

    public function isDnssecSupported(string $domain): bool;

    public function getDomainKeyDataCollection(string $domain): ?KeyDataCollection;

    /**
     * @throws Exception
     */
    public function hasZone(string $domain): bool;

    public function nameservers(DomainDeployment $deployment): RetrieveResult;

    public function retrieveCustomerHandle(string $handle): RetrieveCustomerResponse;

    public function doesContactExist(string $handle): bool;

    public function retrieveAuthCode(string $domain): ?string;

    /**
     * @param array{
     *     handle: string,
     *     name?: string|null,
     *     addressline?: array<string>|null,
     *     postalCode?: string|null,
     *     city?: string|null,
     *     country?: string|null,
     *     email?: string|null,
     *     voice?: string|null,
     *     brand?: string|null,
     *     organization?: string|null,
     *     state?: string|null,
     *     fax?: string|null,
     * } $handleData
     */
    public function modifyHandle(string $domain, array $handleData): bool;

    public function minimalRegister(DomainDeployment $domainDeployment, Handles $handles): RegistrationResult;

    public function minimalTransfer(DomainDeployment $domainDeployment, Handles $handles): TransferResult;

    public function register(
        DomainDeployment $deployment,
        int $period,
        Customer $customer,
        Handles $handles,
        bool $isPrivateWhoisEnabled = false,
        bool $dnssecEnabled = false,
    ): RegistrationResult;

    /**
     * @param mixed[] $customer
     */
    public function transfer(
        DomainDeployment $deployment,
        int $period,
        array $customer,
        Handles $handles,
        bool $isPrivateWhoisEnabled = false,
        bool $dnssecEnabled = false,
        ?string $transferSecret = null,
    ): TransferResult;

    /**
     * @param array{registrant?: string|null, isPrivateWhoisEnabled?: bool|null, period?: int|null, authCode?: string|null, languageCode?: string|null, autoRenew?: bool|null, ns?: array<string>|null, status?: array<string>|null, designatedAgent?: string|null, zone?: Zone|null, contacts?: DomainContactCollection|null, dnssecKeys?: KeyDataCollection|null, billables?: BillableCollection|null} $parameters
     */
    public function modify(string $domain, array $parameters): bool;

    /**
     * @param Nameserver[] $nameServers
     *
     * @throws DomainModificationFailedException
     */
    public function updateNameServers(string $domain, array $nameServers): bool;

    /**
     * @return mixed[]
     */
    public function retrieveDnssecKeys(string $domain): array;

    /**
     * @param PowerDnsSecKey|null $key Optional key if external zone
     *
     * @throws Exception
     */
    public function enableDnssec(string $domain, ?PowerDnsSecKey $key = null): bool;

    public function nameserversAreRequired(string $domain): bool;

    public function hasZoneCheck(string $domain): bool;

    public function creationRequiresPreValidation(string $domain): bool;

    /**
     * @return array<int, string>
     */
    public function getContactValidationCategoriesForDomain(string $domain): array;

    /**
     * @throws Exception
     */
    public function disableDnssec(string $domain): bool;

    public function enablePrivateWhois(string $domain): bool;

    public function disablePrivateWhois(string $domain): bool;

    public function destroyContact(string $externalId): DestroyContactResult;

    public function retrieveRenewalDate(string $domain): CarbonImmutable;

    /**
     * @throws DomainModificationFailedException|NotImplementedException|DomainDoesNotExistException
     */
    public function suspend(string $domain): void;

    /**
     * @throws DomainModificationFailedException|NotImplementedException
     */
    public function unsuspend(string $domain): void;

    public function restore(string $domain): void;

    public function fetchDomain(string $domain): DomainDetailsDTO;

    /**
     * Ensures the contact handle is validated for the given domain's TLD requirements.
     * If validation is not yet complete, triggers the validation process (email) and throws.
     *
     * @throws ContactValidationRequiredException
     */
    public function ensureContactValidatedForDomain(string $domain, string $handle): void;
}
