<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\Services;

use Carbon\CarbonImmutable;
use DateTime;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Cache\Repository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;
use LogicException;
use Pdp\Domain as PdpDomain;
use Psr\Log\LoggerInterface;
use RealtimeRegister\Domain\BillableCollection;
use RealtimeRegister\Domain\DomainContactCollection;
use RealtimeRegister\Domain\DomainRegistration;
use RealtimeRegister\Domain\DomainTransferStatus;
use RealtimeRegister\Domain\Enum\BillableActionEnum;
use RealtimeRegister\Domain\Enum\ProcessStatusEnum;
use RealtimeRegister\Domain\KeyData;
use RealtimeRegister\Domain\KeyDataCollection;
use RealtimeRegister\Domain\Process;
use RealtimeRegister\Domain\ProcessCollection;
use RealtimeRegister\Domain\TLDInfo;
use RealtimeRegister\Domain\Zone;
use RealtimeRegister\Exceptions\BadRequestException;
use RealtimeRegister\Exceptions\ForbiddenException;
use RealtimeRegister\Exceptions\NotFoundException;
use RealtimeRegister\Exceptions\RealtimeRegisterClientException;
use RealtimeRegister\Exceptions\UnexpectedValueException;
use RealtimeRegister\RealtimeRegister;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\DNS\Exceptions\DnsDeploymentNotFoundException;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\DNS\Exceptions\FailedToFetchNameserversException;
use Waterfront\Domain\DNS\Models\DnsDeployment;
use Waterfront\Domain\DNS\Models\DnsNameserver;
use Waterfront\Domain\DNS\Models\DnssecKey;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\DNS\Services\DnsNameserverAssigner;
use Waterfront\Domain\Domains\DTO\CheckResult;
use Waterfront\Domain\Domains\DTO\DestroyContactResult;
use Waterfront\Domain\Domains\DTO\Domain;
use Waterfront\Domain\Domains\DTO\DomainDetailsDTO;
use Waterfront\Domain\Domains\DTO\HandleParameters;
use Waterfront\Domain\Domains\DTO\Handles;
use Waterfront\Domain\Domains\DTO\RegistrationResult;
use Waterfront\Domain\Domains\DTO\RetrieveCustomerResponse;
use Waterfront\Domain\Domains\DTO\RetrieveResult;
use Waterfront\Domain\Domains\DTO\TransferResult;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Domains\Exceptions\ContactValidationRequiredException;
use Waterfront\Domain\Domains\Exceptions\DomainDoesNotExistException;
use Waterfront\Domain\Domains\Exceptions\DomainForbiddenException;
use Waterfront\Domain\Domains\Exceptions\DomainModificationFailedException;
use Waterfront\Domain\Domains\Exceptions\InvalidDnssecKeyException;
use Waterfront\Domain\Domains\Interfaces\DomainDriverInterface;
use Waterfront\Domain\Domains\Interfaces\HandleInterface;
use Waterfront\Domain\Domains\Interfaces\RevisionInterface;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Domains\Rules\DnssecKeyRule;
use Waterfront\Domain\Domains\Services\PremiumDomainService;
use Waterfront\Domain\Provision\DNS\Enums\NameserverType;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Infra\Common\DateTimeFormat;
use Waterfront\Infra\Common\PublicSuffixList;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\PowerDnsClient\Entities\PowerDnsSecKey;
use Waterfront\Infra\PowerDnsClient\Entities\PowerDnsSecKeySet;
use Waterfront\Infra\PowerDnsClient\Enums\PowerDnsZoneKind;
use Waterfront\Infra\RtrClient\Action\ParseRtrTransferStatusToWfStatusAction;
use Waterfront\Infra\RtrClient\Clients\RealtimeRegister as LocalRealtimeRegister;
use Waterfront\Infra\RtrClient\DTO\Revision;
use Waterfront\Infra\RtrClient\Exceptions\ContactDoesNotExistException;
use Waterfront\Infra\RtrClient\Exceptions\RtrApiException;
use Waterfront\Infra\RtrClient\Exceptions\WwwDomainNotAllowedException;
use Waterfront\Infra\RtrClient\Services\Enums\DomainStatus as RtrDomainStatus;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class RtrService implements DomainDriverInterface, RevisionInterface
{
    private const string TLD_INFO_CACHE_PREFIX = 'tld_info_';
    private const int SUPPORTED_DNSSEC_ALGORITHM = 13;
    private const int SUPPORTED_DNSSEC_PROTOCOL = 3;

    private string $billingHandle;

    public function __construct(
        private RealtimeRegister $realtimeRegister,
        private DnsService $dnsService,
        private readonly DnsNameserverAssigner $nameserverAssigner,
        private readonly RtrErrorParseService $errorParseService,
        private readonly PremiumDomainService $premiumDomainProducts,
        private readonly ConfigurationInterface $configuration,
        private readonly RtrResponseLogService $rtrResponseLogPersister,
        private readonly ParseRtrTransferStatusToWfStatusAction $parseRtrTransferStatusToWfStatusAction,
        private readonly PublicSuffixList $domainRules,
        private readonly LoggerInterface $logger,
        private readonly DnsDeploymentRepository $dnsDeploymentRepository,
        private readonly Repository $cache,
        private readonly RtrIdnLanguageCodeResolver $idnLanguageCodeResolver,
    ) {
        $this->billingHandle = $this->configuration->getAsString('realtimeregisterclient.handles.billing');
    }

    /**
     * @throws RtrApiException
     */
    public function retrieveAuthCode(string $domain): ?string
    {
        try {
            // Domains from certain TLD's always have an authcode filled in.
            $authCode = $this->realtimeRegister->domains->get($domain)->authcode;

            if ($authCode !== null) {
                return $authCode;
            }

            // Domains with certain TLD's don't have an authcode filled in by default. The API documentation states that
            // an empty string needs to be sent to generate an auth code.
            $this->realtimeRegister->domains->update($domain, authcode: '');

            return $this->realtimeRegister->domains->get($domain)->authcode;
        } catch (Exception $exception) {
            Log::error(
                sprintf(
                    'status code: %d, message: %s',
                    $exception->getCode(),
                    $exception->getMessage(),
                ),
            );
            throw new RtrApiException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    /**
     * @throws RtrApiException
     */
    public function retrieveRenewalDate(string $domain): CarbonImmutable
    {
        try {
            $expireDate = $this->realtimeRegister->domains->get($domain)->expiryDate;

            return CarbonImmutable::instance($expireDate);
        } catch (Exception $exception) {
            Log::error(
                sprintf(
                    'Status code: %d, message: %s',
                    $exception->getCode(),
                    $exception->getMessage(),
                ),
            );
            throw new RtrApiException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    /**
     * @throws RtrApiException
     *
     * @return Revision[]
     */
    public function revisions(string $domain): array
    {
        if (! $this->realtimeRegister instanceof LocalRealtimeRegister) {
            throw new RuntimeException('Expected a Local RealtimeRegister client with revision support');
        }

        try {
            return $this->realtimeRegister->revisions->listRevisions($domain);
        } catch (Exception $exception) {
            $this->logger->error(
                sprintf(
                    'Error fetching RTR revisions. Status code: %d, message: %s',
                    $exception->getCode(),
                    $exception->getMessage(),
                ),
                [
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::RTR,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                ],
            );
            throw new RtrApiException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    /**
     * Check the status of the given domain.
     *
     * @throws Exception
     */
    public function check(string $domain): CheckResult
    {
        if (str_starts_with($domain, 'www.')) {
            throw new WwwDomainNotAllowedException("Not allowed to order domain $domain starting with www.");
        }

        try {
            $response = $this->realtimeRegister->domains->check($domain);
            $result = $response->toArray();
            $reason = $result['reason'] ?? null;
            $availability = $result['available'] ? 'free' : 'active';
            $price = $result['price'] ?? null;
            $isPremium = $result['premium'] ?? null;

            return new CheckResult(
                domain: $domain,
                status: $availability,
                reason: $reason,
                isPremium: $isPremium,
                price: $price,
            );
        } catch (Throwable $exception) {
            Log::error(
                sprintf(
                    'status code: %d, message: %s',
                    $exception->getCode(),
                    $exception->getMessage(),
                ),
            );
            throw new RtrApiException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    public function nameserversAreRequired(string $domain): bool
    {
        $domainProspect = $this->domainRules->getRules()->resolve($domain)->suffix()->toString();

        $tldInfo = $this->getTldInfo($domainProspect);

        return $tldInfo->metadata->nameservers->required;
    }

    public function hasZoneCheck(string $domain): bool
    {
        $domainProspect = $this->domainRules->getRules()->resolve($domain)->suffix()->toString();

        $tldInfo = $this->getTldInfo($domainProspect);

        return $tldInfo->metadata->zoneCheck !== null;
    }

    public function creationRequiresPreValidation(string $domain): bool
    {
        $domainProspect = $this->domainRules->getRules()->resolve($domain)->suffix()->toString();

        $tldInfo = $this->getTldInfo($domainProspect);

        return $tldInfo->metadata->creationRequiresPreValidation;
    }

    public function getContactValidationCategoriesForDomain(string $domain): array
    {
        $domainProspect = $this->domainRules->getRules()->resolve($domain)->suffix()->toString();

        $domainTld = $this->getTldFromPossibleSld($domainProspect);

        $tldInfo = $this->getTldInfo($domainTld);

        if ($tldInfo->metadata->validationCategory === null) {
            return [];
        }

        return [$tldInfo->metadata->validationCategory];
    }

    /**
     * @param array<int, string> $categories
     *
     * @throws RtrApiException
     */
    public function validateContactHandle(string $handle, array $categories): void
    {
        try {
            $this->realtimeRegister->contacts->validate($this->billingHandle, $handle, $categories);
        } catch (Throwable $exception) {
            throw new RtrApiException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    public function ensureContactValidatedForDomain(string $domain, string $handle): void
    {
        $requiredCategories = $this->getContactValidationCategoriesForDomain($domain);

        if (count($requiredCategories) === 0) {
            return;
        }

        $contact = $this->realtimeRegister->contacts->get($this->billingHandle, $handle);

        $validatedCategories = [];
        if ($contact->validations !== null) {
            foreach ($contact->validations->entities as $validation) {
                $validatedCategories[] = $validation->category;
            }
        }

        $missingCategories = array_diff($requiredCategories, $validatedCategories);

        if (count($missingCategories) === 0) {
            return;
        }

        $this->logger->info(
            'Contact handle requires validation for domain {domain.name}',
            [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::RTR,
                LoggingContextKeys::META => [
                    'handle' => $handle,
                    'missing_categories' => array_values($missingCategories),
                ],
            ],
        );

        try {
            $this->validateContactHandle($handle, array_values($missingCategories));
        } catch (RtrApiException $exception) {
            $this->logger->warning(
                'Failed to trigger contact validation for domain {domain.name}',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::RTR,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );
        }

        throw new ContactValidationRequiredException(
            sprintf('Contact validation required before linking for [%s]', $domain),
        );
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     */
    public function isDnssecSupported(string $domain): bool
    {
        try {
            $zone = $this->dnsService->getDnsZone($domain);
        } catch (DnsZoneNotFoundException) {
            return false;
        }

        if ($zone->kind !== PowerDnsZoneKind::MASTER->value) {
            Log::warning(
                sprintf(
                    'DnsSec not supported for domain {%s} because zone is not set to master. Current zone: %s',
                    $domain,
                    $zone->kind,
                ),
            );

            return false;
        }

        $domainProspect = $this->domainRules->getRules()->resolve($domain)->suffix()->toString();

        $domainTld = $this->getTldFromPossibleSld($domainProspect);

        $tldInfo = $this->getTldInfo($domainTld);

        /**
         * Todo : This is a temporary fix see for a permanent solution.
         * https://yh-jira.atlassian.net/browse/WATER-6456.
         */
        if ($tldInfo->metadata->allowedDnssecAlgorithms !== null) {
            return in_array(self::SUPPORTED_DNSSEC_ALGORITHM, $tldInfo->metadata->allowedDnssecAlgorithms, true);
        }

        return false;
    }

    /**
     * Check if the zone is registered.
     *
     * @throws Exception
     */
    public function hasZone(string $domain): bool
    {
        try {
            $zone = $this->dnsService->getDnsZone($domain);
        } catch (Throwable) {
            return false;
        }

        return (bool) $zone;
    }

    /**
     * Check the nameservers of the given domain.
     *
     * @throws Exception
     */
    public function nameservers(DomainDeployment $deployment): RetrieveResult
    {
        $domain = $deployment->subscription->domain;
        Assert::notNull($domain, 'Provided domain deployment has no domain');

        $retrieveResult = $this->retrieveNameservers($domain);

        $dnsDeployment = $this->dnsDeploymentRepository->getDnsDeploymentFromDomainDeployment($deployment);
        Assert::notNull($dnsDeployment, sprintf(
            'No DNS deployment found for domain deployment with id [%d]',
            $deployment->id,
        ));

        $nameserverType = $dnsDeployment->nameserver_type;
        $retrieveResult->setIsDefaultNameservers($nameserverType === NameserverType::INTERNAL
        || $nameserverType === NameserverType::VANITY);

        return $retrieveResult;
    }

    /**
     * Creates a contact externally.
     *
     * @throws Exception
     */
    public function createContact(HandleParameters $params, ?string $handle = null): string
    {
        $handle ??= $this->generateCustomerHandle(
            $params->getCustomerNumber(),
            $params->getCompanyName() ?? $params->getFirstName() . $params->getLastName(),
        );

        $areaCode = $params->getPhoneAreaCode();
        $cutOff = '0';

        if (Str::startsWith($areaCode, '0')) {
            $areaCode = Str::substr($areaCode, Str::length($cutOff));
        }

        $phoneNumber =
            (str_starts_with('+', $params->getPhoneCountryCode()) ? '' : '+')
            . $params->getPhoneCountryCode()
            . '.'
            . $areaCode
            . $params->getPhoneSubscriberNumber();

        try {
            $this->realtimeRegister->contacts->create(
                $this->billingHandle,
                $handle,
                $params->getFirstName() . ' ' . $params->getLastName(),
                [$params->getAddressStreet() . ' ' . $params->getAddressNumber()],
                $params->getAddressZipcode(),
                $params->getAddressCity(),
                $params->getAddressCountry(),
                $params->getEmail(),
                $phoneNumber,
                null,
                $params->getCompanyName(),
            );

            return $handle;
        } catch (Exception $exception) {
            $customerId = $params->getCustomerNumber();
            Log::error("Failed creating contact remote for customer id: { $customerId } : {$exception->getMessage()} ");
            throw new RtrApiException(
                "Failed creating contact remote for customer id: { $customerId } : {$exception->getMessage()} ",
                $exception->getCode(),
                $exception,
            );
        }
    }

    /**
     * Retrieves customer handle.
     */
    public function retrieveCustomerHandle(string $handle): RetrieveCustomerResponse
    {
        $contact = $this->realtimeRegister->contacts->get(
            $this->billingHandle,
            $handle,
        );
        $contactName = explode(' ', $contact->name, 2);
        $streetName = '';
        $streetNumber = '';
        if (count($contact->addressLine) > 0) {
            $streetName = $contact->addressLine[0];
            $matches = [];
            $matchStatus = preg_match('/^(\d*\D+)\s+(\d.*)$/', $contact->addressLine[0], $matches);
            if ($matchStatus !== false && $matchStatus !== 0) {
                $streetName = $matches[1];
                $streetNumber = $matches[2];
            }
        }

        return new RetrieveCustomerResponse(
            null,
            null,
            0,
            $contact->handle,
            $contact->organization,
            $contact->country,
            $contactName[0] ?? $contact->name,
            $contactName[1] ?? '',
            'x',
            $contact->voice,
            $contact->email,
            $streetName,
            $streetNumber,
            $contact->postalCode,
            $contact->city,
            $contact->country,
        );
    }

    /**
     * Finds the registrant contact for the given domain.
     *
     * @throws DomainDoesNotExistException
     */
    public function retrieveCustomerHandleForDomain(string $domain): RetrieveCustomerResponse
    {
        return $this->retrieveCustomerHandle($this->fetchDomain($domain)->registrant);
    }

    /**
     * @throws DomainDoesNotExistException
     * @throws BadRequestException
     */
    public function fetchDomain(string $domain): DomainDetailsDTO
    {
        try {
            $retrieveResult = $this->realtimeRegister->domains->get($domain);
        } catch (NotFoundException $exception) {
            throw new DomainDoesNotExistException(
                sprintf('Domain "%s" not found with RTR.', $domain),
                Response::HTTP_NOT_FOUND,
                $exception,
            );
        }

        return new DomainDetailsDTO(
            domainName: $retrieveResult->domainName,
            registrant: $retrieveResult->registrant,
            status: $retrieveResult->status,
            autoRenew: $retrieveResult->autoRenew,
            autoRenewPeriod: $retrieveResult->autoRenewPeriod,
            ns: $retrieveResult->ns,
            premium: $retrieveResult->premium,
            childHosts: $retrieveResult->childHosts,
            registry: $retrieveResult->registry,
            customer: $retrieveResult->customer,
            privacyProtect: $retrieveResult->privacyProtect,
            authcode: $retrieveResult->authcode,
            languageCode: $retrieveResult->languageCode,
            createdDate: $retrieveResult->createdDate,
            updatedDate: $retrieveResult->updatedDate,
            expiryDate: $retrieveResult->expiryDate,
            zone: $retrieveResult->zone?->toArray(),
            contacts: $retrieveResult->contacts,
            keyData: $retrieveResult->keyData?->toArray(),
            dsData: $retrieveResult->dsData?->toArray(),
        );
    }

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
     *
     * @throws Exception
     */
    public function modifyHandle(string $domain, array $handleData): bool
    {
        $domainCustomer = $this->realtimeRegister->domains->get($domain)->customer;
        $handle = $handleData['handle'];
        $name = $handleData['name'] ?? null;

        try {
            $this->realtimeRegister->contacts->update(
                $domainCustomer,
                $handle,
                $name,
                $handleData['addressline'] ?? null,
                $handleData['postalCode'] ?? null,
                $handleData['city'] ?? null,
                $handleData['country'] ?? null,
                $handleData['email'] ?? null,
                $handleData['voice'] ?? null,
                $handleData['brand'] ?? null,
                $handleData['organization'] ?? null,
                $handleData['state'] ?? null,
                $handleData['fax'] ?? null,
            );
        } catch (Throwable $exception) {
            throw new RtrApiException('Failed updating customer handle', 0, $exception);
        }

        return true;
    }

    /**
     * Link handle(s) to domain.
     *
     * @throws Exception
     */
    public function linkContactHandle(string $domain, HandleInterface $handles): void
    {
        $this->modify($domain, [
            'registrant' => $handles->getOwnerHandle(),
            'contacts' => $this->contactList($handles->getOwnerHandle()),
        ]);
    }

    /**
     * @throws ContactDoesNotExistException
     * @throws JsonException
     */
    public function minimalTransfer(DomainDeployment $domainDeployment, Handles $handles): TransferResult
    {
        $domain = $domainDeployment->subscription->domain;

        Assert::notNull($domain, 'Provided subscription has no domain');

        $this->validateContactHandles($handles, $domain);

        $this->logger->info(
            'Minimal Transfer for domain {domain.name}',
            [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::SUBSCRIPTION_UUID => $domainDeployment->subscription_uuid,
                LoggingContextKeys::PROVISIONING_TYPE => 'domains',
            ],
        );

        try {
            $billables = $this->findBillableCollection(
                $domain,
                $domainDeployment,
                BillableActionEnum::ACTION_TRANSFER->value,
            );

            $domainTransferStatus = $this->realtimeRegister->domains->transfer(
                domainName: $domain,
                customer: $this->billingHandle,
                registrant: $handles->getOwnerHandle(),
                authcode: $domainDeployment->transfer_secret,
                autoRenew: true,
                contacts: $this->contactList($handles->getOwnerHandle()),
                billables: $billables,
            );

            $this->rtrResponseLogPersister->logApiResponse(json_encode(
                $domainTransferStatus->toArray(),
                JSON_THROW_ON_ERROR,
            ));
            Assert::isInstanceOf($domainTransferStatus, DomainTransferStatus::class, 'Expected a DomainTransferStatus');

            return $this->createSuccesTransferResult($domainTransferStatus);
        } catch (RealtimeRegisterClientException $exception) {
            $this->logger->error(
                'Failed to do minimal transfer for a domain [{domain.name}] with realtimeregister package error',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $domainDeployment->subscription_uuid,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );
            $this->rtrResponseLogPersister->logApiResponse(json_encode($exception->getMessage(), JSON_THROW_ON_ERROR));

            return $this->createFailedTransferResult($exception);
        } catch (Throwable $exception) { // @phpstan-ignore-line We allow the throwable to be ended here as we return the transfer result as failed.
            $this->logger->error(
                'Failed to do minimal transfer for a domain [{domain.name}] with unknown error',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $domainDeployment->subscription_uuid,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );
            $this->rtrResponseLogPersister->logApiResponse(json_encode($exception->getMessage(), JSON_THROW_ON_ERROR));

            return $this->createFailedTransferResult($exception);
        }
    }

    /**
     * @throws ContactDoesNotExistException
     * @throws JsonException
     */
    public function minimalRegister(
        DomainDeployment $domainDeployment,
        Handles $handles,
    ): RegistrationResult {
        $domain = $domainDeployment->subscription->domain;
        $period = $domainDeployment->subscription->contract_period;

        Assert::notNull($domain, sprintf(
            'Provided subscription [%s] has no domain',
            $domainDeployment->subscription_uuid,
        ));

        $this->validateContactHandles($handles, $domain);

        $contacts = $this->contactList($handles->getOwnerHandle());

        $this->logger->info(
            'Minimal Register Using handles',
            [
                LoggingContextKeys::META => [
                    'domain.handles' => $contacts->toArray(),
                ],
            ],
        );

        try {
            $billables = $this->findBillableCollection(
                $domain,
                $domainDeployment,
                BillableActionEnum::ACTION_CREATE->value,
            );
            $period = $this->getValidRegistrationPeriod($domain, $period);
            $languageCode = $this->resolveIdnLanguageCode($domain);

            $register = $this->realtimeRegister->domains->register(
                domainName: $domain,
                customer: $this->billingHandle,
                registrant: $handles->getOwnerHandle(),
                privacyProtect: $domainDeployment->private_whois_enabled,
                period: $period,
                languageCode: $languageCode,
                contacts: $contacts,
                billables: $billables,
            );

            $this->rtrResponseLogPersister->logApiResponse(json_encode($register->toArray(), JSON_THROW_ON_ERROR));
            Assert::isInstanceOf($register, DomainRegistration::class, 'Expected a DomainRegistration');

            return $this->createSuccesRegistrationResult($register, $domainDeployment);
        } catch (RealtimeRegisterClientException $exception) {
            $this->rtrResponseLogPersister->logApiResponse(json_encode($exception->getMessage(), JSON_THROW_ON_ERROR));

            return $this->createdFailedRegistrationResult($exception);
        } catch (Throwable $exception) { // @phpstan-ignore-line We allow the throwable to be ended here as we return the registration result as failed.
            $this->logger->error(
                'Failed to register the domain {domain.name} with unknown error',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $domainDeployment->subscription_uuid,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

            $this->rtrResponseLogPersister->logApiResponse(json_encode($exception->getMessage(), JSON_THROW_ON_ERROR));

            return $this->createdFailedRegistrationResult($exception);
        }
    }

    /**
     * Register the given domain.
     *
     * @throws ContactDoesNotExistException
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

        $this->validateContactHandles($handles, $domain);

        $contacts = $this->contactList($handles->getOwnerHandle());

        Log::info(
            self::class . '::registerDomain - Using handles',
            [
                LoggingContextKeys::META => [
                    'contacts' => $contacts->toArray(),
                ],
            ],
        );

        try {
            $dnssecKeyData = $dnssecEnabled ? $this->getDomainKeyDataCollection($domain) : null;
            $billables = $this->findBillableCollection($domain, $deployment, BillableActionEnum::ACTION_CREATE->value);
            $period = $this->getValidRegistrationPeriod($domain, $period);
            $languageCode = $this->resolveIdnLanguageCode($domain);

            $register = $this->realtimeRegister->domains->register(
                domainName: $domain,
                customer: $this->billingHandle,
                registrant: $handles->getOwnerHandle(),
                privacyProtect: $isPrivateWhoisEnabled,
                period: $period,
                languageCode: $languageCode,
                autoRenew: true,
                ns: $this->getNameserversHostnamesFromDnsDeployment($dnsDeployment),
                contacts: $contacts,
                keyData: $dnssecKeyData,
                billables: $billables,
            );

            $this->rtrResponseLogPersister->logApiResponse(json_encode($register->toArray(), JSON_THROW_ON_ERROR));
            Assert::isInstanceOf($register, DomainRegistration::class, 'Expected a DomainRegistration');

            return $this->createSuccesRegistrationResult($register, $deployment);
        } catch (RealtimeRegisterClientException $exception) {
            $this->rtrResponseLogPersister->logApiResponse(json_encode($exception->getMessage(), JSON_THROW_ON_ERROR));

            return $this->createdFailedRegistrationResult($exception);
        } catch (Throwable $exception) {
            Log::error(
                'Failed to register a domain with unknown error',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::RTR,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );
            $this->rtrResponseLogPersister->logApiResponse(json_encode($exception->getMessage(), JSON_THROW_ON_ERROR));
            $this->nameserverAssigner->clear($dnsDeployment);

            return $this->createdFailedRegistrationResult($exception);
        }
    }

    /**
     * Transfer the given domain.
     *
     * @throws ContactDoesNotExistException
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

        $this->validateContactHandles($handles, $domain);

        $dnsDeployment = $this->dnsDeploymentRepository->getDnsDeploymentFromDomain($domain);

        if ($dnsDeployment === null) {
            throw new DnsDeploymentNotFoundException($domain);
        }

        try {
            $dnssecKeyData = $dnssecEnabled ? $this->getDomainKeyDataCollection($domain) : null;
            $billables = $this->findBillableCollection(
                $domain,
                $deployment,
                BillableActionEnum::ACTION_TRANSFER->value,
            );
            $domainTransferStatus = $this->realtimeRegister->domains->transfer(
                $domain,
                $this->billingHandle,
                $handles->getOwnerHandle(), // our customer
                $isPrivateWhoisEnabled,
                null,
                $transferSecret,
                true,
                $this->getNameserversHostnamesFromDnsDeployment($dnsDeployment),
                null,
                null,
                null,
                $this->contactList($handles->getOwnerHandle()),
                $dnssecKeyData,
                $billables,
            );
            $this->rtrResponseLogPersister->logApiResponse(json_encode(
                $domainTransferStatus->toArray(),
                JSON_THROW_ON_ERROR,
            ));
            Assert::isInstanceOf($domainTransferStatus, DomainTransferStatus::class, 'Expected a DomainTransferStatus');

            return $this->createSuccesTransferResult($domainTransferStatus);
        } catch (RealtimeRegisterClientException $exception) {
            $this->rtrResponseLogPersister->logApiResponse(json_encode($exception->getMessage(), JSON_THROW_ON_ERROR));

            return $this->createFailedTransferResult($exception);
        } catch (Throwable $exception) {
            Log::error(
                'Failed to transfer a domain with unknown error',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::RTR,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

            return $this->createFailedTransferResult($exception);
        }
    }

    public function transferInfo(string $domain, ?string $processId = null): DomainTransferStatus
    {
        return $this->realtimeRegister->domains->transferInfo($domain, $processId);
    }

    /**
     * @param array{
     *     registrant?: string|null,
     *     isPrivateWhoisEnabled?: bool|null,
     *     period?: int|null,
     *     authCode?: string|null,
     *     languageCode?: string|null,
     *     autoRenew?: bool|null,
     *     ns?: array<string>|null,
     *     status?: array<string>|null,
     *     designatedAgent?: string|null,
     *     zone?: Zone|null,
     *     contacts?: DomainContactCollection|null,
     *     dnssecKeys?: KeyDataCollection|null,
     *     billables?: BillableCollection|null,
     * } $parameters
     *
     * @throws Exception
     */
    public function modify(string $domain, array $parameters): bool
    {
        $registrant = $parameters['registrant'] ?? null;

        try {
            $this->realtimeRegister->domains->update(
                $domain,
                $registrant,
                $parameters['isPrivateWhoisEnabled'] ?? null,
                $parameters['period'] ?? null,
                $parameters['authCode'] ?? null,
                $parameters['languageCode'] ?? null,
                $parameters['autoRenew'] ?? null,
                $parameters['ns'] ?? null,
                $parameters['status'] ?? null,
                $parameters['designatedAgent'] ?? null,
                $parameters['zone'] ?? null,
                $parameters['contacts'] ?? null,
                $parameters['dnssecKeys'] ?? null,
                $parameters['billables'] ?? null,
            );
        } catch (Throwable $exception) {
            Log::error(
                'Failed to modify a domain',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::RTR,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

            throw new DomainModificationFailedException('Domain modification failed', 1, $exception);
        }

        return true;
    }

    /**
     * @param Nameserver[] $nameServers
     *
     * @throws Exception
     */
    public function updateNameServers(string $domain, array $nameServers): bool
    {
        $hostnames = array_map(fn ($nameServer) => rtrim($nameServer->hostname, '.'), $nameServers);

        return $this->modify($domain, ['ns' => $hostnames]);
    }

    /**
     * Retrieve DNSSEC keys from domain registry.
     *
     * @return mixed[]
     */
    public function retrieveDnssecKeys(string $domain): array
    {
        $result = $this->realtimeRegister->domains->get($domain);

        $dnssecKeys = [];

        if (! is_null($result->keyData) && $result->keyData->count() > 0) {
            foreach ($result->keyData->entities as $key) {
                $dnssecKeys[] = [
                    'alg' => (string) $key->algorithm,
                    'flags' => (string) $key->flags,
                    'protocol' => (string) $key->protocol,
                    'pubKey' => $key->publicKey,
                ];
            }
        }

        return $dnssecKeys;
    }

    /**
     * Enable DNSSEC for a domain (and zone if it exists).
     *
     * @param PowerDnsSecKey|null $key Optional key if external nameservers
     *
     * @throws Exception
     * @throws GuzzleException
     * @throws DomainModificationFailedException
     */
    public function enableDnssec(string $domain, ?PowerDnsSecKey $key = null): bool
    {
        $path = $key instanceof PowerDnsSecKey ? 'manual' : 'pdns';

        $this->logger->info(
            'Starting dnssec preflight check',
            [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::META => ['path' => $path],
            ],
        );

        $this->preflightDnssecEnable($domain, $key);

        $this->logger->info(
            'Preflight check passed',
            [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::META => ['path' => $path],
            ],
        );

        if ($key instanceof PowerDnsSecKey) {
            $zoneKeys = [new DnssecKey($key)->toArray()];

            $this->logger->info(
                'Using manual DNSKEY',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::META => ['keys' => 1],
                ],
            );
        } else {
            $zone = $this->dnsService->getDnsZone($domain);

            if (! $zone->hasDnsSec()) {
                $this->logger->info(
                    'Enabling DNSSEC on PDNS zone',
                    [LoggingContextKeys::DOMAIN_NAME => $domain],
                );
                $this->dnsService->enableDnssec($domain);
            }

            $pdnsZoneKeys = $this->dnsService->getDnsZoneKeys($domain)->getKeys();

            $zoneKeys = [];
            foreach ($pdnsZoneKeys as $powerDnssecKey) {
                $zoneKeys[] = new DnssecKey($powerDnssecKey)->toArray();
            }

            $this->logger->info(
                'Collected PDNS zone keys',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::META => ['keys' => count($zoneKeys)],
                ],
            );
        }

        $keys = array_map(
            static fn (array $key): array => [
                'protocol' => $key['protocol'],
                'flags' => $key['flags'],
                'algorithm' => $key['alg'],
                'publicKey' => $key['pubKey'],
            ],
            $zoneKeys,
        );

        $this->logger->info(
            'Pushing DNSSEC keys to RTR',
            [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::META => [
                    'path' => $path,
                    'key_count' => count($keys),
                ],
            ],
        );

        return $this->modify($domain, ['dnssecKeys' => KeyDataCollection::fromArray($keys)]);
    }

    /**
     * Disable DNSSEC for a domain and zone if it exists.
     *
     * @throws Exception
     */
    public function disableDnssec(string $domain): bool
    {
        return $this->modify($domain, ['dnssecKeys' => KeyDataCollection::fromArray([])]);
    }

    /**
     * Enable private whois.
     *
     * @throws Exception
     */
    public function enablePrivateWhois(string $domain): bool
    {
        return $this->modify($domain, ['isPrivateWhoisEnabled' => true]);
    }

    /**
     * @throws Exception
     */
    public function disablePrivateWhois(string $domain): bool
    {
        return $this->modify($domain, ['isPrivateWhoisEnabled' => false]);
    }

    public function getClient(): RealtimeRegister
    {
        return $this->realtimeRegister;
    }

    public function setClient(RealtimeRegister $realtimeRegister): RtrService
    {
        $this->realtimeRegister = $realtimeRegister;

        return $this;
    }

    public function setHandle(?string $handle): RtrService
    {
        // Reset billing handle from BU to waterfront config if handle is null
        if ($handle === null) {
            $this->billingHandle = $this->configuration->getAsString('realtimeregisterclient.handles.billing');

            return $this;
        }

        $this->billingHandle = $handle;

        return $this;
    }

    /**
     * @throws LogicException
     */
    public function destroyContact(string $handleId): DestroyContactResult
    {
        try {
            $this->realtimeRegister->contacts->delete(
                $this->billingHandle,
                $handleId,
            );

            return new DestroyContactResult(true);
        } catch (Throwable $exception) {
            Log::error(
                'Failed to delete a contact',
                [
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::RTR,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::META => [
                        'handle_id' => $handleId,
                    ],
                ],
            );

            throw new LogicException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    public function getTldFromPossibleSld(string $prospect): string
    {
        $result = explode('.', $prospect);

        return end($result);
    }

    public function setDnsService(DnsService $dnsService): void
    {
        $this->dnsService = $dnsService;
    }

    /**
     * @throws DomainDoesNotExistException
     * @throws DomainForbiddenException
     * @throws DomainModificationFailedException
     * @throws Exception
     */
    public function suspend(string $domain): void
    {
        try {
            $currentDomainStatus = $this->realtimeRegister->domains->get($domain)->status;
        } catch (NotFoundException) {
            $this->logger->error(
                'Failed suspend domain {domain.name}, because it does not exist with RTR.',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::RTR,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                ],
            );

            throw new DomainDoesNotExistException(
                sprintf(
                    'Domain "%s" not found with RTR.',
                    $domain,
                ),
            );
        } catch (ForbiddenException $exception) {
            $this->logger->error(
                'Failed suspend domain {domain.name}, access forbidden by RTR.',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::RTR,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

            throw new DomainForbiddenException(
                sprintf(
                    'Access forbidden by RTR for domain "%s", the domain is likely linked to the wrong business unit.',
                    $domain,
                ),
                previous: $exception,
            );
        }

        if (in_array(RtrDomainStatus::CLIENT_HOLD->value, $currentDomainStatus, true)) {
            return;
        }

        if (in_array(RtrDomainStatus::PENDING_DELETE->value, $currentDomainStatus, true)) {
            $this->logger->info(
                'skipping suspending for {domain.name}, unable to update domain when pending_delete.',
                [LoggingContextKeys::DOMAIN_NAME => $domain],
            );

            return;
        }

        $success = $this->modify(
            $domain,
            [
                'status' => [
                    RtrDomainStatus::CLIENT_HOLD->value,
                    ...$currentDomainStatus,
                ],
            ],
        );

        if (! $success) {
            throw new DomainModificationFailedException(
                sprintf(
                    'Failed to update domain status to "%s" for domain "%s"',
                    RtrDomainStatus::CLIENT_HOLD->value,
                    $domain,
                ),
            );
        }
    }

    /**
     * @throws DomainDoesNotExistException
     * @throws DomainForbiddenException
     * @throws DomainModificationFailedException
     * @throws Exception
     */
    public function unsuspend(string $domain): void
    {
        try {
            $currentDomainStatus = $this->realtimeRegister->domains->get($domain)->status;
        } catch (NotFoundException) {
            $this->logger->error(
                'Failed unsuspend domain {domain.name}, because it does not exist with RTR.',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::RTR,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                ],
            );

            throw new DomainDoesNotExistException(
                sprintf(
                    'Domain "%s" not found with RTR.',
                    $domain,
                ),
            );
        } catch (ForbiddenException $exception) {
            $this->logger->error(
                'Failed unsuspend domain {domain.name}, access forbidden by RTR.',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::RTR,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

            throw new DomainForbiddenException(
                sprintf(
                    'Access forbidden by RTR for domain "%s", the domain is likely linked to the wrong business unit.',
                    $domain,
                ),
                previous: $exception,
            );
        }

        if (! in_array(RtrDomainStatus::CLIENT_HOLD->value, $currentDomainStatus, true)) {
            return;
        }

        foreach ($currentDomainStatus as $key => $status) {
            if ($status === RtrDomainStatus::CLIENT_HOLD->value) {
                unset($currentDomainStatus[$key]);
            }
        }

        $success = $this->modify($domain, ['status' => $currentDomainStatus]);

        if (! $success) {
            throw new DomainModificationFailedException(
                sprintf(
                    'Failed to update domain status to "%s" for domain "%s"',
                    RtrDomainStatus::OK->value,
                    $domain,
                ),
            );
        }
    }

    /**
     * @param string[] $statuses
     */
    public function getTechnicalStatusFromDomainStatusList(array $statuses): string
    {
        $formatStatus = [];
        foreach ($statuses as $status) {
            switch ($status) {
                /*
                 * The INACTIVE status is used when a domain is registered but not yet active.
                 * This only happens when the domain name is registered and no nameservers are
                 * set up at the registry. This is okay because the domain was registered.
                 */
                case RtrDomainStatus::INACTIVE->value:
                case RtrDomainStatus::OK->value:
                    $formatStatus[] = TechnicalStatus::OK->value;
                    break;
                case RtrDomainStatus::PENDING_DELETE->value:
                case RtrDomainStatus::PENDING_RENEW->value:
                case RtrDomainStatus::PENDING_UPDATE->value:
                case RtrDomainStatus::PENDING_RESTORE->value:
                case RtrDomainStatus::PENDING_TRANSFER->value:
                case RtrDomainStatus::PENDING_VALIDATION->value:
                case RtrDomainStatus::TRANSFER_PERIOD->value:
                case RtrDomainStatus::REDEMPTION_PERIOD->value:
                case RtrDomainStatus::CLIENT_HOLD->value:
                case RtrDomainStatus::SERVER_HOLD->value:
                    $formatStatus[] = TechnicalStatus::PENDING->value;
                    break;
                case RtrDomainStatus::CLIENT_DELETE_PROHIBITED->value:
                case RtrDomainStatus::CLIENT_UPDATE_PROHIBITED->value:
                case RtrDomainStatus::CLIENT_TRANSFER_PROHIBITED->value:
                case RtrDomainStatus::CLIENT_RENEW_PROHIBITED->value:
                case RtrDomainStatus::SERVER_DELETE_PROHIBITED->value:
                case RtrDomainStatus::SERVER_UPDATE_PROHIBITED->value:
                case RtrDomainStatus::SERVER_RENEW_PROHIBITED->value:
                case RtrDomainStatus::SERVER_TRANSFER_PROHIBITED->value:
                case RtrDomainStatus::PRIVACY_PROTECT_PROHIBITED->value:
                case RtrDomainStatus::IRTPC_TRANSFER_PROHIBITED->value:
                case RtrDomainStatus::EXPIRED->value:
                    $formatStatus[] = TechnicalStatus::FAILED->value;
                    break;
            }
        }

        if (in_array(TechnicalStatus::OK->value, $formatStatus, true)) {
            return TechnicalStatus::OK->value;
        }

        if (in_array(TechnicalStatus::PENDING->value, $formatStatus, true)) {
            return TechnicalStatus::PENDING->value;
        }

        return TechnicalStatus::FAILED->value;
    }

    /**
     * @param string[] $statuses
     */
    public function getPrimaryDomainStatusFromDomainStatusList(array $statuses): ?RtrDomainStatus
    {
        // The priority is intentional: PENDING_VALIDATION should win over OK.
        foreach ([
            RtrDomainStatus::PENDING_VALIDATION,
            RtrDomainStatus::OK,
            RtrDomainStatus::INACTIVE,
            RtrDomainStatus::PENDING_DELETE,
            RtrDomainStatus::PENDING_RENEW,
            RtrDomainStatus::PENDING_UPDATE,
            RtrDomainStatus::PENDING_RESTORE,
            RtrDomainStatus::PENDING_TRANSFER,
            RtrDomainStatus::TRANSFER_PERIOD,
            RtrDomainStatus::REDEMPTION_PERIOD,
            RtrDomainStatus::CLIENT_HOLD,
            RtrDomainStatus::SERVER_HOLD,
            RtrDomainStatus::CLIENT_DELETE_PROHIBITED,
            RtrDomainStatus::CLIENT_UPDATE_PROHIBITED,
            RtrDomainStatus::CLIENT_TRANSFER_PROHIBITED,
            RtrDomainStatus::CLIENT_RENEW_PROHIBITED,
            RtrDomainStatus::SERVER_DELETE_PROHIBITED,
            RtrDomainStatus::SERVER_UPDATE_PROHIBITED,
            RtrDomainStatus::SERVER_RENEW_PROHIBITED,
            RtrDomainStatus::SERVER_TRANSFER_PROHIBITED,
            RtrDomainStatus::PRIVACY_PROTECT_PROHIBITED,
            RtrDomainStatus::IRTPC_TRANSFER_PROHIBITED,
            RtrDomainStatus::EXPIRED,
        ] as $domainStatus) {
            if (in_array($domainStatus->value, $statuses, true)) {
                return $domainStatus;
            }
        }

        foreach ($statuses as $status) {
            $domainStatus = RtrDomainStatus::tryFrom($status);

            if ($domainStatus instanceof RtrDomainStatus) {
                return $domainStatus;
            }
        }

        return null;
    }

    public function restore(string $domain): void
    {
        try {
            /** @var DateTime $response */
            $response = $this->realtimeRegister->domains->restore($domain, 'Registrant Error');
            $this->rtrResponseLogPersister->logApiResponse(json_encode([
                'function' => 'restore',
                'domain' => $domain,
                'expiry_date' => $response->format(DateTimeFormat::DEFAULT),
            ], JSON_THROW_ON_ERROR));
        } catch (Throwable $exception) {
            $this->rtrResponseLogPersister->logApiResponse($exception->getMessage());
            throw new RealtimeRegisterClientException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    /**
     * @throws JsonException
     * @throws GuzzleException
     */
    public function getDomainKeyDataCollection(string $domain): ?KeyDataCollection
    {
        try {
            if (! $this->isDnssecSupported($domain)) {
                return null;
            }

            $dnsZoneKeys = $this->dnsService->getOrCreateDnsSecKeys($domain);
        } catch (DnsZoneNotFoundException $exception) {
            $this->logger->notice(
                'Skipping DNSSEC for domain {domain.name} because no DNS zone was found',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::RTR,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

            return null;
        } catch (ClientException $exception) {
            $this->logger->notice(
                'Error while getting DNS zone keys for domain [{domain.name}]',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::RTR,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

            return null;
        }

        return $this->powerDnssecKeySetToKeyDataCollection($dnsZoneKeys);
    }

    /**
     * @throws RtrApiException
     */
    public function listProcessesForDomain(string $domain): ProcessCollection
    {
        try {
            return $this->realtimeRegister->processes->list(
                parameters: [
                    'identifier' => $domain,
                    'type' => 'domain',
                ],
            );
        } catch (Exception $exception) {
            throw new RtrApiException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    /**
     * Check if contact already exist remote.
     */
    public function doesContactExist(string $handle): bool
    {
        try {
            $this->realtimeRegister->contacts->get(
                $this->billingHandle,
                $handle,
            );
        } catch (BadRequestException) {
            return false;
        }

        return true;
    }

    public function findOpenPrevalidationProcessForDomain(string $domain): ?Process
    {
        if (! $this->creationRequiresPreValidation($domain)) {
            return null;
        }

        return $this->findLatestOpenDomainCreationProcess($this->listProcessesForDomain($domain));
    }

    /**
     * @throws Exception
     */
    public function retrieveNameservers(string $domain): RetrieveResult
    {
        $remoteDomain = $this->fetchDomain($domain);
        $retrieveResult = $this->addContactsToRetrieveResult($remoteDomain);
        $retrieveResult->setDomain(new Domain($remoteDomain->domainName));
        $retrieveResult->setNameServers(
            array_map(
                fn (string $ns) => ['ip' => null, 'ip6' => null, 'name' => $ns],
                $remoteDomain->ns,
            ),
        );
        $retrieveResult->setIsPrivateWhoisEnabled(
            (bool) $remoteDomain->privacyProtect,
        );

        return $retrieveResult;
    }

    /**
     * Gets the available TLD creature durations for a tld from the TLD info.
     *
     * @return array<int>
     */
    private function getTldCreateDurations(string $domain): array
    {
        $domainProspect = $this->domainRules->getRules()->resolve($domain)->suffix()->toString();

        $domainTld = $this->getTldFromPossibleSld($domainProspect);

        $tldInfo = $this->getTldInfo($domainTld);

        return $tldInfo->metadata->createDomainPeriods;
    }

    private function preflightDnssecEnable(string $domain, ?PowerDnsSecKey $key = null): void
    {
        $this->fetchDomain($domain);
        $this->assertDnssecSupportedStrict($domain);
        $this->assertKeyIsValid($key);
        $this->assertExternalNameserversHaveKeyIfNeeded($domain, $key);
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     * @throws InvalidArgumentException
     */
    private function assertDnssecSupportedStrict(string $domain): void
    {
        if (! $this->isDnssecSupported($domain)) {
            throw new InvalidArgumentException(
                'DNSSEC not supported: zone must be MASTER and TLD must allow algorithm 13.',
            );
        }
    }

    private function assertKeyIsValid(?PowerDnsSecKey $key): void
    {
        if ($key === null) {
            return;
        }

        $dnskey = $key->toArray()['dnskey'] ?? '';
        $parts = preg_split('/\s+/', $dnskey, 4);
        if ($parts === false || count($parts) !== 4) {
            throw new InvalidDnssecKeyException(
                'Invalid DNSKEY format. Expected: "<flags> 3 <algorithm> <base64PublicKey>".',
            );
        }

        [$flags, $protocol, $alg, $pub] = $parts;

        try {
            DnssecKeyRule::assert([
                'flags' => $flags,
                'alg' => $alg,
                'pubKey' => $pub,
            ]);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidDnssecKeyException($exception->getMessage(), 0, $exception);
        }

        if ((int) $protocol !== 3) {
            throw new InvalidDnssecKeyException(sprintf('Protocol must be: %d', self::SUPPORTED_DNSSEC_PROTOCOL));
        }
    }

    private function assertExternalNameserversHaveKeyIfNeeded(string $domain, ?PowerDnsSecKey $key): void
    {
        if ($key instanceof PowerDnsSecKey) {
            return;
        }

        $nameservers = $this->retrieveNameservers($domain);
        $databaseInternalNameservers = DnsNameserver::all();
        $vanityTlds = new Collection($this->retrieveVanityTlds());

        $everyMatches = new Collection($nameservers->getNameServers())
            ->pluck('name')
            ->every(function (string $ns) use ($databaseInternalNameservers, $vanityTlds): bool {
                $isInternal = $databaseInternalNameservers->contains('nameserver', $ns);
                $isVanity = $vanityTlds->contains(fn (string $tld) => Str::endsWith($ns, $tld));

                return $isInternal || $isVanity;
            });

        if (! $everyMatches) {
            throw new InvalidArgumentException(
                'External nameservers detected: provide flags, alg (13) and base64 public key.',
            );
        }
    }

    private function getTldInfo(string $tld): TLDInfo
    {
        $key = sprintf('%s%s', self::TLD_INFO_CACHE_PREFIX, $tld);

        return $this->cache->remember(
            $key,
            CarbonImmutable::now()->addDay(),
            fn () => $this->realtimeRegister->tlds->info($tld),
        );
    }

    private function resolveIdnLanguageCode(string $domain): ?string
    {
        $resolvedDomain = $this->domainRules->getRules()->resolve(PdpDomain::fromIDNA2008($domain));
        $tldInfo = $this->getTldInfo($resolvedDomain->suffix()->toString());

        return $this->idnLanguageCodeResolver->resolve($resolvedDomain, $tldInfo);
    }

    /**
     * Generate a customer handle for realtime register client with the contact info.
     */
    private function generateCustomerHandle(int $customerNumber, string $name): string
    {
        $name = preg_replace('/[^a-zA-Z0-9\-_@\.]+/', '', $name);
        assert(is_string($name));
        $name = substr($name, 0, 15);

        $length = 38 - strlen($name) - strlen((string) $customerNumber);
        $randomString = Str::random($length);

        return "{$customerNumber}-{$name}-{$randomString}";
    }

    /**
     * Creating a contact list for registration.
     */
    private function contactList(string $handle): DomainContactCollection
    {
        return DomainContactCollection::fromArray([
            [
                'role' => 'ADMIN',
                'handle' => $handle,
            ],
            [
                'role' => 'BILLING',
                'handle' => $handle,
            ],
            [
                'role' => 'TECH',
                'handle' => $handle,
            ],
        ]);
    }

    private function addContactsToRetrieveResult(DomainDetailsDTO $remoteDomain): RetrieveResult
    {
        $matches = [];
        $retrieveResult = new RetrieveResult();

        if ($remoteDomain->contacts !== null) {
            foreach ($remoteDomain->contacts->entities as $contact) {
                match ($contact->role) {
                    'ADMIN' => $matches['ADMIN'] = $contact->handle,
                    'BILLING' => $matches['BILLING'] = $contact->handle,
                    'TECH' => $matches['TECH'] = $contact->handle,
                    default => throw new UnexpectedValueException(
                        sprintf(
                            'Found a unexpected role type while getting nameservers given role: %s',
                            $contact->role,
                        ),
                    ),
                };
            }

            $handles = new Handles(
                owner: $remoteDomain->registrant,
                admin: $matches['ADMIN'] ?? null,
                tech: $matches['TECH'] ?? null,
                billing: $matches['BILLING'] ?? null,
            );

            $retrieveResult->setHandles($handles);
        }

        return $retrieveResult;
    }

    private function findBillableCollection(
        string $domain,
        DomainDeployment $deployment,
        string $action,
    ): ?BillableCollection {
        $product = $deployment->subscription->product;
        if ($product->slug !== $this->premiumDomainProducts->getPremiumDomainProductSlug($domain)) {
            return null;
        }

        $billable = [];
        $billable['product'] = $this->premiumDomainProducts->getPremiumDomainRtrProduct($domain);
        $billable['action'] = $action;
        $billable['quantity'] = 1;

        return BillableCollection::fromArray([$billable]);
    }

    /**
     * @return KeyDataCollection<KeyData>
     */
    private function powerDnssecKeySetToKeyDataCollection(PowerDnsSecKeySet $powerDnsSecKeySet): KeyDataCollection
    {
        $keys = array_map(
            function (PowerDnsSecKey $powerDnssecKey): array {
                $dnssecKey = new DnssecKey($powerDnssecKey);

                return [
                    'protocol' => $dnssecKey->getProtocol(),
                    'flags' => $dnssecKey->getFlags(),
                    'algorithm' => $dnssecKey->getAlgorithm(),
                    'publicKey' => $dnssecKey->getPubKey(),
                ];
            },
            $powerDnsSecKeySet->getKeys(),
        );

        return KeyDataCollection::fromArray($keys);
    }

    /**
     * @throws FailedToFetchNameserversException
     *
     * @return string[]
     */
    private function getNameserversHostnamesFromDnsDeployment(DnsDeployment $dnsDeployment): array
    {
        $nameservers = $this->dnsDeploymentRepository->getNameservers($dnsDeployment);

        return array_map(fn (Nameserver $nameserver) => $nameserver->hostname, $nameservers);
    }

    /**
     * @return string[]
     */
    private function retrieveVanityTlds(): array
    {
        return [
            $this->configuration->getAsString('dns.gandi.vanity_nameservers.ns1'),
            $this->configuration->getAsString('dns.gandi.vanity_nameservers.ns2'),
            $this->configuration->getAsString('dns.gandi.vanity_nameservers.ns3'),
        ];
    }

    private function validateContactHandles(Handles $handles, ?string $domain): void
    {
        if (! $this->doesContactExist($handles->getOwnerHandle())) {
            throw new ContactDoesNotExistException(
                sprintf(
                    'Domain %s wants handle %s but does not exist externally',
                    $domain,
                    $handles->getOwnerHandle(),
                ),
            );
        }
    }

    private function getValidRegistrationPeriod(string $domain, int $period): int
    {
        //We try to avoid registering a domain where the TLD registration period is invalid.
        $tldCreateDurations = $this->getTldCreateDurations($domain);

        if (! in_array($period, $tldCreateDurations, true)) {
            // The period should be a divisible round number. For a period of 48 a 24 would work, but 36 wouldn't.
            $tldCreateDurations = array_filter($tldCreateDurations, fn ($value) => ($period % $value) === 0);

            // Use the longest of the remaining periods. If none remain, don't alter the request period.
            if (count($tldCreateDurations) !== 0) {
                $period = max($tldCreateDurations);
            }
        }

        return $period;
    }

    private function createSuccesTransferResult(DomainTransferStatus $domainTransferStatus): TransferResult
    {
        $status = $this->parseRtrTransferStatusToWfStatusAction->execute($domainTransferStatus->status);
        $transferResult = new TransferResult($status);
        $transferResult->setExpirationDate(CarbonImmutable::now()->format(DateTimeFormat::DEFAULT));

        $reason = json_encode($domainTransferStatus->toArray(), JSON_THROW_ON_ERROR);
        $transferResult->setReason($reason);

        return $transferResult;
    }

    private function createFailedTransferResult(Throwable $exception): TransferResult
    {
        $transferResult = new TransferResult(TechnicalStatus::FAILED->value);
        $transferResult->setExceptionMessage($exception->getMessage());
        $reason = $this->errorParseService->getTranslatedRtrError($exception->getMessage());

        return $transferResult->setReason($reason);
    }

    private function createSuccesRegistrationResult(
        DomainRegistration $register,
        DomainDeployment $domainDeployment,
    ): RegistrationResult {
        $this->persistRtrDomainStatusFromRegistration($domainDeployment, $register);

        $registrationResult = new RegistrationResult(
            $this->getLocalDomainStatusFromRegistration($register),
        );

        if ($register->expiryDate !== null) {
            $registrationResult->setExpirationDate(
                $register->expiryDate->format(DateTimeFormat::DEFAULT),
            );
        }

        $registrationResult->setActivationDate(CarbonImmutable::now()->format(DateTimeFormat::DEFAULT));
        $registrationResult->setReason(json_encode($register->toArray(), JSON_THROW_ON_ERROR));

        return $registrationResult;
    }

    private function persistRtrDomainStatusFromRegistration(
        DomainDeployment $domainDeployment,
        DomainRegistration $register,
    ): void {
        $rtrDomainStatus = $this->getRtrDomainStatusFromRegistration($domainDeployment, $register);

        if ($rtrDomainStatus === null) {
            return;
        }

        $domainDeployment->domain_status = $rtrDomainStatus;
        $domainDeployment->save();
    }

    private function getRtrDomainStatusFromRegistration(
        DomainDeployment $domainDeployment,
        DomainRegistration $register,
    ): ?RtrDomainStatus {
        if ($register->status !== null && $register->status !== []) {
            return $this->getPrimaryDomainStatusFromDomainStatusList($register->status);
        }

        if ($register->expiryDate !== null) {
            return RtrDomainStatus::OK;
        }

        $domain = $domainDeployment->subscription->domain;
        Assert::notNull($domain);

        if ($this->creationRequiresPreValidation($domain)) {
            return RtrDomainStatus::PENDING_VALIDATION;
        }

        return null;
    }

    private function getLocalDomainStatusFromRegistration(DomainRegistration $register): DomainStatus
    {
        if ($register->status !== null && $register->status !== []) {
            $rtrDomainStatus = $this->getPrimaryDomainStatusFromDomainStatusList($register->status);

            if (! $rtrDomainStatus instanceof RtrDomainStatus) {
                return DomainStatus::FAILED;
            }

            return match ($this->getTechnicalStatusFromDomainStatusList([$rtrDomainStatus->value])) {
                TechnicalStatus::OK->value => DomainStatus::ACTIVE,
                TechnicalStatus::PENDING->value => DomainStatus::PENDING,
                default => DomainStatus::FAILED,
            };
        }

        if ($register->expiryDate !== null) {
            return DomainStatus::ACTIVE;
        }

        return DomainStatus::PENDING;
    }

    private function findLatestOpenDomainCreationProcess(ProcessCollection $processes): ?Process
    {
        $openProcesses = [];

        foreach ($processes as $process) {
            if (! $process instanceof Process) {
                continue;
            }

            if ($this->isOpenDomainCreationProcess($process)) {
                $openProcesses[] = $process;
            }
        }

        if ($openProcesses === []) {
            return null;
        }

        usort(
            $openProcesses,
            static fn (Process $left, Process $right): int => $right->createdDate <=> $left->createdDate,
        );

        return $openProcesses[0];
    }

    private function isOpenDomainCreationProcess(Process $process): bool
    {
        if ($process->type !== 'domain') {
            return false;
        }

        if ($process->action !== 'create') {
            return false;
        }

        return in_array(
            $process->status,
            [
                ProcessStatusEnum::STATUS_NEW,
                ProcessStatusEnum::STATUS_VALIDATED,
                ProcessStatusEnum::STATUS_RUNNING,
                ProcessStatusEnum::STATUS_IN_DOUBT,
                ProcessStatusEnum::STATUS_SCHEDULED,
                ProcessStatusEnum::STATUS_SUSPENDED,
            ],
            true,
        );
    }

    private function createdFailedRegistrationResult(Throwable $exception): RegistrationResult
    {
        $registrationResult = new RegistrationResult(DomainStatus::FAILED);
        $registrationResult->setExceptionMessage($exception->getMessage());
        $reason = $this->errorParseService->getTranslatedRtrError($exception->getMessage());

        return $registrationResult->setReason($reason);
    }
}
