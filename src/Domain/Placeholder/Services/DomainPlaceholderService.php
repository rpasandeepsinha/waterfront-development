<?php

declare(strict_types=1);

namespace Waterfront\Domain\Placeholder\Services;

use Carbon\CarbonImmutable;
use RealtimeRegister\Domain\KeyDataCollection;
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
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Domains\Interfaces\DomainDriverInterface;
use Waterfront\Domain\Domains\Interfaces\HandleInterface;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Provision\Hosting\Exceptions\DriverNotFoundException;
use Waterfront\Infra\PowerDnsClient\Entities\PowerDnsSecKey;
use Waterfront\Support\Exceptions\NotImplementedException;

/**
 * This service mocks a OpenproviderService, so we can migrate or handle domain subscription without the technical execution part.
 */
class DomainPlaceholderService extends PlaceHolderService implements DomainDriverInterface
{
    public function retrieveAuthCode(string $domain): ?string
    {
        throw new NotImplementedException();
    }

    public function check(string $domain): CheckResult
    {
        throw new NotImplementedException();
    }

    public function hasZone(string $domain): bool
    {
        throw new NotImplementedException();
    }

    public function nameservers(DomainDeployment $deployment): RetrieveResult
    {
        throw new NotImplementedException();
    }

    public function retrieveCustomerHandle(string $handle): RetrieveCustomerResponse
    {
        throw new NotImplementedException();
    }

    public function doesContactExist(string $handle): bool
    {
        throw new NotImplementedException();
    }

    public function modifyHandle(string $domain, array $handleData): never
    {
        throw new NotImplementedException();
    }

    /**
     * @param array<mixed> $parameters
     */
    public function modify(string $domain, array $parameters): bool
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritDoc}
     */
    public function createContact(HandleParameters $params): string
    {
        return '';
    }

    /**
     * {@inheritDoc}
     */
    public function linkContactHandle(string $domain, HandleInterface $handles): void
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritDoc}
     */
    public function destroyContact(string $externalId): DestroyContactResult
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritDoc}
     *
     * @throws DriverNotFoundException
     */
    public function register(
        DomainDeployment $deployment,
        int $period,
        Customer $customer,
        Handles $handles,
        bool $isPrivateWhoisEnabled = false,
        bool $dnssecEnabled = false
    ): RegistrationResult {
        $provider = Provider::where('slug', ProviderSlug::PLACEHOLDER)->where('type', ProviderType::DOMAIN)->first();

        if ($provider === null) {
            throw new DriverNotFoundException(sprintf(
                'There was no domainprovider found with the slug %s for the subscription with uuid: %s',
                ProviderSlug::PLACEHOLDER->value,
                $deployment->subscription_uuid
            ));
        }

        $deployment->update(['provider_id' => $provider->id]);
        $subscription = $deployment->subscription;

        $provisionDetail = $this->getProvisionDetailFromSubscription($subscription);

        $this->notificationService->sendCreationNotification($provisionDetail);

        return new RegistrationResult(DomainStatus::PENDING);
    }

    public function isDnssecSupported(string $domain): bool
    {
        throw new NotImplementedException();
    }

    public function transfer(
        DomainDeployment $deployment,
        int $period,
        array $customer,
        Handles $handles,
        bool $isPrivateWhoisEnabled = false,
        bool $dnssecEnabled = false,
        ?string $transferSecret = null,
    ): TransferResult {
        throw new NotImplementedException();
    }

    public function enableDnssec(string $domain, ?PowerDnsSecKey $key = null): bool
    {
        throw new NotImplementedException();
    }

    /**
     * @param Nameserver[] $nameServers
     */
    public function updateNameServers(string $domain, array $nameServers): bool
    {
        throw new NotImplementedException();
    }

    /**
     * @return string[][]
     */
    public function retrieveDnssecKeys(string $domain): array
    {
        throw new NotImplementedException();
    }

    public function disableDnssec(string $domain): bool
    {
        throw new NotImplementedException();
    }

    public function enablePrivateWhois(string $domain): bool
    {
        throw new NotImplementedException();
    }

    public function disablePrivateWhois(string $domain): bool
    {
        throw new NotImplementedException();
    }

    public function retrieveRenewalDate(string $domain): CarbonImmutable
    {
        throw new NotImplementedException();
    }

    public function suspend(string $domain): void
    {
        throw new NotImplementedException();
    }

    public function unsuspend(string $domain): void
    {
        throw new NotImplementedException();
    }

    public function restore(string $domain): void
    {
        throw new NotImplementedException();
    }

    public function fetchDomain(string $domain): DomainDetailsDTO
    {
        throw new NotImplementedException();
    }

    /**
     * @throws DriverNotFoundException
     */
    public function minimalRegister(DomainDeployment $domainDeployment, Handles $handles): RegistrationResult
    {
        return $this->register(
            $domainDeployment,
            $domainDeployment->subscription->contract_period,
            $domainDeployment->subscription->customer,
            $handles
        );
    }

    public function minimalTransfer(DomainDeployment $domainDeployment, ?Handles $handles): TransferResult
    {
        throw new NotImplementedException();
    }

    public function getDomainKeyDataCollection(string $domain): ?KeyDataCollection
    {
        throw new NotImplementedException();
    }

    public function nameserversAreRequired(string $domain): bool
    {
        throw new NotImplementedException();
    }

    public function hasZoneCheck(string $domain): bool
    {
        throw new NotImplementedException();
    }

    public function creationRequiresPreValidation(string $domain): bool
    {
        throw new NotImplementedException();
    }

    public function ensureContactValidatedForDomain(string $domain, string $handle): void
    {
        throw new NotImplementedException();
    }
}
