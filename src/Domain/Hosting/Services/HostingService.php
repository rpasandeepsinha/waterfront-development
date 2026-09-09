<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Services;

use ErrorException;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Bus\Dispatcher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use ReflectionException;
use UnexpectedValueException;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Hosting\DirectAdmin\DTO\DomainOccupation;
use Waterfront\Domain\Hosting\DirectAdmin\Exceptions\CoupleHostingException;
use Waterfront\Domain\Hosting\DTO\DnsRecord;
use Waterfront\Domain\Hosting\DTO\UserStatistics;
use Waterfront\Domain\Hosting\Exceptions\HostingException;
use Waterfront\Domain\Hosting\Exceptions\SsoResolveException;
use Waterfront\Domain\Hosting\Factories\HostingServiceFactory;
use Waterfront\Domain\Hosting\Interfaces\Hosting\HostingOfferingInterface;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Parameters as HostingParameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Domain\Hosting\Interfaces\Hosting\SiteConfigInterface;
use Waterfront\Domain\Hosting\Jobs\ReceiveWpInstallationIdJob;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Repositories\HostingDeploymentRepository;
use Waterfront\Domain\MailManagement\Services\MailManagementService;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Repositories\ProductSpecRepository;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Hosting\Exceptions\DriverNotDefinedException;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;
use Waterfront\Infra\PleskClient\Exceptions\PleskClientException;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Exceptions\NotImplementedException;
use Webmozart\Assert\Assert;

class HostingService
{
    public function __construct(
        private readonly HostingServiceFactory $hostingServiceFactory,
        private readonly LoggerInterface $logger,
        private readonly Dispatcher $busDispatcher,
        private readonly ProductSpecRepository $productSpecRepository,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly HostingDeploymentRepository $hostingDeploymentRepository,
        private readonly MailManagementService $mailManagementService,
        private readonly HostingDeploymentService $hostingDeploymentService,
    ) {
    }

    /**
     * @throws DirectAdminCommandException
     * @throws CoupleHostingException
     */
    public function decoupleHostingByDomain(DomainDeployment $domainDeployment): void
    {
        $this->hostingServiceFactory
            ->defaultDriver()->decoupleHostingByDomain($domainDeployment);
    }

