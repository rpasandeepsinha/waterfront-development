<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Pivot;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RealtimeRegister\Exceptions\RealtimeRegisterClientException;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\DNS\Actions\AssignNameserversToDomainAction;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\DNS\Exceptions\DnsDeploymentNotFoundException;
use Waterfront\Domain\DNS\Factories\NameserverAssignerFactory;
use Waterfront\Domain\DNS\Models\DnsDeployment;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\DNS\Repository\DnsProductSpecRepository;
use Waterfront\Domain\DNS\Services\DnsExternalNameserverAssigner;
use Waterfront\Domain\Domains\DTO\DomainDetailsDTO;
use Waterfront\Domain\Domains\DTO\HandleParameters;
use Waterfront\Domain\Domains\DTO\Handles;
use Waterfront\Domain\Domains\DTO\RegistrationResult;
use Waterfront\Domain\Domains\DTO\RetrieveCustomerResponse;
use Waterfront\Domain\Domains\DTO\RetrieveResult;
use Waterfront\Domain\Domains\DTO\TransferResult;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Domains\Exceptions\ContactValidationRequiredException;
use Waterfront\Domain\Domains\Exceptions\DisableAutorenewalFailedException;
use Waterfront\Domain\Domains\Exceptions\DomainContactException;
use Waterfront\Domain\Domains\Exceptions\DomainModificationFailedException;
use Waterfront\Domain\Domains\Exceptions\EnableAutorenewalFailedException;
use Waterfront\Domain\Domains\Exceptions\FetchDomainException;
use Waterfront\Domain\Domains\Exceptions\RestoreDomainException;
use Waterfront\Domain\Domains\Factories\DomainServiceFactory;
use Waterfront\Domain\Domains\Models\DomainContact;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Domains\Models\DomainProviderBusinessUnit;
use Waterfront\Domain\Domains\Repositories\DomainDeploymentRepository;
use Waterfront\Domain\Domains\Repositories\DomainProviderBusinessUnitRepository;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Provision\DNS\Enums\NameserverType;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\PowerDnsClient\Entities\PowerDnsSecKey;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Exceptions\NotImplementedException;
use Webmozart\Assert\Assert;

class DomainService
{
    public function __construct(
        private readonly NameserverAssignerFactory $nameserverAssignerFactory,
        private readonly AssignNameserversToDomainAction $assignNameserversToDomainAction,
        private readonly DomainServiceFactory $domainServiceFactory,
        private readonly LoggerInterface $logger,
        private readonly DnsDeploymentRepository $dnsDeploymentRepository,
        private readonly DnsProductSpecRepository $dnsProductSpecRepository,
        private readonly DnsService $dnsService,
        private readonly DomainDeploymentRepository $domainDeploymentRepository,
        private readonly DomainProviderBusinessUnitRepository $businessUnitRepository,
    ) {
    }

    public function retrieveAuthCode(ProviderSlug $driver, string $domain): ?string
    {
        return $this->domainServiceFactory->driver($driver, $this->getBusinessUnitByDomain($domain))->retrieveAuthCode($domain);
    }

    public function checkNameservers(DomainDeployment $deployment): RetrieveResult
    {
        Assert::notNull($deployment->subscription->domain);
        return $this->domainServiceFactory->driver($deployment->provider->slug, $this->getBusinessUnitByDomain($deployment->subscription->domain))->nameservers($deployment);
    }

    public function nameserversAreRequired(string $domain): bool
    {
        return $this->domainServiceFactory->defaultDriver()->nameserversAreRequired($domain);
    }

    public function registrationRequiresDnsBeforeSubmission(string $domain): bool
    {
        return $this->hasZoneCheck($domain) || $this->nameserversAreRequired($domain);
    }

    public function hasZoneCheck(string $domain): bool
    {
        return $this->domainServiceFactory->driver(ProviderSlug::REALTIME_REGISTER, $this->getBusinessUnitByDomain($domain))->hasZoneCheck($domain);
    }

    public function creationRequiresPreValidation(string $domain): bool
    {
        return $this->domainServiceFactory->driver(ProviderSlug::REALTIME_REGISTER, $this->getBusinessUnitByDomain($domain))->creationRequiresPreValidation($domain);
    }

    /**
     * @param array<mixed> $handleData
     */
    public function modifyHandle(string $domain, array $handleData, ProviderSlug $driver): bool
    {
        return $this->domainServiceFactory->driver($driver, $this->getBusinessUnitByDomain($domain))->modifyHandle($domain, $handleData);
    }

    public function destroyContact(Customer $customer, DomainContact $contact): bool
    {
        if ($contact->contactOwnerDomainSubscriptions->isNotEmpty()) {
            throw new DomainContactException(
                'Contact owner cannot be deleted because it still has domains attached.'
            );
        }

        $this->destroyContactRemote($customer, $contact);

        $contact->refresh();
        if ($contact->providers->isEmpty()) {
            return $contact->delete() ?? false;
        }

        return false;
    }

