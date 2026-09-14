<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Services;

use Carbon\CarbonImmutable;
use DateTime;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use LogicException;
use RealtimeRegister\Domain\Enum\DomainStatusEnum;
use RealtimeRegister\Domain\KeyDataCollection;
use RuntimeException;
use Throwable;
use UnexpectedValueException;
use Waterfront\Domain\Customers\DTO\PhoneDTO;
use Waterfront\Domain\Customers\Enums\Locale;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\DNS\Exceptions\DnsDeploymentNotFoundException;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\DNS\Services\DnsNameserverAssigner;
use Waterfront\Domain\Domains\DTO\CheckResult;
use Waterfront\Domain\Domains\DTO\DestroyContactResult;
use Waterfront\Domain\Domains\DTO\Domain;
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
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Domains\Exceptions\DomainModificationFailedException;
use Waterfront\Domain\Domains\Interfaces\DomainDriverInterface;
use Waterfront\Domain\Domains\Interfaces\HandleInterface;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\OpenproviderClient\OpenproviderClient;
use Waterfront\Infra\OpenproviderClient\Services\NameServerRetriever;
use Waterfront\Infra\PowerDnsClient\Entities\PowerDnsSecKey;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Exceptions\NotImplementedException;
use Webmozart\Assert\Assert;

class OpenproviderService implements DomainDriverInterface
{
    public function __construct(
        private OpenproviderClient $openproviderClient,
        private readonly NameServerRetriever $nameServerRetriever,
        private readonly DnsService $dnsService,
        private readonly DnsNameserverAssigner $nameserverAssigner,
        private readonly ConfigurationInterface $configuration,
        private readonly DnsDeploymentRepository $dnsDeploymentRepository,
    ) {
    }

