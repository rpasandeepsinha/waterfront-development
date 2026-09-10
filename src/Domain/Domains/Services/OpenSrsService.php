<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Services;

use Carbon\CarbonImmutable;
use DateTime;
use Exception;
use Illuminate\Support\Arr;
use LogicException;
use Psr\Log\LoggerInterface;
use RealtimeRegister\Domain\Enum\DomainStatusEnum;
use RealtimeRegister\Domain\KeyDataCollection;
use RuntimeException;
use Throwable;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\Domains\DTO\CheckResult;
use Waterfront\Domain\Domains\DTO\DestroyContactResult;
use Waterfront\Domain\Domains\DTO\DomainDetailsDTO;
use Waterfront\Domain\Domains\DTO\HandleParameters;
use Waterfront\Domain\Domains\DTO\Handles;
use Waterfront\Domain\Domains\DTO\ModifyParameters;
use Waterfront\Domain\Domains\DTO\RegistrationParameters;
use Waterfront\Domain\Domains\DTO\RegistrationResult;
use Waterfront\Domain\Domains\DTO\RetrieveCustomerResponse;
use Waterfront\Domain\Domains\DTO\RetrieveResult;
use Waterfront\Domain\Domains\DTO\TransferParameters;
use Waterfront\Domain\Domains\DTO\TransferResult;
use Waterfront\Domain\Domains\Exceptions\DomainModificationFailedException;
use Waterfront\Domain\Domains\Interfaces\DomainDriverInterface;
use Waterfront\Domain\Domains\Interfaces\HandleInterface;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\OpenSrsClient\OpenSrsClient;
use Waterfront\Infra\PowerDnsClient\Entities\PowerDnsSecKey;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Exceptions\NotImplementedException;
use Webmozart\Assert\Assert;

/**
 * Domain driver backed by the OpenSRS OPS (XCP) API.
 *
 * OpenSRS carries contact data inline on every provisioning call and has no
 * standalone contact/handle lifecycle, so the contact-handle methods of the
 * interface are not supported here (see the OpenSrsClient package plan).
 */