    /**
     * Try to destroy the contact at the given provider(s) and detach relationship if successful.
     */
    public function destroyContactRemote(Customer $customer, DomainContact $contact): void
    {
        foreach ($contact->providers as $provider) {
            try {
                /** @var string $externalId */
                $externalId = $provider->pivot->external_contact;
                $businessUnit = $this->getBusinessUnitById($provider->pivot->domain_business_unit_id);

                $result = $this->domainServiceFactory
                    ->driver($provider->slug, $businessUnit)
                    ->destroyContact($externalId);

                if ($result->isSuccessful()) {
                    // Only detach current provider relationship to the domain contact
                    $contact->providers()->detach($provider);
                }
            } catch (Exception $exception) {
                $this->logger->error(
                    'Failed deleting domain contact at provider: ' . $provider->slug->value . ',
                    Status code: ' . $exception->getCode() . ', message: ' . $exception->getMessage(),
                    [
                        LoggingContextKeys::CUSTOMER_ID => $customer->id,
                        LoggingContextKeys::EXCEPTION => $exception,
                        LoggingContextKeys::PROVISIONING_PROVIDER => $provider->slug->value,
                        LoggingContextKeys::META => ['external_id', $externalId],
                ]
                );
            }
        }
    }

    public function unlinkDomainSubscriptionsFromContactOwner(DomainContact $contact): void
    {
        $contact->contactOwnerDomainSubscriptions()->update(['contact_owner_id' => null]);
    }

    /**
     *
     * @return array<int<0, max>, array<string, string|false|null>>
     */
    public function getAvailableDomains(DomainContact $domainContact, bool $checkWhoisUpdateAllowed = false): array
    {
        if ($domainContact->has_anonymous_handle) {
            // Anonymous domain contacts can't be newly linked to any domains.
            // The button for linking in the frontend + policies should already be disabled, so this is an extra failsafe.
            return [];
        }

        $customer = $domainContact->customer;

        $subscriptions = Subscription::query()->whereProductGroupType(ProductGroupType::EXTENSION)
            ->whereNotIn('administrative_status', AdministrativeStatus::administrativelyEnded())
            ->where('customer_id', $customer->id)
            ->orderBy('domain')
            ->get();

        $domains = [];
        foreach ($subscriptions as $subscription) {
            /** @var DomainDeployment|null $domainDeployment */
            $domainDeployment = $subscription->domainDeployment;

            if (is_null($domainDeployment) || $domainDeployment->contact_owner_id === $domainContact->id) {
                continue;
            }

            if ($checkWhoisUpdateAllowed && $subscription->product->productSpecs->where('name', 'domain.allow_whois')->pluck('value')->first() === '0') {
                continue;
            }

            $domains[] = [
                'domain' => $subscription->domain,
                'linked' => false,
            ];
        }

        return $domains;
    }