    public function getCoupledHostingByDomain(DomainDeployment $domainDeployment): ?HostingDeployment
    {
        try {
            return $this->hostingServiceFactory->defaultDriver()->getCoupledHostingByDomain($domainDeployment);
        } catch (NotImplementedException) {
            return null;
        } catch (Exception $exception) {
            $this->logger->error(
                'Could not get coupled hosting by domain for domain {domain.name}',
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::DOMAIN_NAME => $domainDeployment->subscription->domain,
                ]
            );
            throw new HostingException(
                self::class . '::getCoupledHostingByDomain - exception code: ' . $exception->getCode()
                . ', message: ' . $exception->getMessage()
                . ', trace: ' . $exception->getTraceAsString(),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * @throws HostingException
     */
    public function getDomainOccupation(HostingDeployment $hostingDeployment): DomainOccupation
    {
        return $this->hostingServiceFactory->defaultDriver()->getDomainOccupation($hostingDeployment);
    }

    /**
     * @throws HostingException
     */
    public function coupleDomainToExistingHosting(
        DomainDeployment $domainDeployment,
        HostingDeployment $hostingDeployment
    ): bool {
        try {
            $providerSlug = $this->getProviderSlug($hostingDeployment->subscription);

            return $this->hostingServiceFactory
                ->driver(ProviderSlug::from($providerSlug ?? ''))
                ->coupleDomainToExistingHosting($domainDeployment, $hostingDeployment);
        } catch (Exception $exception) {
            $this->logger->error(
                'Could not couple domain to existing hosting {domain.name}',
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::DOMAIN_NAME => $domainDeployment->subscription->domain,
                ]
            );

            throw new HostingException(
                self::class . '::coupleDomainToExistingHosting - domain: ' . $domainDeployment->subscription->domain
                . ', exception code: ' . $exception->getCode()
                . ', message: ' . $exception->getMessage()
                . ', trace: ' . $exception->getTraceAsString(),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * Send the data to a hosting creation service and return the result.
     *
     * @throws DriverNotDefinedException
     */
    public function create(
        string $subscriptionUuid,
        string $contactPersonName,
        string $contactEmail,
        Product $product,
        Customer $customer,
        ?int $serverId,
        ?string $forwardingUrl = null,
        ?string $domain = null,
    ): void {
        $status = [];
        if ($serverId !== null && $serverId !== 0) {
            $server = Server::find($serverId);
        } else {
            $server = $this->hostingServiceFactory
                ->defaultDriver()
                ->findServer();
        }

        Assert::isInstanceOf($server, Server::class, message: 'There is a server required, before we can start with creation of hosting');

        $this->logger->info(
            'Create hosting',
            [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscriptionUuid,
                LoggingContextKeys::SERVER_ID => $serverId,
                LoggingContextKeys::META => [
                    'contact email' => $contactEmail,
                    'contact person' => $contactPersonName,
                    'email' => $contactEmail,
                ],
            ]
        );

        /** @var Subscription $subscription */
        $subscription = $this->subscriptionRepository->getByUuid($subscriptionUuid);

        try {
            $status = $this->hostingServiceFactory
                ->defaultDriver()
                ->create(
                    contactPersonName: $contactPersonName,
                    contactEmail: $contactEmail,
                    customerEmail: $customer->email,
                    customerUuid: $customer->uuid,
                    subscriptionUuid: $subscriptionUuid,
                    specs: $product->productSpecs->toArray(),
                    server: $server,
                    forwardingUrl: $forwardingUrl,
                    domain: $domain
                );

            if ($this->productSpecRepository->booleanSpecificationIsTrue($product, ProductSpecName::WAIT_FOR_WP_TOOLKIT)) {
                $status['result'] = TechnicalStatus::PENDING->value;
                $job = new ReceiveWpInstallationIdJob($subscription->uuid, $server);
                $this->busDispatcher->dispatch($job);
            }
        } catch (Exception $exception) {
            $this->logger->error(
                'Hosting create error for subscription {subscription.uuid} with domain {domain.name}',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::SERVER_ID => $server->id ?? null,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscriptionUuid,
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::META => [
                        'product_specs' => $product->productSpecs->toArray(),
                    ],
                ]
            );

            // @todo: find a generic system for the status of the different actions.
            $status['result'] = TechnicalStatus::ERROR->value;
            $status['domain'] = $domain;
        }

        $subscription->technical_status = $status['result'];
        $subscription->domain = $status['domain'];
        $subscription->save();
    }

    /**
     * @throws SsoResolveException
     */
    public function getSsoUrl(
        HostingDeployment $hostingDeployment,
        string $ipAddress,
        bool $redirectToMail = false
    ): string {
        /**
         * Upgrades/Downgrades are not working properly sometimes servers are not switched properly,
         * that's why need to check the 'old' property #WATER-5029.
         */
        $server = $this->hostingDeploymentRepository->getServer($hostingDeployment);
        $username = $this->hostingDeploymentService->getUsername($hostingDeployment);

        if ($redirectToMail) {
            $username = $this->hostingDeploymentService->getMailUsername($hostingDeployment);
        }

        if ($server === null) {
            throw new SsoResolveException(
                sprintf(
                    'Failed to generate SSO Url, No server attached to hosting deployment %s',
                    $hostingDeployment->uuid,
                )
            );
        }

        if ($username === null) {
            throw new SsoResolveException(
                sprintf(
                    'Failed to generate SSO Url, No username found for hosting deployment %s',
                    $hostingDeployment->uuid
                )
            );
        }

        $providerSlug = $this->getProviderSlug($hostingDeployment->subscription);
        Assert::notNull($providerSlug);
        $providerSlug = ProviderSlug::from($providerSlug);

        $ssoUrl = $this->hostingServiceFactory->driver($providerSlug)->getSsoUrl(
            $username,
            $server,
            $ipAddress,
            $redirectToMail
        );

        if ($ssoUrl === '') {
            throw new SsoResolveException(
                sprintf(
                    'Could not retrieve SSO url for hosting deployment %s using server %d from IP %s',
                    $hostingDeployment->uuid,
                    $server->id,
                    $ipAddress
                )
            );
        }

        return $ssoUrl;
    }

    /**
     * Retrieve the hosting package customer config.
     *
     * @return mixed[]
     */
    public function getCustomerConfig(HostingDeployment $hostingDeployment): array
    {
        $hostingDeployment->loadMissing(['subscription', 'provider']);
        assert($hostingDeployment->provider instanceof Provider);

        $parameters = new HostingParameters();
        $parameters->setUsername($this->hostingDeploymentService->getUsername($hostingDeployment) ?? '');

        $server = $this->hostingDeploymentRepository->getServer($hostingDeployment);

        $parameters->setServer($server);

        if (is_null($server)) {
            $this->logger->error(
                'Server not found for subscription {subscription.id} and domain {domain.name}',
                [
                    LoggingContextKeys::SUBSCRIPTION_ID => $hostingDeployment->subscription->id,
                    LoggingContextKeys::DOMAIN_NAME => $hostingDeployment->subscription->domain,
                ]
            );
            throw new ModelNotFoundException(
                sprintf(
                    'Server not found for subscription with id: %s and domain %s',
                    $hostingDeployment->subscription->id,
                    $hostingDeployment->subscription->domain
                )
            );
        }

        $parameters->setIpv4Address($server->getIpv4() ?? '');

        return $this->hostingServiceFactory->driver($hostingDeployment->provider->slug)->getCustomerConfig($parameters);
    }

    /**
     * Modify a hosting package customer. (enableDns/enableSsh/enableSsl).
     */
    public function modifyCustomer(Subscription $subscription, HostingParameters $parameters): bool
    {
        if (! $subscription->product->isHostingProduct() || $subscription->hostingDeployment === null) {
            throw new UnexpectedValueException(
                sprintf(
                    'Subscription %d is not a hosting subscription',
                    $subscription->id,
                )
            );
        }

        /** @var HostingDeployment $hostingDeployment */
        $hostingDeployment = $subscription->hostingDeployment;

        $server = $this->hostingDeploymentRepository->getServer($hostingDeployment);

        if (is_null($server)) {
            Log::error(
                sprintf(
                    'Server not found for subscription with id: %s and domain %s',
                    $subscription->id,
                    $subscription->domain ?? 'domain not set'
                )
            );
            throw new ModelNotFoundException(
                sprintf(
                    'Server not found for subscription with id: %s and domain %s',
                    $subscription->id,
                    $subscription->domain ?? 'domain not set'
                )
            );
        }

        $parameters->setServer($server);
        $parameters->setIpv4Address($server->getIpv4() ?? '');
        $parameters->setUsername($this->hostingDeploymentService->getUsername($hostingDeployment) ?? '');

        /** @var Provider $provider */
        $provider = $hostingDeployment->provider()->firstOrFail();
        return $this->hostingServiceFactory->driver($provider->slug)->modifyCustomer($parameters);
    }

    /**
     *
     * @throws DriverNotDefinedException
     *
     * @return array<mixed, mixed>
     */
    public function getPackagesOnServer(Server $server): array
    {
        return $this->hostingServiceFactory
            ->driver($this->hostingServiceFactory->getDriverFromServer($server))
            ->getPackagesOnServer($server);
    }

    /**
     *
     * @throws DriverNotDefinedException
     *
     * @return array<mixed, mixed>
     */
    public function getPackageOnServer(Server $server, string $packageName): array
    {
        return $this->hostingServiceFactory
            ->driver($this->hostingServiceFactory->getDriverFromServer($server))
            ->getPackageOnServer($server, $packageName);
    }

    /**
     * @throws DriverNotDefinedException
     * @throws ReflectionException
     * @throws HostingException
     * @throws GuzzleException
     */
    public function getPackageOnServerAsDto(Server $server, string $packageName): HostingOfferingInterface
    {
        return $this->hostingServiceFactory
            ->driver($this->hostingServiceFactory->getDriverFromServer($server))
            ->getPackageOnServerAsDto($server, $packageName);
    }

    /**
     * Get the URL to log into the server for SSO.
     */
    public function getServerSsoUrl(Server $server, ProviderSlug $driver): string|null
    {
        try {
            return $this->hostingServiceFactory->driver($driver)->getServerSsoUrl($server->id);
        } catch (Exception $exception) {
            Log::error(
                self::class . '::getServerSsoUrl - status code: ' . $exception->getCode()
                . ', message: ' . $exception->getMessage()
                . ', trace: ' . $exception->getTraceAsString()
            );

            return null;
        }
    }

    public function getUserStats(Subscription $subscription, ProviderSlug $driver): ?UserStatistics
    {
        $hostingDeployment = $subscription->hostingDeployment()->firstOrFail();

        $server = $this->hostingDeploymentRepository->getServer($hostingDeployment);

        if (is_null($server)) {
            Log::error(
                sprintf(
                    'Server not found for subscription with id: %s',
                    $subscription->id
                )
            );
            throw new ModelNotFoundException(
                sprintf(
                    'Server not found for subscription with id: %s',
                    $subscription->id,
                )
            );
        }

        $parameters = new HostingParameters();
        $parameters->setUsername($this->hostingDeploymentService->getUsername($hostingDeployment) ?? '');
        $parameters->setDomain($subscription->domain ?? '');
        $parameters->setIpv4Address($server->getIpv4() ?? '');
        $parameters->setServer($server);

        return $this->hostingServiceFactory->driver($driver)->getUserStats($parameters);
    }

    /**
     * @throws HostingException
     * @throws DriverNotDefinedException
     */
    public function getUserConfigAsDto(string $driver, string $identifier, Server $server): SiteConfigInterface
    {
        return $this->hostingServiceFactory
            ->driver(ProviderSlug::from($driver))
            ->getUserConfigAsDto($identifier, $server);
    }

    /**
     * @return array<mixed>
     */
    public function getUserConfig(string $driver, string $identifier, Server $server): array
    {
        return $this->hostingServiceFactory
            ->driver(ProviderSlug::from($driver))
            ->getUserConfig($identifier, $server);
    }

    /**
     * Please only use this function for migrations!!!
     *
     * @return array<mixed>
     */
    public function getUserConfigAsAdmin(string $driver, string $identifier, Server $server): array
    {
        return $this->hostingServiceFactory
            ->driver(ProviderSlug::from($driver))
            ->getUserConfigAsAdmin($identifier, $server);
    }

    /**
     * Terminate a hosting deployment.
     */
    public function terminate(HostingDeployment $deployment): void
    {
        Assert::notNull($deployment->subscription->domain, 'Provided subscription has no domain');
        Assert::notNull($deployment->provider);

        $success = $this->hostingServiceFactory
            ->driver($deployment->provider->slug)
            ->terminate(
                $deployment->subscription->domain,
                $deployment->subscription->uuid
            );

        if ($success) {
            $deployment->subscription->technical_status = Result::STATUS_DELETED;
            $deployment->subscription->save();
        }
    }

    /**
     *
     * @return array<string, string>
     */
    public function resetPassword(Subscription $subscription, ProviderSlug $driver): array
    {
        /** @var HostingDeployment $hostingDeployment */
        $hostingDeployment = $subscription->hostingDeployment()->firstOrFail();

        $server = $this->hostingDeploymentRepository->getServer($hostingDeployment);

        if (is_null($server)) {
            Log::error(
                sprintf(
                    'Server not found for subscription with id: %s',
                    $subscription->id
                )
            );
            throw new ModelNotFoundException(
                sprintf(
                    'Server not found for subscription with id: %s',
                    $subscription->id
                )
            );
        }

        $parameters = new HostingParameters();
        $parameters->setDomain($subscription->domain ?? '');
        $parameters->setUsername($this->hostingDeploymentService->getUsername($hostingDeployment) ?? '');
        $parameters->setPassword($this->resolvePassword());
        $parameters->setIpv4Address($server->getIpv4() ?? '');
        $parameters->setContactPersonName($subscription->customer->contact_name);
        $parameters->setEmailAddress($subscription->customer->email);

        return $this->hostingServiceFactory->driver($driver)->resetPassword($parameters, $subscription->customer->uuid);
    }

    public function getDefaultDomain(ProviderSlug $driver, string $username, Server $server): string|null
    {
        Log::info(
            self::class . '::getDefaultDomain - Get default domain',
            [
                LoggingContextKeys::META => [
                    'driver'   => $driver->value,
                    'username' => $username,
                    'server'   => $server->hostname,
                ],
            ]
        );

        return $this->hostingServiceFactory
            ->driver($driver)
            ->getDefaultDomain($username, $server);
    }

    /**
     * @throws DriverNotDefinedException
     * @throws ErrorException
     */
    public function isUsingHostingServerAsNameserver(
        string $driver,
        string|null $ipv4HostingServer,
        string|null $ipv6HostingServer,
        SiteConfigInterface $siteConfig
    ): bool {
        return $this->hostingServiceFactory
            ->driver(ProviderSlug::from($driver))
            ->isUsingHostingServerAsNameserver($ipv4HostingServer, $ipv6HostingServer, $siteConfig);
    }

    /**
     * @throws DirectAdminCommandException
     * @throws PleskClientException
     * @throws DriverNotDefinedException
     *
     * @return string[]
     */
    public function getCustomerDomains(string $driver, HostingDeployment $hostingDeployment): array
    {
        $domains = $this->hostingServiceFactory
            ->driver(ProviderSlug::from($driver))
            ->getCustomerDomainsForDkim($hostingDeployment);

        $this->logger->debug(
            'Retrieved domains for hosting deployment {provisioning.id}',
            [
                LoggingContextKeys::PROVISIONING_PROVIDER => ProviderSlug::tryFrom($driver)->value ?? $driver,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING,
                LoggingContextKeys::PROVISIONING_ID => $hostingDeployment->id,
                LoggingContextKeys::META => [
                    'domains' => $domains,
                ],
            ]
        );

        return $domains;
    }

    /**
     * @throws DirectAdminCommandException
     * @throws PleskClientException
     */
    public function isDkimEnabled(string $driver, HostingDeployment $hostingDeployment, string $domain): bool
    {
        return $this->hostingServiceFactory
            ->driver(ProviderSlug::from($driver))
            ->isDkimEnabled($hostingDeployment, $domain);
    }

    /**
     * @throws DirectAdminCommandException
     * @throws PleskClientException
     */
    public function setDkim(string $driver, HostingDeployment $hostingDeployment, string $domain, bool $enable): void
    {
        $this->logger->debug(
            sprintf('Setting DKIM for {domain.name} to %s', $enable ? 'enabled' : 'disabled'),
            [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProviderSlug::tryFrom($driver)->value ?? $driver,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING,
                LoggingContextKeys::PROVISIONING_ID => $hostingDeployment->id,
            ]
        );

        $this->hostingServiceFactory
            ->driver(ProviderSlug::from($driver))
            ->setDkim($hostingDeployment, $domain, $enable);
    }

    /**
     * @throws DirectAdminCommandException
     * @throws PleskClientException
     */
    public function getDkimRecord(string $driver, HostingDeployment $hostingDeployment, string $domain): ?DnsRecord
    {
        $dnsRecord = $this->hostingServiceFactory
            ->driver(ProviderSlug::from($driver))
            ->getDkimRecord($hostingDeployment, $domain);

        $this->logger->debug(
            'Retrieved DKIM DNS record for {domain.name}',
            [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProviderSlug::tryFrom($driver)->value ?? $driver,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING,
                LoggingContextKeys::PROVISIONING_ID => $hostingDeployment->id,
                LoggingContextKeys::META => $dnsRecord === null ? null : [
                    'dkim_dns_record' => [
                        'type' => $dnsRecord->type,
                        'host' => $dnsRecord->host,
                        'value' => $dnsRecord->value,
                    ],
                ],
            ]
        );

        return $dnsRecord;
    }

    public function getProviderSlug(Subscription $subscription): ?string
    {
        if ($subscription->product->productGroup->slug !== ProductGroupType::HOSTING) {
            return null;
        }

        if ($subscription->hostingDeployment !== null) {
            if ($subscription->product->isMailOnlyServer()) {
                return $this->mailManagementService->getMailHostingProviderSlug($subscription->hostingDeployment)->value;
            }

            return $subscription->hostingDeployment->provider?->slug->value;
        }

        $this->logger->notice(sprintf('Subscription with id: %d was a hosting deployment but was not coupled to a provider.', $subscription->id));

        return Provider::where('default', true)->where('type', ProviderType::HOSTING)->first()?->slug->value;
    }

    private function resolvePassword(): string
    {
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $pieces = [];
        $max = mb_strlen($chars, '8bit') - 1;
        for ($i = 0; $i < 32; ++$i) {
            $pieces[] = $chars[random_int(0, $max)];
        }

        return implode('', $pieces);
    }
}