class OpenSrsService implements DomainDriverInterface
{
    public function __construct(
        private OpenSrsClient $openSrsClient,
        private readonly DnsDeploymentRepository $dnsDeploymentRepository,
        private readonly ConfigurationInterface $configuration,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function setClient(OpenSrsClient $openSrsClient): self
    {
        $this->openSrsClient = $openSrsClient;

        return $this;
    }

    /**
     * @throws Exception
     */
    public function check(string $domain): CheckResult
    {
        if (str_starts_with($domain, 'www.')) {
            throw new RuntimeException("Not allowed to order domain $domain starting with www.");
        }

        try {
            return $this->openSrsClient->checkDomain($domain);
        } catch (Throwable $exception) {
            $this->logger->error('OpenSRS domain check failed for {domain.name}', [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::EXCEPTION => $exception,
            ]);

            throw new RuntimeException($exception->getMessage(), (int) $exception->getCode(), $exception);
        }
    }

    public function retrieveAuthCode(string $domain): ?string
    {
        try {
            return $this->openSrsClient->retrieveAuthCode($domain);
        } catch (Exception $exception) {
            $this->logger->error('OpenSRS auth code retrieval failed for {domain.name}', [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::EXCEPTION => $exception,
            ]);

            throw $exception;
        }
    }

    public function nameservers(DomainDeployment $deployment): RetrieveResult
    {
        $domain = $deployment->subscription->domain;
        Assert::notNull($domain, 'Provided deployment has no domain');

        $retrieveResult = $this->openSrsClient->retrieveDomain($domain);

        $dnsDeployment = $this->dnsDeploymentRepository->getDnsDeploymentFromDomainDeployment($deployment);
        $retrieveResult->setIsDefaultNameservers(
            $dnsDeployment !== null && $dnsDeployment->dnsNameservers->isNotEmpty()
        );

        return $retrieveResult;
    }

    public function minimalRegister(DomainDeployment $domainDeployment, Handles $handles): RegistrationResult
    {
        $subscription = $domainDeployment->subscription;
        $domain = $subscription->domain;
        Assert::notNull($domain, 'Provided subscription has no domain');

        return $this->openSrsClient->registerDomain(
            $this->registrationParameters($domain, $subscription->contract_period, $subscription->customer)
        );
    }

    public function minimalTransfer(DomainDeployment $domainDeployment, Handles $handles): TransferResult
    {
        $subscription = $domainDeployment->subscription;
        $domain = $subscription->domain;
        Assert::notNull($domain, 'Provided subscription has no domain');

        return $this->openSrsClient->transferDomain(
            $this->transferParameters($domain, $subscription->contract_period, $subscription->customer, null)
        );
    }

    public function register(
        DomainDeployment $deployment,
        int $period,
        Customer $customer,
        Handles $handles,
        bool $isPrivateWhoisEnabled = false,
        bool $dnssecEnabled = false
    ): RegistrationResult {
        $domain = $deployment->subscription->domain;
        Assert::notNull($domain, 'Provided subscription has no domain');

        try {
            $parameters = $this->registrationParameters($domain, $period, $customer, $isPrivateWhoisEnabled);

            return $this->openSrsClient->registerDomain($parameters);
        } catch (Throwable $exception) {
            throw new RuntimeException('Domain registration failed: ' . $exception->getMessage(), 1, $exception);
        }
    }

    /**
     * @param mixed[] $customer
     *
     * @throws Exception
     */
    public function transfer(
        DomainDeployment $deployment,
        int $period,
        array $customer,
        Handles $handles,
        bool $isPrivateWhoisEnabled = false,
        bool $dnssecEnabled = false,
        ?string $transferSecret = null,
    ): TransferResult {
        $domain = $deployment->subscription->domain;
        Assert::notNull($domain, 'Provided subscription has no domain');

        try {
            $data = [
                'domain'          => $domain,
                'period'          => $this->periodInYears($period),
                'customer'        => $customer,
                'nameServerGroup' => $this->configuration->getAsString('domainservice.nameservers.name-server-group'),
            ];
            if ($transferSecret !== null) {
                $data['transferSecret'] = $transferSecret;
            }
            if ($isPrivateWhoisEnabled) {
                $data['isPrivateWhoisEnabled'] = true;
            }

            return $this->openSrsClient->transferDomain(TransferParameters::create($data));
        } catch (Exception $exception) {
            throw new RuntimeException('Domain transfer failed', 1, $exception);
        }
    }

    /**
     * @param mixed[] $parameters
     *
     * @throws DomainModificationFailedException
     */
    public function modify(string $domain, array $parameters): bool
    {
        try {
            $this->openSrsClient->modifyDomain(
                ModifyParameters::create(array_merge($parameters, ['domain' => $domain]))
            );
        } catch (Throwable $exception) {
            $this->logger->error('Failed to modify a domain', [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::OPENSRS,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                LoggingContextKeys::EXCEPTION => $exception,
            ]);

            throw new DomainModificationFailedException('Domain modification failed', 1, $exception);
        }

        return true;
    }

    /**
     * @param mixed[] $handleData
     */
    public function modifyHandle(string $domain, array $handleData): bool
    {
        throw new NotImplementedException('OpenSRS does not support standalone contact modification.');
    }

    /**
     * @param Nameserver[] $nameServers
     *
     * @throws DomainModificationFailedException
     */
    public function updateNameServers(string $domain, array $nameServers): bool
    {
        try {
            $this->openSrsClient->updateNameServers($domain, $nameServers);
        } catch (Throwable $exception) {
            throw new DomainModificationFailedException('Nameserver update failed', 1, $exception);
        }

        return true;
    }

    /**
     * @throws DomainModificationFailedException
     */
    public function enablePrivateWhois(string $domain): bool
    {
        return $this->modify($domain, ['isPrivateWhoisEnabled' => true]);
    }

    /**
     * @throws DomainModificationFailedException
     */
    public function disablePrivateWhois(string $domain): bool
    {
        return $this->modify($domain, ['isPrivateWhoisEnabled' => false]);
    }

    public function linkContactHandle(string $domain, HandleInterface $handles): void
    {
        throw new NotImplementedException('OpenSRS does not support linking contact handles.');
    }

    public function createContact(HandleParameters $params): string
    {
        throw new NotImplementedException('OpenSRS does not support standalone contact creation.');
    }

    public function retrieveCustomerHandle(string $handle): RetrieveCustomerResponse
    {
        throw new NotImplementedException('OpenSRS does not expose contact handles.');
    }

    public function doesContactExist(string $handle): bool
    {
        return false;
    }

    public function destroyContact(string $externalId): DestroyContactResult
    {
        // OpenSRS has no standalone contacts to destroy.
        return new DestroyContactResult(true);
    }

    /**
     * @throws LogicException
     */
    public function retrieveRenewalDate(string $domain): CarbonImmutable
    {
        $result = $this->openSrsClient->retrieveDomain($domain);
        $dateString = $result->getExpirationDateOpenprovider() ?? $result->getExpirationDate();

        if ($dateString === null) {
            throw new LogicException(sprintf('Could not retrieve renewal date from OpenSRS for domain %s', $domain));
        }

        return CarbonImmutable::instance(new DateTime($dateString));
    }

    public function fetchDomain(string $domain): DomainDetailsDTO
    {
        $result = $this->openSrsClient->retrieveDomain($domain);

        $ownerHandle = $result->getHandles()?->getOwnerHandle();
        Assert::stringNotEmpty($ownerHandle, 'Registrant (owner handle) must not be null or empty');

        return new DomainDetailsDTO(
            domainName: $result->getDomain()?->__toString() ?? $domain,
            registrant: $ownerHandle,
            status: [DomainStatusEnum::STATUS_OK],
            autoRenew: $result->getAutoRenew() ?? true,
            autoRenewPeriod: 0,
            ns: Arr::map($result->getNameServers() ?? [], static fn (array $nameserver): string => (string) $nameserver['name']),
            premium: false,
            childHosts: [],
            privacyProtect: $result->getIsPrivateWhoisEnabled(),
            authcode: $result->getAuthCode(),
            createdDate: $result->getActiveDate() !== null ? new DateTime($result->getActiveDate()) : null,
            expiryDate: $result->getExpirationDate() !== null ? new DateTime($result->getExpirationDate()) : null,
        );
    }

    public function isDnssecSupported(string $domain): bool
    {
        return false;
    }

    public function getDomainKeyDataCollection(string $domain): ?KeyDataCollection
    {
        return null;
    }

    public function hasZone(string $domain): bool
    {
        return false;
    }

    public function hasZoneCheck(string $domain): bool
    {
        return false;
    }

    public function nameserversAreRequired(string $domain): bool
    {
        return false;
    }

    public function creationRequiresPreValidation(string $domain): bool
    {
        return false;
    }

    /**
     * @return mixed[]
     */
    public function retrieveDnssecKeys(string $domain): array
    {
        return [];
    }

    public function enableDnssec(string $domain, ?PowerDnsSecKey $key = null): bool
    {
        return false;
    }

    public function disableDnssec(string $domain): bool
    {
        return false;
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

    public function ensureContactValidatedForDomain(string $domain, string $handle): void
    {
        throw new NotImplementedException();
    }

    /**
     * @throws Exception
     */
    private function registrationParameters(
        string $domain,
        int $periodInMonths,
        Customer $customer,
        bool $isPrivateWhoisEnabled = false,
    ): RegistrationParameters {
        $dnsDeployment = $this->dnsDeploymentRepository->getDnsDeploymentFromDomain($domain);
        Assert::notNull($dnsDeployment, sprintf('No DNS deployment for domain %s', $domain));

        $parameters = RegistrationParameters::create([
            'domain'      => $domain,
            'period'      => $this->periodInYears($periodInMonths),
            'nameServers' => $this->dnsDeploymentRepository->getNameservers($dnsDeployment),
        ]);
        $parameters->setCustomer($customer->load('address')->toArray());
        $parameters->setIsPrivateWhoisEnabled($isPrivateWhoisEnabled);

        return $parameters;
    }

    /**
     * @throws Exception
     */
    private function transferParameters(
        string $domain,
        int $periodInMonths,
        Customer $customer,
        ?string $transferSecret,
    ): TransferParameters {
        $data = [
            'domain'          => $domain,
            'period'          => $this->periodInYears($periodInMonths),
            'customer'        => $customer->load('address')->toArray(),
            'nameServerGroup' => $this->configuration->getAsString('domainservice.nameservers.name-server-group'),
        ];
        if ($transferSecret !== null) {
            $data['transferSecret'] = $transferSecret;
        }

        return TransferParameters::create($data);
    }

    /**
     * OpenSRS registration periods are whole years; contract periods are stored in months.
     */
    private function periodInYears(int $periodInMonths): int
    {
        return max(1, (int) ceil($periodInMonths / 12));
    }
}