    public function retrieveContactHandle(string $handle, ProviderSlug $driver, ?DomainProviderBusinessUnit $businessUnit = null): RetrieveCustomerResponse
    {
        return $this->domainServiceFactory->driver($driver, $businessUnit)->retrieveCustomerHandle($handle);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function minimalRegister(DomainDeployment $domainDeployment): RegistrationResult
    {
        if ($domainDeployment->contactOwner === null) {
            throw new InvalidArgumentException('Contact owner is required for minimal register');
        }

        $domainSubscription = $domainDeployment->subscription;
        Assert::notNull($domainSubscription->domain);

        $this->logger->info(
            'Starting minimal register for domain {domain.name}',
            [
                LoggingContextKeys::DOMAIN_NAME => $domainSubscription->domain,
                LoggingContextKeys::SUBSCRIPTION_UUID => $domainSubscription->uuid,
                LoggingContextKeys::SUBSCRIPTION_ID => $domainSubscription->id,
            ]
        );

        $handles = $this->findOrCreateHandles(
            customer: $domainSubscription->customer,
            deployment: $domainDeployment
        );

        return $this->domainServiceFactory
            ->driver($domainDeployment->provider->slug, $this->getBusinessUnitByDomain($domainSubscription->domain))
            ->minimalRegister($domainDeployment, $handles);
    }

    public function minimalTransfer(DomainDeployment $domainDeployment): TransferResult
    {
        if ($domainDeployment->contactOwner === null) {
            throw new InvalidArgumentException('Contact owner is required for minimal transfer');
        }

        $domainSubscription = $domainDeployment->subscription;
        Assert::notNull($domainSubscription->domain);

        $this->logger->info(
            'Starting minimal transfer for domain {domain.name}',
            [
                LoggingContextKeys::DOMAIN_NAME => $domainSubscription->domain,
                LoggingContextKeys::SUBSCRIPTION_UUID => $domainSubscription->uuid,
                LoggingContextKeys::SUBSCRIPTION_ID => $domainSubscription->id,
            ]
        );

        $handles = $this->findOrCreateHandles(
            customer: $domainSubscription->customer,
            deployment: $domainDeployment
        );

        return $this->domainServiceFactory
            ->driver($domainDeployment->provider->slug, $this->getBusinessUnitByDomain($domainSubscription->domain))
            ->minimalTransfer($domainDeployment, $handles);
    }

    public function register(
        DomainDeployment $domainDeployment,
        string $domain,
        int $period,
        Customer $customer,
        bool $isPrivateWhoisEnabled = false,
        bool $dnssecEnabled = false
    ): RegistrationResult {
        $this->logger->info(sprintf(
            'Starting register for domain %s',
            $domain
        ), [
            LoggingContextKeys::DOMAIN_NAME => $domain,
            LoggingContextKeys::CUSTOMER_ID => $customer->id,
            LoggingContextKeys::META => [
                'period' => $period,
                'private_whois' => $isPrivateWhoisEnabled ? 'enabled' : 'disabled',
                'dnssec' => $dnssecEnabled ? 'enabled' : 'disabled',
            ],
        ]);

        try {
            $handles = $this->findOrCreateHandles(
                customer: $customer,
                deployment: $domainDeployment
            );

            $this->logger->info(sprintf(
                'Registering domain %s, period %d, customer %d, handles: %s, private whois: %b',
                $domain,
                $period,
                $customer->id,
                json_encode($handles->toArray(), JSON_THROW_ON_ERROR),
                $isPrivateWhoisEnabled
            ), [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::CUSTOMER_ID => $customer->id,
                LoggingContextKeys::META => [
                    'period' => $period,
                    'private_whois' => $isPrivateWhoisEnabled ? 'enabled' : 'disabled',
                    'dnssec' => $dnssecEnabled ? 'enabled' : 'disabled',
                    'handles' => $handles->toArray(),
                ],
            ]);

            $domainRegResult = $this->domainServiceFactory
                ->driver($domainDeployment->provider->slug, $this->getBusinessUnitByDomain($domain))
                ->register(
                    $domainDeployment,
                    $period,
                    $customer,
                    $handles,
                    $isPrivateWhoisEnabled,
                    $dnssecEnabled
                );
        } catch (Exception $exception) {
            $this->logger->error(
                'status code: ' . $exception->getCode() . ', message: ' . $exception->getMessage(),
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::CUSTOMER_ID => $customer->id,
                    LoggingContextKeys::META => [
                        'period' => $period,
                        'private_whois' => $isPrivateWhoisEnabled ? 'enabled' : 'disabled',
                        'dnssec' => $dnssecEnabled ? 'enabled' : 'disabled',
                    ],
                ]
            );

            $domainRegResult = new RegistrationResult(DomainStatus::FAILED);
            $domainRegResult->setReason($exception->getMessage());
        }

        return $domainRegResult;
    }

    public function transfer(
        DomainDeployment $domainDeployment,
        string $domain,
        int $period,
        Customer $customer,
        bool $isPrivateWhoisEnabled = false,
        bool $dnssecEnabled = false,
        ?string $transferSecret = null
    ): TransferResult {
        $this->logger->info(sprintf(
            'Starting transfer for domain %s',
            $domain
        ), [
            LoggingContextKeys::DOMAIN_NAME => $domain,
            LoggingContextKeys::CUSTOMER_ID => $customer->id,
            LoggingContextKeys::META => [
                'period' => $period,
                'private_whois' => $isPrivateWhoisEnabled ? 'enabled' : 'disabled',
                'dnssec' => $dnssecEnabled ? 'enabled' : 'disabled',
                'transfer_secret' => $transferSecret === null ? 'not set' : 'set',
            ],
        ]);

        try {
            $handles = $this->findOrCreateHandles(
                customer: $customer,
                deployment: $domainDeployment
            );

            $this->logger->info(sprintf(
                'Transferring domain %s, period %d, customer %d, handles: %s, private whois: %b, dnssec enabled: %b',
                $domain,
                $period,
                $customer->id,
                json_encode($handles->toArray(), JSON_THROW_ON_ERROR),
                $isPrivateWhoisEnabled,
                $dnssecEnabled
            ), [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::CUSTOMER_ID => $customer->id,
                LoggingContextKeys::META => [
                    'period' => $period,
                    'private_whois' => $isPrivateWhoisEnabled ? 'enabled' : 'disabled',
                    'dnssec' => $dnssecEnabled ? 'enabled' : 'disabled',
                    'transfer_secret' => $transferSecret === null ? 'not set' : 'set',
                    'handles' => $handles->toArray(),
                ],
            ]);

            $domainTransferResult = $this->domainServiceFactory
                ->driver($domainDeployment->provider->slug, $this->getBusinessUnitByDomain($domain))
                ->transfer(
                    $domainDeployment,
                    $period,
                    $customer->load('address')->toArray(),
                    $handles,
                    $isPrivateWhoisEnabled,
                    $dnssecEnabled,
                    $transferSecret,
                );
        } catch (Exception $exception) {
            $this->logger->error(
                'status code: ' . $exception->getCode() . ', message: ' . $exception->getMessage(),
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::CUSTOMER_ID => $customer->id,
                    LoggingContextKeys::META => [
                        'period' => $period,
                        'private_whois' => $isPrivateWhoisEnabled ? 'enabled' : 'disabled',
                        'dnssec' => $dnssecEnabled ? 'enabled' : 'disabled',
                        'transfer_secret' => $transferSecret === null ? 'not set' : 'set',
                    ],
                ]
            );

            $domainTransferResult = new TransferResult(DomainStatus::FAILED->value);
            $domainTransferResult->setReason($exception->getMessage());
        }