    public function retrieveAuthCode(string $domain): ?string
    {
        try {
            return $this->openproviderClient->retrieveDomain($domain)->getAuthCode();
        } catch (Exception $exception) {
            Log::error(sprintf(
                'Status code: %d, message: %s',
                $exception->getCode(),
                $exception->getMessage(),
            ));
            throw $exception;
        }
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
            return $this->openproviderClient->checkDomain($domain);
        } catch (Throwable $exception) {
            Log::error(sprintf(
                'Status code: %d, message: %s',
                $exception->getCode(),
                $exception->getMessage(),
            ));
            throw new RuntimeException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    /**
     * @throws Exception
     */
    public function hasZone(string $domain): bool
    {
        try {
            $zone = $this->dnsService->getDnsZone($domain);
        } catch (Throwable) {
            // Not really sure what it returns when no zone
            return false;
        }

        return (bool) $zone;
    }

    public function nameservers(DomainDeployment $deployment): RetrieveResult
    {
        $domain = $deployment->subscription->domain;
        Assert::notNull($domain, 'Provided deployment has no domain');

        $retrieveResult =
            $this->openproviderClient->retrieveDomain(
                $domain,
            );

        $dnsDeployment = $this->dnsDeploymentRepository->getDnsDeploymentFromDomainDeployment($deployment);

        $isDefaultNameservers = $dnsDeployment !== null && $dnsDeployment->dnsNameservers->isNotEmpty();
        $retrieveResult->setIsDefaultNameservers($isDefaultNameservers);

        return $retrieveResult;
    }

    /**
     * @throws GuzzleException
     */
    public function retrieveCustomerHandle(string $handle): RetrieveCustomerResponse
    {
        return $this->openproviderClient->getCustomerHandle($handle);
    }

    public function doesContactExist(string $handle): bool
    {
        try {
            $this->openproviderClient->getCustomerHandle($handle);

            return true;
        } catch (GuzzleException) {
            return false;
        }
    }

    /**
     * @param mixed[] $handleData
     */
    public function modifyHandle(string $domain, array $handleData): bool
    {
        assert(is_string($handleData['phone']));

        $address = $handleData['address'] ?? null;
        assert(is_array($address));
        assert(is_string($address['country']) || is_array($address['country']));
        assert(is_string($address['street']));
        assert(is_string($address['number']));
        assert(is_string($address['zipcode']));

        $phone = new PhoneDTO($handleData['phone']);
        $handleData['phone_country_code'] = $phone->getCountryCode();
        $handleData['phone_area_code'] = $phone->getAreaCode();
        $handleData['phone_subscriber_number'] = $phone->getNumber();
        $handleData['address'] = array_merge(
            $address,
            [
                'street_name' => $address['street'],
                'street_number' => $address['number'],
                'zip_code' => $address['zipcode'],
                'country_code' => $address['country'],
            ],
        );
        $handleData['locale'] = Locale::DUTCH->value;

        return $this->modify($domain, ['customer' => $handleData]);
    }

    /**
     * @param mixed[] $parameters
     *
     * @throws DomainModificationFailedException
     */
    public function modify(string $domain, array $parameters): bool
    {
        try {
            $this->openproviderClient->modifyDomain(
                ModifyParameters::create(
                    array_merge(
                        $parameters,
                        ['domain' => $domain],
                    ),
                ),
            );
        } catch (Throwable $exception) {
            Log::error(
                'Failed to modify a domain',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::OPENPROVIDER,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

            throw new DomainModificationFailedException('Domain modification failed', 1, $exception);
        }

        return true;
    }

    public function createContact(HandleParameters $params): string
    {
        return $this->openproviderClient->createHandle($params);
    }

    public function linkContactHandle(string $domain, HandleInterface $handles): void
    {
        $parameters = ModifyParameters::create([
            'domain' => $domain,
            'handles' => $handles,
        ]);

        $this->openproviderClient->modifyDomain($parameters);
    }

    public function destroyContact(string $externalId): DestroyContactResult
    {
        return $this->openproviderClient->destroyContact($externalId);
    }

    public function minimalTransfer(DomainDeployment $domainDeployment, ?Handles $handles): TransferResult
    {
        $domainSubscription = $domainDeployment->subscription;

        try {
            $periodInYears = $this->getPeriodInYears($domainSubscription->contract_period);

            $domain = $domainSubscription->domain;
            Assert::notNull($domain, 'Provided subscription has no domain');

            $parameters = TransferParameters::create(
                [
                    'domain' => $domain,
                    'period' => $periodInYears,
                    'nameServerGroup' => $this->configuration->getAsString(
                        'domainservice.nameservers.name-server-group',
                    ),
                    'customer' => $domainSubscription->customer->load('address')->toArray(),
                ],
            );

            return $this->openproviderClient->transferDomain($parameters);
        } catch (Exception $exception) {
            throw new RuntimeException('Domain transfer failed', 1, $exception);
        }
    }

    public function minimalRegister(DomainDeployment $domainDeployment, Handles $handles): RegistrationResult
    {
        $domainSubscription = $domainDeployment->subscription;
        $domain = $domainDeployment->subscription->domain;

        $periodInYears = $this->getPeriodInYears($domainSubscription->contract_period);

        Assert::notNull($domain, sprintf('Provided subscription [%s] has no domain', $domainSubscription->uuid));

        $primaryNs = $this->configuration->getAsString('domainservice.nameservers.primary-nameserver');
        $secondaryNs = $this->configuration->getAsString('domainservice.nameservers.secondary-nameserver');

        $parameters = RegistrationParameters::create(
            [
                'domain' => $domain,
                'handles' => $handles,
                'period' => $periodInYears,
                'nameServers' => [new Nameserver($primaryNs), new Nameserver($secondaryNs)],
            ],
        );

        return $this->openproviderClient->registerDomain($parameters);
    }

    /**
     * @throws DnsDeploymentNotFoundException
     */
    public function register(
        DomainDeployment $deployment,
        int $period,
        Customer $customer,
        Handles $handles,
        bool $isPrivateWhoisEnabled = false,
        bool $dnssecEnabled = false,
    ): RegistrationResult {
        $domain = $deployment->subscription->domain;
        Assert::notNull($domain, 'Provided subscription has no domain');

        $dnsDeployment = $this->dnsDeploymentRepository->getDnsDeploymentFromDomain($domain);

        if ($dnsDeployment === null) {
            throw new DnsDeploymentNotFoundException($domain);
        }

        try {
            // openprovider expects the period in years, not months
            $periodInYears = $this->getPeriodInYears($period);

            $parameters = RegistrationParameters::create(
                [
                    'domain' => $domain,
                    'handles' => $handles,
                    'period' => $periodInYears,
                    'nameServers' => $this->dnsDeploymentRepository->getNameserverHostnames($dnsDeployment),
                    'isPrivateWhoisEnabled' => $isPrivateWhoisEnabled,
                ],
            );

            if ($this->isDnssecSupported($domain)) {
                try {
                    $zone = $this->dnsService->getDnsZone($domain);
                    if ($zone->hasDnsSec()) {
                        $key = $this->dnsService->getDnsZoneKey($domain);

                        $parameters->setDnssecKeys([$key]);
                    }
                } catch (DnsZoneNotFoundException) {
                    // @ignoreException
                }
            }

            return $this->openproviderClient->registerDomain($parameters);
        } catch (Throwable $exception) {
            $this->nameserverAssigner->clear($dnsDeployment);

            throw new RuntimeException('Domain registration failed: ' . $exception->getMessage(), 1, $exception);
        }
    }

    /**
     * @throws Exception
     */
    public function isDnssecSupported(string $domain): bool
    {
        $extension = new Domain($domain)->getExtension();
        $extensionInfo = $this->openproviderClient->retrieveExtension($extension);

        return $extensionInfo->isDnssecAllowed();
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
        try {
            // openprovider expects the period in years, not months
            $periodInYears = $this->getPeriodInYears($period);

            $domain = $deployment->subscription->domain;
            Assert::notNull($domain, 'Provided subscription has no domain');

            $parameters = TransferParameters::create(
                [
                    'domain' => $domain,
                    'period' => $periodInYears,
                    'nameServers' => $this->nameServerRetriever->fetchNameServers($domain),
                    'nameServerGroup' => $this->configuration->getAsString(
                        'domainservice.nameservers.name-server-group',
                    ),
                    'customer' => $customer,
                    'transferSecret' => $transferSecret,
                    'isPrivateWhoisEnabled' => $isPrivateWhoisEnabled,
                ],
            );

            return $this->openproviderClient->transferDomain($parameters);
        } catch (Exception $exception) {
            throw new RuntimeException('Domain transfer failed', 1, $exception);
        }
    }

    /**
     * @param PowerDnsSecKey|null $key Optional key if external zone
     *
     * @throws Exception
     * @throws GuzzleException
     */
    public function enableDnssec(string $domain, ?PowerDnsSecKey $key = null): bool
    {
        if ($this->isDnssecSupported($domain)) {
            if ($key === null) {
                // Zone is not external
                $zone = $this->dnsService->getDnsZone($domain);

                if (! $zone->hasDnsSec()) {
                    $this->dnsService->enableDnssec($domain);
                }

                $key = $this->dnsService->getDnsZoneKey($domain);
            }

            return $this->modify($domain, ['dnssecKeys' => [$key]]);
        }

        return false;
    }

    /**
     * @param Nameserver[] $nameServers
     *
     * @throws Exception
     */
    public function updateNameServers(string $domain, array $nameServers): bool
    {
        $openProviderNameservers = array_map(fn (Nameserver $nameServer) => [
            'name' => $nameServer->hostname,
            'ip' => $nameServer->ipv4,
            'ip6' => $nameServer->ipv6,
        ], $nameServers);

        return $this->modify($domain, ['nameServers' => $openProviderNameservers]);
    }

    /**
     * @return mixed[]
     */
    public function retrieveDnssecKeys(string $domain): array
    {
        $domain = $this->openproviderClient->retrieveDomain($domain);

        $domainKeys = $domain->getDnssecKeys();
        $keys = [];

        if (is_array($domainKeys) && array_key_exists('array', $domainKeys)) {
            foreach ($domainKeys['array'] as $item) {
                $keys[] = $item;
            }
        }

        return $keys;
    }

    /**
     * @throws DomainModificationFailedException
     */
    public function disableDnssec(string $domain): bool
    {
        return $this->modify($domain, ['dnssecKeys' => []]);
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

    /**
     * @throws LogicException
     */
    public function retrieveRenewalDate(string $domain): CarbonImmutable
    {
        $dateString = $this->openproviderClient->retrieveDomain($domain)->getExpirationDateOpenprovider();
        if ($dateString === null) {
            throw new LogicException(sprintf(
                'Could not retrieve date from OpenProvider for domain %s',
                $domain,
            ));
        }

        return CarbonImmutable::instance(new DateTime($dateString));
    }

    /**
     * @throws NotImplementedException
     */
    public function suspend(string $domain): void
    {
        throw new NotImplementedException();
    }

    /**
     * @throws NotImplementedException
     */
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
        $retrieveResult = $this->openproviderClient->retrieveDomain($domain);

        $ownerHandle = $retrieveResult->getHandles()?->getOwnerHandle() ?? null;
        Assert::stringNotEmpty($ownerHandle, 'Registrant (owner handle) must not be null or empty');

        return new DomainDetailsDTO(
            domainName: $retrieveResult->getDomain()?->__toString() ?? $domain,
            registrant: $ownerHandle,
            status: [$this->mapDomainStatus($retrieveResult->getStatus())],
            autoRenew: $retrieveResult->getAutoRenew() ?? true,
            autoRenewPeriod: 0,
            ns: Arr::map($retrieveResult->getNameServers() ?? [], fn (array $nameserver) => $nameserver['name']),
            premium: false,
            childHosts: [],
            privacyProtect: $retrieveResult->getIsPrivateWhoisEnabled(),
            authcode: $retrieveResult->getAuthCode(),
            createdDate: $retrieveResult->getActiveDate() !== null
                ? new DateTime($retrieveResult->getActiveDate())
                : null,
            expiryDate: $retrieveResult->getExpirationDate() !== null
                ? new DateTime($retrieveResult->getExpirationDate())
                : null,
        );
    }

    public function getDomainKeyDataCollection(string $domain): ?KeyDataCollection
    {
        return null;
    }

    /**
     * @see https://docs.openprovider.com/doc/all#operation/CreateDomain
     */
    public function nameserversAreRequired(string $domain): bool
    {
        return false; // Not Implemented by OpenProvider
    }

    /**
     * @see https://docs.openprovider.com/doc/all#operation/CreateDomain
     */
    public function hasZoneCheck(string $domain): bool
    {
        return false; // Not Implemented by OpenProvider
    }

    public function creationRequiresPreValidation(string $domain): bool
    {
        return false; // Not Implemented by OpenProvider
    }

    public function getContactValidationCategoriesForDomain(string $domain): array
    {
        throw new NotImplementedException();
    }

    public function ensureContactValidatedForDomain(string $domain, string $handle): void
    {
        throw new NotImplementedException();
    }

    public function setClient(OpenproviderClient $openproviderClient): OpenproviderService
    {
        $this->openproviderClient = $openproviderClient;

        return $this;
    }

    private function mapDomainStatus(?string $openProviderStatus): string
    {
        return match ($openProviderStatus) {
            DomainStatus::ACTIVE->value => DomainStatusEnum::STATUS_OK,
            DomainStatus::DELETED->value => DomainStatusEnum::STATUS_REDEMPTION_PERIOD,
            DomainStatus::FAILED->value => DomainStatusEnum::STATUS_INACTIVE, // Inactive is probably the closest status
            DomainStatus::PENDING->value, DomainStatus::REQUESTED->value => DomainStatusEnum::STATUS_PENDING_VALIDATION, // Closest status probably...
            DomainStatus::RESTORE_REQUESTED->value => DomainStatusEnum::STATUS_PENDING_RESTORE,
            DomainStatus::SCHEDULED->value => DomainStatusEnum::STATUS_PENDING_TRANSFER,
            default => throw new UnexpectedValueException('Unknown status: ' . $openProviderStatus),
        };
    }

    private function getPeriodInYears(int $period): int|float
    {
        return $period / 12;
    }
}