        return $domainTransferResult;
    }

    /**
     * @param array<mixed> $parameters
     */
    public function modify(string $domain, array $parameters, ProviderSlug $driver): bool
    {
        try {
            $this->logger->info(sprintf(
                'Modifying domain %s, parameters: %s',
                $domain,
                json_encode($parameters, JSON_THROW_ON_ERROR)
            ), [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::PROVISIONING_PROVIDER => $driver,
                LoggingContextKeys::META => [
                    'parameters' => $parameters,
                ],
            ]);

            return $this->domainServiceFactory->driver($driver, $this->getBusinessUnitByDomain($domain))->modify($domain, $parameters);
        } catch (Exception $exception) {
            $this->logger->error(
                'status code: ' . $exception->getCode() . ', message: ' . $exception->getMessage(),
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::PROVISIONING_PROVIDER => $driver,
                    LoggingContextKeys::META => [
                        'parameters' => $parameters,
                    ],
                ]
            );

            return false;
        }
    }

    public function resetNameServersToInternal(DomainDeployment $deployment): bool
    {
        $domain = $deployment->subscription->domain;

        $this->logger->info(sprintf(
            'Resetting nameservers for domain %s',
            $domain,
        ), [
            LoggingContextKeys::DOMAIN_NAME => $domain,
        ]);

        Assert::notNull($domain, 'Provided subscription has no domain');

        $dnsDeployment = $this->dnsDeploymentRepository->getDnsDeploymentFromDomain($domain);

        if ($dnsDeployment === null) {
            throw new DnsDeploymentNotFoundException($domain);
        }

        $externalAssigner = $this->nameserverAssignerFactory->createAssigner(NameserverType::EXTERNAL);
        $externalAssigner->clear($dnsDeployment);

        $nameserverType = $this->getNameserverTypeForReset($dnsDeployment);
        $dnsDeployment->nameserver_type = $nameserverType;
        $dnsDeployment->save();

        if (! $this->dnsService->hasDnsZone($domain)) {
            $this->logger->debug('No internal zone yet for {domain.name}. Creating now', [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DNS,
                LoggingContextKeys::PROVISIONING_ID => $dnsDeployment->id,
            ]);

            $this->dnsService->createDnsZone(
                domain: $domain,
                nameservers: $this->dnsDeploymentRepository->getNameservers($dnsDeployment),
            );
        }

        $this->enableDnssec($domain, $deployment->provider->slug);

        $this->assignNameserversToDomainAction->assign($deployment);

        return true;
    }

    /**
     * @param Nameserver[] $nameServers
     *
     * @throws DnsDeploymentNotFoundException
     */
    public function setCustomNameservers(DomainDeployment $deployment, array $nameServers): bool
    {
        try {
            $domain = $deployment->subscription->domain;
            Assert::notNull($domain, 'Provided subscription has no domain');

            $dnsDeployment = $this->dnsDeploymentRepository->getDnsDeploymentFromDomain($domain);

            if ($dnsDeployment === null) {
                throw new DnsDeploymentNotFoundException($domain);
            }

            $assigner = $this->nameserverAssignerFactory->createAssigner($dnsDeployment->nameserver_type);
            $assigner->clear($dnsDeployment);

            /** @var DnsExternalNameserverAssigner $externalNameserverAssigner */
            $externalNameserverAssigner = $this->nameserverAssignerFactory->createAssigner(NameserverType::EXTERNAL);
            $externalNameserverAssigner->assign($dnsDeployment, $nameServers);

            $this->logger->info(sprintf(
                'Updating nameservers for domain %s to ns: %s',
                $domain,
                json_encode($nameServers, JSON_THROW_ON_ERROR)
            ), [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::PROVISIONING_PROVIDER => $deployment->provider->slug,
                LoggingContextKeys::META => [
                    'nameservers' => $nameServers,
                ],
            ]);

            return $this->domainServiceFactory->driver($deployment->provider->slug, $this->getBusinessUnitByDomain($domain))->updateNameServers($domain, $nameServers);
        } catch (DomainModificationFailedException $exception) {
            $this->logger->error(
                'status code: ' . $exception->getCode() . ', message: ' . $exception->getMessage(),
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::PROVISIONING_PROVIDER => $deployment->provider->slug,
                    LoggingContextKeys::META => [
                        'nameservers' => $nameServers,
                    ],
                ]
            );

            return false;
        }
    }

    /**
     * @throws DisableAutorenewalFailedException
     */
    public function disableAutoRenewal(DomainDeployment $domainDeployment): void
    {
        $domain = $domainDeployment->subscription->domain;
        Assert::notNull($domain, 'Provided subscription has no domain');

        $this->logger->info(sprintf(
            'Disabling autorenewal for domain %s',
            $domain,
        ), [
            LoggingContextKeys::DOMAIN_NAME => $domain,
        ]);

        if (! $this->modify($domain, ['autoRenew' => false], $domainDeployment->provider->slug)) {
            throw new DisableAutorenewalFailedException('The attempt to disable the autorenewal for ' . $domain .
                ' failed. Please refer to the logs for further information.');
        }

        $domainDeployment->subscription->technical_status = TechnicalStatus::CANCELED->value;
        $domainDeployment->subscription->save();
    }

    /**
     * @throws EnableAutorenewalFailedException
     */
    public function enableAutoRenewal(string $domain, ProviderSlug $providerSlug): void
    {
        $this->logger->info(sprintf(
            'Enabling autorenewal for domain %s',
            $domain,
        ), [
            LoggingContextKeys::DOMAIN_NAME => $domain,
            LoggingContextKeys::PROVISIONING_PROVIDER => $providerSlug,
        ]);

        if (! $this->modify($domain, ['autoRenew' => true], $providerSlug)) {
            throw new EnableAutorenewalFailedException('The attempt to enable the autorenewal for ' . $domain .
                ' failed. Please refer to the logs for further information.');
        }
    }

    public function isDnssecSupported(string $domain, ProviderSlug $driver): bool
    {
        return $this->domainServiceFactory->driver($driver, $this->getBusinessUnitByDomain($domain))->isDnssecSupported($domain);
    }

    /**
     * @return mixed[]
     */
    public function retrieveDnssecKeys(string $domain, ProviderSlug $driver): array
    {
        return $this->domainServiceFactory->driver($driver, $this->getBusinessUnitByDomain($domain))->retrieveDnssecKeys($domain);
    }

    /**
     * @param PowerDnsSecKey|null $key Optional key if external zone
     */
    public function enableDnssec(string $domain, ProviderSlug $driver, ?PowerDnsSecKey $key = null): bool
    {
        try {
            $this->logger->info(sprintf(
                'Enabling DNSSEC for domain %s, DNSSEC key: %s',
                $domain,
                $key !== null ? json_encode($key->toArray(), JSON_THROW_ON_ERROR) : null
            ), [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::PROVISIONING_PROVIDER => $driver,
                LoggingContextKeys::META => [
                    'dnssec_key' => $key?->toArray(),
                ],
            ]);

            return $this->domainServiceFactory->driver($driver, $this->getBusinessUnitByDomain($domain))->enableDnssec($domain, $key);
        } catch (Exception $exception) {
            $this->logger->error(
                'Status code: ' . $exception->getCode() . ', message: ' . $exception->getMessage(),
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::PROVISIONING_PROVIDER => $driver,
                    LoggingContextKeys::META => [
                        'dnssec_key' => $key?->toArray(),
                    ],
                ]
            );

            return false;
        }
    }

    public function disableDnssec(string $domain, ProviderSlug $driver): bool
    {
        try {
            $this->logger->info(sprintf(
                'Disabling DNSSEC for domain %s',
                $domain,
            ), [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::PROVISIONING_PROVIDER => $driver,
            ]);

            return $this->domainServiceFactory->driver($driver, $this->getBusinessUnitByDomain($domain))->disableDnssec($domain);
        } catch (Exception $exception) {
            $this->logger->error(
                'Status code: ' . $exception->getCode() . ', message: ' . $exception->getMessage(),
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::PROVISIONING_PROVIDER => $driver,
                ]
            );

            return false;
        }
    }

    /**
     * Determines whether you can enable DNSSEC.
     *
     * @throws Exception
     */
    public function manualDnssecAvailable(string $domain, DomainDeployment $domainDeployment): bool
    {
        $domainDriver = $this->domainServiceFactory->driver($domainDeployment->provider->slug, $this->getBusinessUnitByDomain($domain));

        if (! $domainDriver->isDnssecSupported($domain)) {
            return false;
        }

        return $domainDriver->nameservers($domainDeployment)->getIsDefaultNameservers() === true;
    }

    public function enablePrivateWhois(DomainDeployment $deployment): bool
    {
        $domain = $deployment->subscription->domain;
        assert($domain !== null);

        $this->logger->info(sprintf(
            'Enabling private WHOIS for domain %s',
            $domain,
        ), [
            LoggingContextKeys::DOMAIN_NAME => $domain,
            LoggingContextKeys::PROVISIONING_PROVIDER => $deployment->provider->slug,
            LoggingContextKeys::META => [
                'business_unit' => $deployment->businessUnit,
            ],
        ]);

        $enabled = $this->domainServiceFactory
            ->driver($deployment->provider->slug, $deployment->businessUnit)
            ->enablePrivateWhois($domain);

        $deployment->private_whois_enabled = $enabled;
        $deployment->save();

        return $enabled;
    }

    public function disablePrivateWhois(DomainDeployment $deployment): bool
    {
        $domain = $deployment->subscription->domain;
        assert($domain !== null);

        $this->logger->info(sprintf(
            'Disabling private WHOIS for domain %s',
            $domain,
        ), [
            LoggingContextKeys::DOMAIN_NAME => $domain,
            LoggingContextKeys::PROVISIONING_PROVIDER => $deployment->provider->slug,
            LoggingContextKeys::META => [
                'business_unit' => $deployment->businessUnit,
            ],
        ]);

        $disabled = $this->domainServiceFactory
            ->driver($deployment->provider->slug, $deployment->businessUnit)
            ->disablePrivateWhois($domain);

        $deployment->private_whois_enabled = $disabled;
        $deployment->save();

        return $disabled;
    }

    /**
     * @param array<int, array<string, string>> $domains
     *
     * @throws ContactValidationRequiredException
     * @throws DomainModificationFailedException
     * @throws NotImplementedException
     */
    public function linkContactHandle(array $domains, DomainContact $contact): bool
    {
        $resolvedDomains = [];

        foreach ($domains as $domain) {
            $domainName = $domain['domain'];
            $domainDeployment = DomainDeployment::whereHas(
                'subscription',
                fn (Builder $query) => $query
                    ->where('domain', $domainName)
            )->with('provider')->first();

            if (! $domainDeployment instanceof DomainDeployment) {
                $this->logger->warning(sprintf(
                    "No domain subscription for domain '%s' so can't link contact with id '%d'",
                    $domainName,
                    $contact->id
                ), [
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                    LoggingContextKeys::DOMAIN_NAME => $domainName,
                    LoggingContextKeys::META => ['domain_contact_id', $contact->id],
                ]);

                return false;
            }

            $businessUnit = $this->getBusinessUnitById($domainDeployment->domain_business_unit_id);
            $handle = $this->findOrCreateExternalHandle($contact, $domainDeployment->provider->slug, $businessUnit);

            $resolvedDomains[] = [
                'domain' => $domainName,
                'deployment' => $domainDeployment,
                'handle' => $handle,
                'businessUnit' => $businessUnit,
            ];
        }

        $domainsRequiringValidation = [];

        foreach ($resolvedDomains as $resolved) {
            try {
                $this->domainServiceFactory
                    ->driver($resolved['deployment']->provider->slug, $resolved['businessUnit'])
                    ->ensureContactValidatedForDomain($resolved['domain'], $resolved['handle']);
            } catch (ContactValidationRequiredException) {
                $domainsRequiringValidation[] = $resolved['domain'];
            } catch (NotImplementedException) {
                continue;
            }
        }

        if (count($domainsRequiringValidation) > 0) {
            throw new ContactValidationRequiredException(
                sprintf('Contact validation required before linking for [%s]', implode(',', $domainsRequiringValidation))
            );
        }

        foreach ($resolvedDomains as $resolved) {
            // TODO update per type
            // https://yh-jira.atlassian.net/browse/WATER-80

            $this->domainServiceFactory
                ->driver($resolved['deployment']->provider->slug, $resolved['businessUnit'])
                ->linkContactHandle($resolved['domain'], new Handles($resolved['handle']));

            $resolved['deployment']->contactOwner()->associate($contact);
            $resolved['deployment']->save();
        }

        return true;
    }

    /**
     * To unlink a handle from your domain contacts
     * we do the following:
     * 1. get the current handle
     * 2. dupe the current contacts without the old handle
     * 3. set new handles.
     *
     * @param array<int, string> $domains
     */
    public function unlinkContactHandle(array $domains, DomainContact $domainContact): void
    {
        foreach ($domains as $domain) {
            /** @var Subscription|null $subscription */
            $subscription = Subscription::query()->whereProductGroupType(ProductGroupType::EXTENSION)->where('domain', $domain)->first();

            if ($subscription === null) {
                $this->logger->error(sprintf(
                    'Failed to fetch subscription for domain: %s while unlinking',
                    $domain
                ), [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::META => ['domain_contact_id', $domainContact->id],
                ]);
                continue;
            }

            /** @var DomainDeployment|null $domainDeployment */
            $domainDeployment = $subscription->domainDeployment;

            if (is_null($domainDeployment)) {
                $this->logger->error(sprintf(
                    'Failed to fetch domain subscription for domain: %s with subscription: %s while unlinking',
                    $domain,
                    $subscription->uuid,
                ), [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::META => ['domain_contact_id', $domainContact->id],
                ]);
                continue;
            }

            $provider = $domainDeployment->provider;

            /** @var Provider|null $contactHandle */
            $contactHandle = $domainContact
                ->providers()
                ->where('provider_id', $provider->id)
                ->withPivot(['external_contact', 'domain_business_unit_id'])
                ->first();

            if (is_null($contactHandle)) {
                $this->logger->error(sprintf(
                    'Failed to fetch contact handle for domain: %s with subscription: %s while unlinking',
                    $domain,
                    $subscription->uuid,
                ), [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::META => ['domain_contact_id', $domainContact->id],
                ]);
                continue;
            }

            //1. Fetching current contact handle
            /** @var Pivot $pivot */
            $pivot = $contactHandle->getRelationValue('pivot');
            $handle = $pivot->external_contact;
            $businessUnit = $this->getBusinessUnitById($pivot->domain_business_unit_id);
            assert(is_string($handle));

            $currentContact = $this->domainServiceFactory->driver($provider->slug, $businessUnit)->retrieveCustomerHandle($handle);

            $params = HandleParameters::createFromRetrieveCustomerResponse($currentContact, $domainContact->customer);

            //2. Create new contact handle without old handle
            $newContact = $this->domainServiceFactory->driver($provider->slug, $businessUnit)->createContact($params);

            /** @var DomainContact $newDomainContact */
            $newDomainContact = DomainContact::create([
                'email'                   => $params->getEmail(),
                'first_name'              => $params->getFirstName(),
                'last_name'               => $params->getLastName(),
                'phone_country_code'      => $params->getPhoneCountryCode(),
                'phone_area_code'         => $params->getPhoneAreaCode(),
                'phone_subscriber_number' => $params->getPhoneSubscriberNumber(),
                'street_name'             => $params->getAddressStreet(),
                'street_number'           => $params->getAddressNumber(),
                'zip_code'                => $params->getAddressZipcode(),
                'city'                    => $params->getAddressCity(),
                'country_code'            => $params->getAddressCountry(),
                'organization'            => $params->getCompanyName(),
                'default_owner'           => false,
                'customer_id'             => $domainContact->customer->id,
            ]);

            $newDomainContact->providers()->attach($provider, ['external_contact' => $currentContact->getHandle()]);

            $handles = new Handles($newContact);

            //3. Set new handles.
            $modifySuccess = $this->modify($domain, $handles->toArray(), $provider->slug);

            if (! $modifySuccess) {
                $this->logger->error(sprintf(
                    'Failed to update new handles for domain : %s with Subscription uuid: %s.',
                    $domain,
                    $subscription->uuid
                ), [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::META => [
                        'domain_contact' => $domainContact->toArray(),
                    ],
                ]);
            }

            $domainDeployment->update(['contact_owner_id' => $newDomainContact->id]);
        }
    }

    public function restore(string $domain, ProviderSlug $type): void
    {
        try {
            $this->domainServiceFactory->driver($type, $this->getBusinessUnitByDomain($domain))->restore($domain);
        } catch (RealtimeRegisterClientException $exception) {
            throw new RestoreDomainException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    public function fetchDomain(string $domain, ProviderSlug $type): DomainDetailsDTO
    {
        try {
            return $this->domainServiceFactory->driver($type, $this->getBusinessUnitByDomain($domain))->fetchDomain($domain);
        } catch (Exception $exception) {
            throw new FetchDomainException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    /**
     * @param string[] $domains
     *
     * @return array<int<0, max>, array<string, bool|string>> $domains
     */
    public function addIsExternalInformation(array $domains): array
    {
        $externalDomains = [];
        foreach ($domains as $domain) {
            $subscription = Subscription::whereProductGroupType(ProductGroupType::EXTENSION)
                ->whereNotIn('administrative_status', AdministrativeStatus::administrativelyEnded())
                ->where('domain', $domain)
                ->first();

            if (! $subscription instanceof Subscription) {
                $externalDomains[] = ['domain' => $domain, 'is_external' => true];
                continue;
            }

            $domainDeployment = $subscription->domainDeployment;
            if (! $domainDeployment instanceof DomainDeployment) {
                $externalDomains[] = ['domain' => $domain, 'is_external' => true];
                continue;
            }

            $dnsDeployment = $this->dnsDeploymentRepository->getDnsDeploymentFromDomainDeployment($domainDeployment);
            if (! $dnsDeployment instanceof DnsDeployment) {
                $externalDomains[] = ['domain' => $domain, 'is_external' => true];
                continue;
            }

            $externalDomains[] = ['domain' => $domain, 'is_external' => $dnsDeployment->nameserver_type === NameserverType::EXTERNAL];
        }

        return $externalDomains;
    }

    private function getBusinessUnitByDomain(string $domain): ?DomainProviderBusinessUnit
    {
        $deployment = $this->domainDeploymentRepository->getDomainDeploymentByDomain($domain);
        return $deployment?->businessUnit;
    }

    private function findOrCreateExternalHandle(DomainContact $contact, ProviderSlug $driver, ?DomainProviderBusinessUnit $businessUnit = null): string
    {
        $query = $contact->providers()
            ->where('slug', $driver);

        if ($businessUnit === null) {
            $query->wherePivotNull('domain_business_unit_id');
        } else {
            $query->wherePivot('domain_business_unit_id', $businessUnit->id);
        }

        $external = $query->withPivot(['external_contact'])
            ->first();

        if ($external !== null) {
            $externalContact = $external->pivot->external_contact;
            assert(is_string($externalContact));

            $this->logger->debug(
                'Found external domain handle for domain contact',
                [
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::tryFrom($driver->value) ?? $driver->value,
                    LoggingContextKeys::META => [
                        'external_id' => $external->id,
                        'external_contact' => $externalContact,
                        'domain_contact_id' => $contact->id,
                        'business_unit' => $businessUnit?->slug,
                    ],
                ]
            );

            $remoteExists = $this->domainServiceFactory->driver($driver, $businessUnit)->doesContactExist($externalContact);

            if ($remoteExists) {
                return $externalContact;
            }

            $this->logger->warning(
                sprintf('DomainContact with handle [%s] exists in DB but not at remote. Removing external contact from provider.', $externalContact),
                [
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::tryFrom($driver->value) ?? $driver->value,
                    LoggingContextKeys::META => [
                        'external_id' => $external->id,
                        'external_contact' => $externalContact,
                        'domain_contact_id' => $contact->id,
                        'business_unit' => $businessUnit?->slug,
                    ],
                ]
            );

            $external->pivot->delete();
            $external->unsetRelation('pivot');
        }

        // Create new external handle
        $provider = Provider::where('slug', $driver)->firstOrFail();

        $params = HandleParameters::createFromCustomerArray($contact->domainContactArray());

        $handle = $this->domainServiceFactory->driver($driver, $businessUnit)->createContact($params);

        $contact->providers()->attach($provider, ['external_contact' => $handle]);

        $this->logger->debug(
            'Created new external domain handle for domain contact',
            [
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::tryFrom($driver->value) ?? $driver->value,
                LoggingContextKeys::META => [
                    'handle' => $handle,
                    'domain_contact_id' => $contact->id,
                    'business_unit' => $businessUnit?->slug,
                ],
            ]
        );

        return $handle;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function findOrCreateHandles(Customer $customer, DomainDeployment $deployment): Handles
    {
        if ($deployment->contactOwner === null) {
            throw new InvalidArgumentException('Contact owner is required for finding or creating handles');
        }

        $customerContact = $deployment->contactOwner;
        $driver = $deployment->provider->slug;
        $businessUnit = $this->getBusinessUnitById($deployment->domain_business_unit_id);

        $this->logger->debug(
            sprintf('Finding or creating domain handles for domain %s', $deployment->subscription->domain),
            [
                LoggingContextKeys::DOMAIN_NAME => $deployment->subscription->domain,
                LoggingContextKeys::CUSTOMER_ID => $customer->id,
                LoggingContextKeys::META => [
                    'deployment_id' => $deployment->id,
                    'domain_contact_id' =>  $deployment->contactOwner->id,
                    'business_unit' => $businessUnit?->slug,
                ],
            ]
        );

        $ownerHandle = $this->findOrCreateExternalHandle($customerContact, $driver, $businessUnit);

        return new Handles($ownerHandle);
    }

    private function getNameserverTypeForReset(DnsDeployment $dnsDeployment): NameserverType
    {
        $isPremium = $this->dnsProductSpecRepository->isPremiumDns($dnsDeployment->subscription->product);

        if ($isPremium) {
            return NameserverType::VANITY;
        }

        return  NameserverType::INTERNAL;
    }

    private function getBusinessUnitById(mixed $businessUnitId): ?DomainProviderBusinessUnit
    {
        if ($businessUnitId === null) {
            return null;
        }

        Assert::integerish($businessUnitId);
        return $this->businessUnitRepository->findById((int) $businessUnitId);
    }
}
