<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Plesk\Services;

use Carbon\CarbonImmutable;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Bus\Dispatcher as JobDispatcher;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;
use Throwable;
use UnexpectedValueException;
use Waterfront\Domain\DNS\Events\ReplaceParkingAndUpdateDns;
use Waterfront\Domain\DNS\Services\DnsZoneService;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Email\Dto\Recipient;
use Waterfront\Domain\Email\Jobs\AddDomainToSpamFilter;
use Waterfront\Domain\Email\Jobs\RemoveDomainFromSpamFilter;
use Waterfront\Domain\Hosting\Actions\Plesk\PleskGetSsoUrlAction;
use Waterfront\Domain\Hosting\Actions\Plesk\PleskSuspendHostingAction;
use Waterfront\Domain\Hosting\Actions\Plesk\PleskUnsuspendHostingAction;
use Waterfront\Domain\Hosting\DirectAdmin\DTO\DomainOccupation;
use Waterfront\Domain\Hosting\DTO\DnsRecord;
use Waterfront\Domain\Hosting\DTO\UserStatistics;
use Waterfront\Domain\Hosting\Exceptions\HostingException;
use Waterfront\Domain\Hosting\Interfaces\Drivers\HostingServiceInterface;
use Waterfront\Domain\Hosting\Interfaces\Hosting\CustomerInterface;
use Waterfront\Domain\Hosting\Interfaces\Hosting\HostingOfferingInterface;
use Waterfront\Domain\Hosting\Interfaces\Hosting\HostingPackageInterface;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\CreateCustomer\Result as CreateCustomerResult;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\DeleteCustomer\Parameters as CustomerDeleteParameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\DeleteCustomer\Result as CustomerDeleteResult;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\DeleteWebsite\Parameters as WebsiteDeleteParameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\EmailForwardingCreate\Parameters as EmailForwardingCreateParameters
;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\EmailGetAccountSettings\Parameters as EmailGetAccountSettingsParameters
;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\EmailSetCatchAll\Parameters as EmailSetCatchAllParameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Parameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Domain\Hosting\Interfaces\Hosting\SessionTokenInterface;
use Waterfront\Domain\Hosting\Interfaces\Hosting\SiteConfigInterface;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Plesk\DTO\SiteConfig;
use Waterfront\Domain\Hosting\Plesk\Exception\PleskServerException;
use Waterfront\Domain\Hosting\Plesk\Mailer\MailPleskDetails;
use Waterfront\Domain\Hosting\Plesk\Mailer\MailPleskEmailOnlyDetails;
use Waterfront\Domain\Hosting\Plesk\PleskPassword;
use Waterfront\Domain\Hosting\Repositories\HostingDeploymentRepository;
use Waterfront\Domain\Hosting\Repositories\ServerRepository;
use Waterfront\Domain\Mailer\IsMailable;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Repositories\ProductSpecRepository;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Ssl\Interfaces\InstallInterface;
use Waterfront\Domain\Ssl\Interfaces\Models\CertificateInstall\Parameters as CertificateInstallParameters;
use Waterfront\Domain\Ssl\Interfaces\SelectInterface;
use Waterfront\Domain\Subscriptions\Exceptions\SubscriptionNotFoundException;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\PleskClient\DTO\Domain;
use Waterfront\Infra\PleskClient\DTO\MailAccount;
use Waterfront\Infra\PleskClient\DTO\PleskHostingPackage;
use Waterfront\Infra\PleskClient\Exceptions\PleskClientException;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Exceptions\NotImplementedException;
use Waterfront\Support\Helpers\DnsHelper;
use Webmozart\Assert\Assert;

class PleskHostingService implements HostingServiceInterface
{
    private const string DOMAIN_TYPE_ALIAS = 'alias';

    private const string DOMAIN_TYPE_SUBDOMAIN = 'subdomain';

    public function __construct(
        private readonly HostingDeploymentRepository $deploymentRepository,
        private readonly ServerRepository $serverRepository,
        private readonly CustomerInterface $customerClient,
        private readonly HostingPackageInterface $hostingPackageClient,
        private readonly SessionTokenInterface $sessionTokenClient,
        private readonly SecretKeyService $secretKeyService,
        private readonly DnsZoneService $dnsZoneService,
        private readonly InstallInterface $sslInstallClient,
        private readonly SelectInterface $sslSelectClient,
        private readonly PleskUsernameBroker $pleskUsernameBroker,
        private readonly MailerInterface $mailer,
        private readonly JobDispatcher $jobDispatcher,
        private readonly EventDispatcher $eventDispatcher,
        private readonly PleskGetSsoUrlAction $pleskGetSsoUrlAction,
        private readonly PleskSuspendHostingAction $pleskSuspendHostingAction,
        private readonly PleskUnsuspendHostingAction $pleskUnsuspendHostingAction,
        private readonly ConfigurationInterface $configuration,
        private readonly LoggerInterface $logger,
        private readonly PleskPassword $pleskPassword,
        private readonly ProductSpecRepository $productSpecRepository,
        private readonly DnsHelper $dnsHelper,
        private readonly SubscriptionRepository $subscriptionRepository,
    ) {
    }

    public function serverIsValid(Server $server): bool
    {
        $this->hostingPackageClient->setServer($server);

        try {
            $result = $this->hostingPackageClient->getIpAddresses();
            if ($result->getStatus() !== Result::STATUS_OK) {
                throw new PleskServerException(sprintf('Server is not valid. [%s]', $result->getErrorMessage()));
            }

            return true;
        } catch (GuzzleException|PleskClientException|PleskServerException $exception) {
            $this->logger->notice(
                'Validation of hosting server failed with server: [{server.id}] - {server.name}',
                [
                    LoggingContextKeys::SERVER_ID => $server->id,
                    LoggingContextKeys::SERVER_HOSTNAME => $server->hostname,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

            return false;
        }
    }

    /**
     * Gets a suitable server and fetches the secret key if not already available.
     *
     *
     * @throws Exception
     */
    public function getServer(?HostingDeployment $hostingDeployment = null): Server
    {
        $server = $hostingDeployment->server ?? $this->findServer();

        if ($server->secret_key === null) {
            $server->secret_key = $this->secretKeyService->getPleskSecretKey($server);
            $server->save();
        }

        return $server;
    }

    public function findServer(): Server
    {
        return $this->serverRepository->findAvailableServer(
            serverType: ServerType::PLESK,
        );
    }

    /**
     * @throws Exception
     */
    public function createCustomer(Parameters $parameters): CreateCustomerResult
    {
        $createCustomerResult = $this->customerClient->createCustomer($parameters);

        if ($createCustomerResult->getStatus() === CreateCustomerResult::STATUS_ERROR) {
            throw new RuntimeException(
                $createCustomerResult->getErrorMessage() ?? '',
                $createCustomerResult->getErrorCode() ?? 0,
            );
        }

        return $createCustomerResult;
    }

    /**
     * @return array<mixed>
     */
    public function getCustomerConfig(Parameters $parameters): array
    {
        $server = $parameters->getServer();
        $username = $parameters->getUsername();

        if ($server === null) {
            throw new UnexpectedValueException('Hosting parameters did not contain any server');
        }

        /** @var array<mixed> $customerConfig */
        $customerConfig = Arr::get(
            $this->getUserConfig($username, $server),
            'response_body.customer.get',
            [],
        );

        return $customerConfig;
    }

    public function modifyCustomer(Parameters $parameters): bool
    {
        if ($parameters->getEnableDns() === Parameters::STATE_OFF) {
            $this->disableDnsZones($parameters);
        }

        return true;
    }

    /**
     * @throws RuntimeException
     */
    public function createPackage(Parameters $parameters): Result
    {
        $createPackageResult = $this->hostingPackageClient->createHosting($parameters);

        if ($createPackageResult->getStatus() === Result::STATUS_ERROR) {
            throw new RuntimeException(
                $createPackageResult->getErrorMessage() ?? '',
                $createPackageResult->getErrorCode() ?? 0,
            );
        }

        return $createPackageResult;
    }

    public function create(
        string $contactPersonName,
        string $contactEmail,
        string $customerEmail,
        UuidInterface $customerUuid,
        string $subscriptionUuid,
        array $specs,
        ?Server $server = null,
        ?string $forwardingUrl = null,
        ?string $domain = null,
    ): array {
        $return = [];
        $generatingFakeDomain = is_null($domain);
        $username = $this->generateUsername();

        if ($server instanceof Server === false) {
            $server = $this->getServer();
        }

        $hostingDeployment = $this->deploymentRepository->findByUuid($subscriptionUuid);
        $subscription = $this->subscriptionRepository->getByUuid($subscriptionUuid);

        if ($subscription === null) {
            throw new SubscriptionNotFoundException(sprintf('Subscription with uuid %s not found', $subscriptionUuid));
        }

        $currentServicePlan = $subscription->product->slug;

        if ($generatingFakeDomain) {
            $domain = $username . '.' . $server->hostname;
        }

        $this->customerClient->setServer($server);
        $this->hostingPackageClient->setServer($server);

        $this->logger->info(
            'Create new hosting',
            [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscriptionUuid,
                LoggingContextKeys::META => [
                    'contact_person' => $contactPersonName,
                    'contact_email' => $contactEmail,
                    'customer_email' => $customerEmail,
                ],
            ],
        );

        $hasWebsiteSpec = $this->productSpecRepository->findBySpecification(
            $subscription->product,
            ProductSpecName::HOSTING_HAS_WEBSITE->value,
        );
        $isMailOnlyHosting = $hasWebsiteSpec?->value === '0';

        $parameters = Parameters::create(
            [
                'contactPersonName' => $contactPersonName,
                'emailAddress' => $customerEmail,
                'domain' => $domain,
                'ipv4Address' => $server->ipv4,
                'ipv6Address' => $server->ipv6,
                'username' => $username,
                'password' => $this->generatePassword(),
                'specs' => $specs,
                'phpVersion' => $server->php_version,
                'forwardingUrl' => $forwardingUrl,
                'enableDns' => 'OFF',
                'enableSsh' => 'OFF',
                'enableSsl' => 'OFF',
                'notify' => 'yes',
                'package' => $currentServicePlan,
                'mailOnlyHosting' => $isMailOnlyHosting,
            ],
        );

        if (! $this->hostingPackageClient->servicePlanExists($parameters)) {
            throw PleskClientException::ServicePlanNotExistsException($currentServicePlan);
        }

        $createCustomerResult = $this->createCustomer($parameters);

        if ($createCustomerResult->getCustomerId() !== '') {
            $parameters->setCustomerId($createCustomerResult->getCustomerId());
        }

        if ($hostingDeployment === null) {
            $hostingDeployment = $this->deploymentRepository->create(
                [
                    'plesk_customer_username' => $parameters->getUsername(),
                    'plesk_customer_id' => $parameters->getCustomerId(),
                ],
                $subscriptionUuid,
                $server,
            );
        } else {
            $hostingDeployment->update([
                'plesk_customer_username' => $parameters->getUsername(),
                'plesk_customer_id' => $parameters->getCustomerId(),
                'server_id' => $server->id,
            ]);
        }

        $this->hostingPackageClient->setServer($server);
        $createPackageResult = $this->createPackage($parameters);

        $this->deploymentRepository->storeLastResult($subscriptionUuid, $createPackageResult->getResponseResult());

        $recipient = new Recipient($contactPersonName, $contactEmail, $customerUuid);
        $this->sendEmail(
            $hostingDeployment,
            [$recipient],
            $parameters->getUsername(),
            $parameters->getPassword(),
            $parameters->getDomain(),
            $parameters->getIpv4Address(),
        );

        if (! $generatingFakeDomain) {
            $domain = $parameters->getDomain();

            $this->setDnsForHosting($server, $domain);
            $this->jobDispatcher->dispatch(new AddDomainToSpamFilter($domain, null));
        }

        $return['result'] = $createPackageResult->getStatus();
        $return['domain'] = $domain;
        $return['username'] = $username;

        return $return;
    }

    /**
     * @throws PleskClientException
     * @throws SubscriptionNotFoundException
     */
    public function createForMailOnly(
        string $contactPersonName,
        string $contactEmail,
        string $customerEmail,
        UuidInterface $customerUuid,
        string $domain,
        string $subscriptionUuid,
    ): string {
        $server = $this->getServer();

        $deployment = $this->deploymentRepository->findByUuid($subscriptionUuid);
        if ($deployment === null) {
            throw new SubscriptionNotFoundException(sprintf('Subscription with uuid %s not found', $subscriptionUuid));
        }

        $currentServicePlan = $deployment->subscription->product->slug;
        $pleskMailOnlySlugs = Config::get('hostingservice.plesk.mail_only_slugs');
        assert(is_array($pleskMailOnlySlugs));
        $hasMailOnlySlug = in_array($currentServicePlan, $pleskMailOnlySlugs, true);

        if ($hasMailOnlySlug === false) {
            $currentServicePlan = $this->configuration->getAsString('hostingservice.plesk.mail_only_sitebuilder_slug');
        }

        $this->customerClient->setServer($server);
        $this->hostingPackageClient->setServer($server);

        $this->logger->info(
            'Create new mail only',
            [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscriptionUuid,
                LoggingContextKeys::META => [
                    'contact_person' => $contactPersonName,
                    'contact_email' => $contactEmail,
                    'customer_email' => $customerEmail,
                ],
            ],
        );

        $parameters = Parameters::create(
            [
                'contactPersonName' => $contactPersonName,
                'emailAddress' => $customerEmail,
                'domain' => $domain,
                'ipv4Address' => $server->ipv4,
                'ipv6Address' => $server->ipv6,
                'username' => $this->generateUsername(),
                'password' => $this->generatePassword(),
                'specs' => [],
                'phpVersion' => $server->php_version,
                'forwardingUrl' => null,
                'enableDns' => 'OFF',
                'enableSsh' => 'OFF',
                'enableSsl' => 'OFF',
                'notify' => 'yes',
                'package' => $currentServicePlan,
                'mailOnlyHosting' => true,
            ],
        );

        if (! $this->hostingPackageClient->servicePlanExists($parameters)) {
            throw PleskClientException::ServicePlanNotExistsException($currentServicePlan);
        }

        $createCustomerResult = $this->createCustomer($parameters);

        if ($createCustomerResult->getCustomerId() !== '') {
            $parameters->setCustomerId($createCustomerResult->getCustomerId());
        }

        $deployment->update([
            'plesk_customer_username' => $parameters->getUsername(),
            'plesk_customer_id' => $parameters->getCustomerId(),
        ]);

        $createPackageResult = $this->createPackage($parameters);

        if ($hasMailOnlySlug) {
            $this->setDnsForHosting($server, $domain);

            $payload = array_merge(
                $parameters->toArray(),
                [
                    'username' => $parameters->getUsername(),
                    'password' => $parameters->getPassword(),
                    'domain' => $parameters->getDomain(),
                    'ipv4_address' => $parameters->getIpv4Address(),
                ],
            );

            $recipient = new Recipient($contactPersonName, $contactEmail, $customerUuid);
            $this->sendEmail(
                $deployment,
                [$recipient],
                $parameters->getUsername(),
                $parameters->getPassword(),
                $parameters->getDomain(),
                $parameters->getIpv4Address(),
            );
        }

        $this->deploymentRepository->storeLastResult($subscriptionUuid, $createPackageResult->getResponseResult());

        return $createPackageResult->getStatus() ?? Result::STATUS_ERROR;
    }

    public function createEmailForward(
        Server $server,
        string $domain,
        string $sourceEmailAddressUsername,
        string $destinationEmailAddresses,
    ): string {
        $parameters = EmailForwardingCreateParameters::create(
            [
                'domain' => $domain,
                'sourceEmailAddressUsername' => $sourceEmailAddressUsername,
                'destinationEmailAddresses' => [$destinationEmailAddresses],
            ],
        );

        $this->hostingPackageClient->setServer($server);
        $result = $this->hostingPackageClient->createEmailForward($parameters);

        return $result->getStatus() ?? Result::STATUS_ERROR;
    }

    /**
     * Get the URL to log into the hosting provider for SSO.
     */
    public function getServerSsoUrl(int $serverId): string
    {
        $server = Server::findOrFail($serverId);
        $this->sessionTokenClient->setServer($server);

        return $this->sessionTokenClient->getServerSsoUrl();
    }

    /**
     * @return MailAccount[]
     */
    public function getMailAccounts(Server $server, string $domain): array
    {
        $this->hostingPackageClient->setServer($server);
        $siteId = $this->hostingPackageClient->getSiteIdByDomain($domain);

        return $this->hostingPackageClient
            ->getExistingEmailAccounts(EmailGetAccountSettingsParameters::create(['siteId' => $siteId]))
            ->getEmailAccounts();
    }

    public function getUserStats(Parameters $parameters): UserStatistics
    {
        $server = $parameters->getServer();
        $username = $parameters->getUsername();

        if (! $server instanceof Server) {
            throw new UnexpectedValueException('Server parameter must be an instance of Server');
        }

        $result = $this->getUserConfig($username, $server);

        /** @var array<string, string> $stats */
        $stats = Arr::get($result, 'response_body.customer.get.result.data.stat', []);
        $mailAccounts = $this->getMailAccounts($server, $parameters->getDomain());
        $mailDiskSpace = array_reduce(
            $mailAccounts,
            fn (int $usage, MailAccount $account): int => $usage + $account->mailboxUsage,
            0,
        );

        return new UserStatistics(
            activeDomains: (int) $stats['active_domains'],
            subdomains: (int) $stats['subdomains'],
            diskSpaceInMb: (int) ((round((int) $stats['disk_space']) / 1024) / 1024),
            mailDiskSpaceInMb: (int) round(($mailDiskSpace / 1024) / 1024),
            mailBoxes: (int) $stats['postboxs'],
            mailLists: (int) $stats['mail_lists'],
            mailAutoResponders: (int) $stats['mail_resps'],
            redirects: (int) $stats['redirects'],
            databases: (int) $stats['data_bases'],
            traffic: (int) $stats['traffic'],
        );
    }

    public function selectCertificate(Server $server, CertificateInstallParameters $parameters): string
    {
        try {
            $this->sslSelectClient->setServer($server);

            return $this->sslSelectClient->selectCertificate($parameters->getDomain(), $parameters->getName());
        } catch (Exception $exception) {
            $this->logger->error(
                'Install certificate',
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );
        }

        return 'error';
    }

    public function installCertificate(string $subscriptionUuid, array $data): string
    {
        $hostingDeployment = HostingDeployment::where('subscription_uuid', $subscriptionUuid)->firstOrFail();
        $parameters = CertificateInstallParameters::create($data);

        try {
            $server = $this->deploymentRepository->getServer($hostingDeployment);
            Assert::notNull($server);
            $this->sslInstallClient->setServer($server);

            $status = $this->sslInstallClient->installCertificate($parameters);
        } catch (Exception $exception) {
            $this->logger->error(
                'Install certificate',
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

            return 'error';
        }

        if ($status === 'error') {
            return $status;
        }

        return $this->selectCertificate($server, $parameters);
    }

    /**
     * Terminate a subscription.
     * We do this by deleting the customer. The subscription will automatically be deleted as well.
     *
     * @return bool true if successful or false if unsuccessful
     */
    public function terminate(string $domain, string $subscriptionUuid): bool
    {
        $hostingDeployment = HostingDeployment::where('subscription_uuid', $subscriptionUuid)
            ->with('subscription')
            ->first();

        if (is_null($hostingDeployment)) {
            $this->logger->warning(
                sprintf(
                    'The hosting deployment for domain %s could not be terminated because it does not exist. SubscriptionInfo uuid : %s',
                    $domain,
                    $subscriptionUuid,
                ),
                [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscriptionUuid,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::PLESK,
                ],
            );

            return true;
        }

        $subscription = $hostingDeployment->subscription;

        /** @var Server $server */
        $server = $hostingDeployment->server()->withTrashed()->firstOrFail();

        //Remove the domain / hosting
        $this->hostingPackageClient->setServer($server);
        $websiteDeleteParameters = WebsiteDeleteParameters::create([
            'domain' => $domain,
        ]);

        $this->logger->info(
            sprintf(
                'start deleting hosting for domain %s',
                $domain,
            ),
            [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::SUBSCRIPTION_UUID => $hostingDeployment->subscription_uuid,
                LoggingContextKeys::CUSTOMER_ID => $subscription->customer_id,
                LoggingContextKeys::SERVER_ID => $hostingDeployment->server_id,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::PLESK,
                LoggingContextKeys::PROVISIONING_ID => $hostingDeployment->id,
                LoggingContextKeys::META => [
                    'plesk_customer_username' => $hostingDeployment->plesk_customer_username,
                ],
            ],
        );

        $websiteDeleteResult = $this->hostingPackageClient->deleteWebsite($websiteDeleteParameters);
        if (Result::STATUS_ERROR === $websiteDeleteResult->getStatus()) {
            $this->logger->error(
                sprintf(
                    'The hosting deployment for domain %s could not be terminated due a error in the website deletion. SubscriptionInfo id: %d (uuid : %s)',
                    $domain,
                    $hostingDeployment->id,
                    $subscriptionUuid,
                ),
                [
                    LoggingContextKeys::RESPONSE_CODE => $websiteDeleteResult->getErrorCode(),
                    LoggingContextKeys::RESPONSE_DATA => $websiteDeleteResult->getResponseResult(),
                    LoggingContextKeys::META => [
                        'error_message' => $websiteDeleteResult->getErrorMessage(),
                    ],
                ],
            );

            return false;
        }

        $this->logger->info(
            sprintf(
                'Deleted hosting for domain %s, now checking if their are any webspaces left',
                $domain,
            ),
            [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::SUBSCRIPTION_UUID => $hostingDeployment->subscription_uuid,
                LoggingContextKeys::CUSTOMER_ID => $subscription->customer_id,
                LoggingContextKeys::SERVER_ID => $hostingDeployment->server_id,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::PLESK,
                LoggingContextKeys::PROVISIONING_ID => $hostingDeployment->id,
                LoggingContextKeys::META => [
                    'plesk_customer_username' => $hostingDeployment->plesk_customer_username,
                ],
            ],
        );

        if (! $this->userHasWebspaces($hostingDeployment)) {
            $this->logger->info(
                sprintf(
                    'No webspaces found for domain %s, removing customer',
                    $domain,
                ),
                [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $hostingDeployment->subscription_uuid,
                    LoggingContextKeys::CUSTOMER_ID => $subscription->customer_id,
                    LoggingContextKeys::SERVER_ID => $hostingDeployment->server_id,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::PLESK,
                    LoggingContextKeys::PROVISIONING_ID => $hostingDeployment->id,
                    LoggingContextKeys::META => [
                        'plesk_customer_username' => $hostingDeployment->plesk_customer_username,
                    ],
                ],
            );

            //Remove the customer
            $this->customerClient->setServer($server);

            $parameters = CustomerDeleteParameters::create(
                [
                    'customerLogin' => $hostingDeployment->plesk_customer_username,
                ],
            );

            $result = $this->customerClient->deleteCustomer($parameters);

            if (Result::STATUS_ERROR === $result->getStatus()) {
                $this->logger->error(
                    sprintf(
                        'The hosting deployment for domain %s could not be terminated due a error in the customer deletion. HostingDeployment id: %d (uuid : %s)',
                        $domain,
                        $hostingDeployment->id,
                        $subscriptionUuid,
                    ),
                    [
                        LoggingContextKeys::RESPONSE_CODE => $websiteDeleteResult->getErrorCode(),
                        LoggingContextKeys::RESPONSE_DATA => $websiteDeleteResult->getResponseResult(),
                        LoggingContextKeys::META => [
                            'error_message' => $websiteDeleteResult->getErrorMessage(),
                        ],
                    ],
                );

                return false;
            }
        }

        $spamExpertsCluster = $hostingDeployment->spamExpertsCluster;
        $hostingDeployment->delete();

        $this->logger->info(
            sprintf(
                'The hosting deployment for domain %s is successful terminated. SubscriptionInfo id: %d (uuid : %s)',
                $domain,
                $hostingDeployment->id,
                $subscriptionUuid,
            ),
            [
                LoggingContextKeys::RESPONSE_CODE => $websiteDeleteResult->getErrorCode(),
                LoggingContextKeys::RESPONSE_DATA => $websiteDeleteResult->getResponseResult(),
                LoggingContextKeys::META => [
                    'error_message' => $websiteDeleteResult->getErrorMessage(),
                ],
            ],
        );

        $this->jobDispatcher->dispatch(new RemoveDomainFromSpamFilter($domain, $spamExpertsCluster));

        return true;
    }

    /**
     * Terminate a plesk mail only subscription.
     * We do this by deleting the customer. The subscription will automatically be deleted as well.
     */
    public function terminatePleskMailOnly(string $domain, HostingDeployment $hostingDeployment): string
    {
        /** @var Server $server */
        $server = $hostingDeployment->server()->withTrashed()->firstOrFail();
        $this->customerClient->setServer($server);

        $parameters = CustomerDeleteParameters::create(
            [
                'customerLogin' => $hostingDeployment->plesk_customer_username,
            ],
        );

        $result = $this->customerClient->deleteCustomer($parameters);

        if (CustomerDeleteResult::STATUS_ERROR === $result->getStatus()) {
            $this->logger->error(
                'The hosting deployment could not be terminated.',
                [
                    LoggingContextKeys::RESPONSE_CODE => $result->getErrorCode(),
                    LoggingContextKeys::RESPONSE_DATA => $result->getErrorMessage(),
                ],
            );

            return Result::STATUS_ERROR;
        }

        return Result::STATUS_OK;
    }

    /**
     * @return array<string, string>
     */
    public function resetPassword(Parameters $parameters, UuidInterface $customerUuid): array
    {
        throw new RuntimeException('IntegratedService does not support resetting passwords yet!');
    }

    public function resetEmailPassword(Server $server, string $mailAccount, string $domain, string $password): Result
    {
        $this->hostingPackageClient->setServer($server);

        return $this->hostingPackageClient->resetEmailPassword($domain, $mailAccount, $password)->getResult();
    }

    public function deleteEmailAccount(Server $server, string $domain, string $emailAccount): Result
    {
        $this->hostingPackageClient->setServer($server);

        return $this->hostingPackageClient->deleteEmailAccount($domain, $emailAccount)->getResult();
    }

    public function createEmailAccount(Server $server, string $mailAccount, string $domain, string $password): Result
    {
        $this->hostingPackageClient->setServer($server);

        return $this->hostingPackageClient->createEmailAccount($domain, $mailAccount, $password)->getResult();
    }

    public function changeServicePlan(HostingDeployment $deployment, Product $oldProduct, Product $newProduct): Result
    {
        if ($deployment->server === null) {
            $result = new Result();
            $result->setErrorMessage('Server is null/empty.');
            $result->setStatus('error');

            $this->logger->error(
                'The upgrade or downgrade can\'t be performed.',
                [
                    LoggingContextKeys::RESPONSE_CODE => 422,
                    LoggingContextKeys::RESPONSE_DATA => $result->getErrorMessage(),
                ],
            );

            return $result;
        }

        $this->customerClient->setServer($deployment->server);
        $this->hostingPackageClient->setServer($deployment->server);

        $domain = $deployment->subscription->domain;
        Assert::notNull($domain, 'Provided subscription has no domain');
        $servicePlanGuuid = $newProduct->slug;

        $sourceHasWebsite = $this->productSpecRepository->getStringValueOfSpecification(
            $oldProduct,
            ProductSpecName::HOSTING_HAS_WEBSITE,
        );

        $targetHasWebsite = $this->productSpecRepository->getStringValueOfSpecification(
            $newProduct,
            ProductSpecName::HOSTING_HAS_WEBSITE,
        );

        // If this is a migrated subscription we should NEVER delete the website (in the changeServicePlanSwitchBetweenHostingType function)
        if (
            $deployment->subscription->migratedSubscriptions()->exists()
            || $this->hostingPackageClient->isServicePlanChangeable($domain, $servicePlanGuuid)
        ) {
            $result = $this->hostingPackageClient->changeServicePlan($domain, $servicePlanGuuid);
        } else {
            $parameters = Parameters::create(
                [
                    'contactPersonName' => $deployment->subscription->customer->getContactNameAttribute(),
                    'emailAddress' => $deployment->subscription->customer->getEmail(),
                    'domain' => $domain,
                    'ipv4Address' => $deployment->server->ipv4,
                    'ipv6Address' => $deployment->server->ipv6,
                    'username' => $deployment->plesk_customer_username,
                    'password' => $this->generatePassword(),
                    'specs' => [],
                    'phpVersion' => null,
                    'forwardingUrl' => null,
                    'enableDns' => 'OFF',
                    'enableSsh' => 'OFF',
                    'enableSsl' => 'OFF',
                    'notify' => 'yes',
                    'package' => $deployment->subscription->product->slug,
                    'customer_id' => (string) $deployment->plesk_customer_id,
                ],
            );

            $result = $this->hostingPackageClient->changeServicePlanSwitchBetweenHostingType(
                $parameters,
                $domain,
                $servicePlanGuuid,
            );
        }

        if ($sourceHasWebsite === '0' && $targetHasWebsite === '1') {
            if (! $this->setFtpPassword($deployment)) {
                $this->logger->error(
                    'Failed to set FTP password during upgrade for domain ' . $domain,
                    [
                        LoggingContextKeys::SUBSCRIPTION_UUID => $deployment->subscription->uuid,
                        LoggingContextKeys::DOMAIN_NAME => $domain,
                    ],
                );
            }

            $syncResult = $this->syncSubscription($deployment, $domain);
            if ($syncResult->getStatus() !== Result::STATUS_OK) {
                $this->logger->error(
                    'Failed to sync subscription during upgrade',
                    [
                        LoggingContextKeys::SUBSCRIPTION_UUID => $deployment->subscription->uuid,
                        LoggingContextKeys::DOMAIN_NAME => $domain,
                        LoggingContextKeys::RESPONSE_CODE => $syncResult->getErrorCode(),
                        LoggingContextKeys::RESPONSE_DATA => $syncResult->getErrorMessage(),
                    ],
                );
                $result->setStatus(Result::STATUS_ERROR);
                $result->setErrorCode($syncResult->getErrorCode() ?? 0);
                $result->setErrorMessage('Subscription sync failed: ' . $syncResult->getErrorMessage());
            }
        }

        return $result;
    }

    public function setEmailCatchAll(Server $server, string $domain, string $destinationEmailAddresses): string
    {
        $parameters = EmailSetCatchAllParameters::create(
            [
                'domain' => $domain,
                'destinationEmailAddress' => $destinationEmailAddresses,
            ],
        );

        $this->hostingPackageClient->setServer($server);
        $result = $this->hostingPackageClient->setEmailCatchAll($parameters);

        return $result->getStatus() ?? Result::STATUS_ERROR;
    }

    public function coupleDomainToExistingHosting(
        DomainDeployment $domainDeployment,
        HostingDeployment $hostingDeployment,
    ): bool {
        $pleskUsername = $hostingDeployment->plesk_customer_username;
        $domain = $domainDeployment->subscription->domain;

        $this->logger->debug(
            sprintf('Coupling domain [%s] to existing Plesk hosting [%s]', $domain, $pleskUsername),
            [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::PLESK,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING,
                LoggingContextKeys::META => [
                    'hosting_deployment_id' => $hostingDeployment->id,
                    'domain_deployment_id' => $domainDeployment->id,
                    'server' => $hostingDeployment->server?->domain,
                    'server_id' => $hostingDeployment->server?->id,
                ],
            ],
        );

        Assert::stringNotEmpty($pleskUsername);
        Assert::isInstanceOf($hostingDeployment->server, Server::class);

        $this->hostingPackageClient->setServer($hostingDeployment->server);
        $space = $this->hostingPackageClient->getWebspaces($pleskUsername);

        $spaceId = Arr::get($space->getResponseBody(), 'webspace.get.result.id');

        Assert::string($spaceId);
        Assert::string($domain);

        $result = $this->hostingPackageClient->createSite($domain, (int) $spaceId);

        if ($result->getStatus() === Result::STATUS_OK) {
            $this->setDnsForHosting($hostingDeployment->server, $domain);
            $this->jobDispatcher->dispatch(new AddDomainToSpamFilter($domain, null));

            return true;
        }

        $this->logger->warning(
            'Failed to couple domain to existing hosting',
            [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::PLESK,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING,
                LoggingContextKeys::RESPONSE_CODE => $result->getErrorCode(),
                LoggingContextKeys::RESPONSE_DATA => $result->getErrorMessage(),
                LoggingContextKeys::META => [
                    'hosting_deployment_id' => $hostingDeployment->id,
                    'domain_deployment_id' => $domainDeployment->id,
                    'server' => $hostingDeployment->server->domain,
                    'server_id' => $hostingDeployment->server->id,
                ],
            ],
        );

        return false;
    }

    public function setDnsForHosting(Server $server, string $domain): void
    {
        $changes = $this->dnsZoneService->getHostingDnsRecords(
            $domain,
            $server->getIpv4(),
            $server->getIpv6(),
        );

        $this->eventDispatcher->dispatch(new ReplaceParkingAndUpdateDns($domain, $changes));
    }

    public function resetDnsForSitebuilder(Server $server, Server $mailOnlyServer, string $domain): void
    {
        $ipv4Host = $server->getIpv4();
        $ipv6Host = $server->getIpv6();

        $ipv4HostMail = $mailOnlyServer->getIpv4();
        $ipv6HostMail = $mailOnlyServer->getIpv6();

        $changes = $this->dnsZoneService->getExternalHostingDnsRecords(
            $domain,
            $ipv4Host,
            $ipv6Host,
            $ipv4HostMail,
            $ipv6HostMail,
        );

        $this->eventDispatcher->dispatch(new ReplaceParkingAndUpdateDns($domain, $changes));
    }

    public function getCoupledHostingByDomain(DomainDeployment $domainSubscription): ?HostingDeployment
    {
        throw new NotImplementedException();
    }

    public function decoupleHostingByDomain(DomainDeployment $domainDeployment): void
    {
        Assert::string($domainDeployment->subscription->domain);

        $result = $this->hostingPackageClient->removeSite($domainDeployment->subscription->domain);

        if ($result->getStatus() !== Result::STATUS_OK) {
            throw new PleskClientException(
                sprintf(
                    'Could not remove domaindeployment %d (%s) from Plesk. RemoveSite failed with response: %s',
                    $domainDeployment->id,
                    $domainDeployment->subscription->domain,
                    $result->getErrorMessage(),
                ),
            );
        }
    }

    /**
     * @throws HostingException
     */
    public function getDomainOccupation(HostingDeployment $hostingDeployment): DomainOccupation
    {
        $pleskUsername = $hostingDeployment->plesk_customer_username;
        Assert::stringNotEmpty($pleskUsername);
        Assert::isInstanceOf($hostingDeployment->server, Server::class);

        $this->customerClient->setServer($hostingDeployment->server);
        $domains = $this->customerClient->getDomainList($pleskUsername);
        $domainsOnServer = array_map(fn (Domain $domain) => $domain->name, $domains->domains);
        $domainsInUse = count(array_filter($domains->domains, fn (Domain $domain) => $domain->isMain));

        $this->hostingPackageClient->setServer($hostingDeployment->server);
        $webspaceResult = $this->hostingPackageClient->getWebspaces($pleskUsername);

        /** @var array<int, array<string, string>> $limitsArray */
        $limitsArray = Arr::get($webspaceResult->getResponseBody(), 'webspace.get.result.data.limits.limit', []);
        $limits = new Collection($limitsArray);

        /**
         * @var ?string $maxDomainString
         *
         */
        $maxDomainString = $limits
            ->filter(fn ($limit) => array_key_exists('name', $limit) && $limit['name'] === 'max_site')
            ->pluck('value')
            ->first();

        if ($maxDomainString === null) {
            throw new HostingException(sprintf(
                'Could not retrieve max domains from Plesk for user %s.',
                $pleskUsername,
            ));
        }

        $maxDomains = intval($maxDomainString);

        return new DomainOccupation(
            hostingSubscription: $hostingDeployment,
            domains: $domainsOnServer,
            domainsInUse: $domainsInUse,
            domainsAvailable: $maxDomains - $domainsInUse,
            maxDomains: $maxDomains,
        );
    }

    public function getSsoUrl(
        string $username,
        Server $server,
        string $ipAddress,
        bool $redirectToMail = false,
    ): string {
        return $this->pleskGetSsoUrlAction->execute($server, $username, $ipAddress, $redirectToMail);
    }

    /**
     * @throws PleskClientException
     * @throws JsonException
     * @throws InvalidArgumentException
     */
    public function suspend(HostingDeployment $hostingDeployment): void
    {
        $server = $this->deploymentRepository->getServer($hostingDeployment);

        if ($server === null) {
            throw new InvalidArgumentException(
                sprintf(
                    'Server is not set for subscription uuid: %s',
                    $hostingDeployment->subscription->uuid,
                ),
            );
        }

        $this->pleskSuspendHostingAction->execute($server, $hostingDeployment);
    }

    /**
     * @throws PleskClientException
     * @throws JsonException
     * @throws InvalidArgumentException
     */
    public function unsuspend(HostingDeployment $hostingDeployment): void
    {
        $server = $this->deploymentRepository->getServer($hostingDeployment);

        if ($server === null) {
            throw new InvalidArgumentException(
                sprintf(
                    'Server is not set for subscription uuid: %s',
                    $hostingDeployment->subscription->uuid,
                ),
            );
        }

        $this->pleskUnsuspendHostingAction->execute($server, $hostingDeployment);
    }

    public function getUserConfig(string $identifier, Server $server): array
    {
        $this->customerClient->setServer($server);

        $params = new Parameters();
        $params->setUsername($identifier);

        return $this->customerClient->fetchCustomer($params)->toArray();
    }

    /**
     * Please only use this function for migrations!!!
     *
     * @return array<mixed>
     */
    public function getUserConfigAsAdmin(string $identifier, Server $server): array
    {
        return $this->getUserConfig($identifier, $server);
    }

    public function getUserConfigAsDto(string $identifier, Server $server): SiteConfigInterface
    {
        $params = new Parameters();
        $params->setUsername($identifier);

        $this->customerClient->setServer($server);
        $this->hostingPackageClient->setServer($server);

        $space = $this->hostingPackageClient->getWebspaces($identifier);

        if ($space->getErrorMessage() !== '') {
            $error = sprintf(
                'getUserConfigAsDto error: %s for identifier: %s',
                $space->getErrorMessage(),
                $identifier,
            );

            throw new PleskClientException($error);
        }

        $defaultDomain = $this->resolveMainDomainFromWebspace($space->getResponseBody(), $server, $identifier);
        $params->setDomain($defaultDomain);

        // One deployment is coupled to a single user which will in
        // turn be coupled to a single subscription with a single plan
        // so it's safe in our use case to take the first.
        /** @var string|null $planGuid */
        $planGuid = Arr::get(
            $space->getResponseBody(),
            'webspace.get.result.data.subscriptions.subscription.plan.plan-guid',
        );
        $plan = 'unknown';

        if ($planGuid !== null) {
            $params->setPackage($planGuid);
            $plan = $this->hostingPackageClient->getServicePlanByGuid($params);
        }

        /** @var array<int, array<string, string>> $limits */
        $limits = Arr::get($space->getResponseBody(), 'webspace.get.result.data.limits.limit', []);

        $maxDomains = -1;
        $maxMailAccounts = -1;
        $maxDb = -1;
        $maxNetwork = -1;
        $maxDisk = -1;

        foreach ($limits as $limit) {
            if ($limit['name'] === 'max_site') {
                $maxDomains = (int) $limit['value'];
            }

            if ($limit['name'] === 'max_box') {
                $maxMailAccounts = (int) $limit['value'];
            }

            if ($limit['name'] === 'max_db') {
                $maxDb = (int) $limit['value'];
            }

            if ($limit['name'] === 'max_traffic') {
                $maxNetwork = (int) $limit['value'];
            }

            if ($limit['name'] === 'disk_space') {
                $maxDisk = (int) $limit['value'];
            }
        }

        return new SiteConfig(
            zoneStatus: 'enabled',
            identifier: $identifier,
            // Since with plesk resellers and normal hosting are
            // separated. Reseller will always be false here since it would never
            // be able to get fetched in the first place.
            isReseller: false,
            package: $plan,
            hasSsoEnabled: true,
            maxAmountDomains: $maxDomains,
            maxAmountMailAccounts: $maxMailAccounts,
            maxAmountDatabases: $maxDb,
            // max network traffic per month
            maxNetworkTrafficInMB: $maxNetwork > -1 ? (int) round(($maxNetwork / 1024) / 1024) : $maxNetwork,
            // base maxDisk in bytes. DTO needs megabytes
            maxDiskSpaceInMB: $maxDisk > -1 ? (int) round(($maxDisk / 1024) / 1024) : $maxDisk,
            domain: $defaultDomain,
        );
    }

    public function getDefaultDomain(string $username, Server $server): ?string
    {
        $this->hostingPackageClient->setServer($server);
        $space = $this->hostingPackageClient->getWebspaces($username);

        if ($space->getErrorMessage() !== '') {
            $error = sprintf(
                'getDefaultDomain error: %s for identifier: %s',
                $space->getErrorMessage(),
                $username,
            );

            throw new PleskClientException($error);
        }

        return $this->resolveMainDomainFromWebspace($space->getResponseBody(), $server, $username);
    }

    /**
     * @return array<mixed>
     */
    public function getPackagesOnServer(Server $server): array
    {
        $this->hostingPackageClient->setServer($server);

        $params = new Parameters();

        return $this->hostingPackageClient->getServicePlans($params);
    }

    public function setFtpPassword(HostingDeployment $hostingDeployment): bool
    {
        Assert::notNull($hostingDeployment->server);
        Assert::notNull($hostingDeployment->subscription->domain);
        Assert::notNull($hostingDeployment->plesk_customer_username);

        $this->hostingPackageClient->setServer($hostingDeployment->server);

        $password = $this->pleskPassword->generatePassword();

        return (
            $this->hostingPackageClient
                ->setFtpPassword(
                    domain: $hostingDeployment->subscription->domain,
                    user: $hostingDeployment->plesk_customer_username,
                    password: $password,
                )
                ->getStatus() === Result::STATUS_OK
        );
    }

    public function getPackageOnServer(Server $server, string $packageName): array
    {
        $this->hostingPackageClient->setServer($server);

        $params = new Parameters();
        $params->setPackage($packageName);

        return $this->hostingPackageClient->getServicePlan($params);
    }

    public function getPackageOnServerAsDto(Server $server, string $packageName): HostingOfferingInterface
    {
        $packageArray = $this->getPackageOnServer($server, $packageName);

        /** @var array<int, array<string, string>> $limits */
        $limits = Arr::get($packageArray, 'limits.limit', []);

        $maxDomains = -1;
        $maxMailAccounts = -1;
        $maxDb = -1;
        $maxNetwork = -1;
        $maxDisk = -1;

        foreach ($limits as $limit) {
            if ($limit['name'] === 'max_site') {
                $maxDomains = (int) $limit['value'];
            }

            if ($limit['name'] === 'max_box') {
                $maxMailAccounts = (int) $limit['value'];
            }

            if ($limit['name'] === 'max_db') {
                $maxDb = (int) $limit['value'];
            }

            if ($limit['name'] === 'max_traffic') {
                $maxNetwork = (int) $limit['value'];
            }

            if ($limit['name'] === 'disk_space') {
                $maxDisk = (int) $limit['value'];
            }
        }

        return new PleskHostingPackage(
            maxAmountDomains: $maxDomains,
            maxAmountMailAccounts: $maxMailAccounts,
            maxAmountDatabases: $maxDb,
            // max network traffic per month
            maxNetworkTrafficInMB: $maxNetwork > -1 ? (int) round(($maxNetwork / 1024) / 1024) : $maxNetwork,
            // base maxDisk in bytes. DTO needs megabytes
            maxDiskSpaceInMB: $maxDisk > -1 ? (int) round(($maxDisk / 1024) / 1024) : $maxDisk,
            package: $packageName,
        );
    }

    public function isUsingHostingServerAsNameserver(
        ?string $ipv4HostingServer,
        ?string $ipv6HostingServer,
        SiteConfigInterface $userConfig,
    ): bool {
        $domain = $userConfig->getDomain();

        if ($domain === null) {
            return false;
        }

        $nsRecords = $this->dnsHelper->dnsGetRecord($domain, DNS_NS);

        if ($nsRecords === false) {
            return false;
        }

        $nsIPAddresses = array_map(
            fn (array $record): string => $this->dnsHelper->getHostByName($record['target']),
            $nsRecords,
        );

        return new Collection($nsIPAddresses)->contains(
            fn ($nsIPAddress): bool => $nsIPAddress === $ipv4HostingServer || $nsIPAddress === $ipv6HostingServer,
        );
    }

    public function getCustomerDomainsForDkim(HostingDeployment $hostingDeployment): array
    {
        Assert::notNull($hostingDeployment->server);
        Assert::notNull($hostingDeployment->plesk_customer_username);

        $this->customerClient->setServer($hostingDeployment->server);
        $result = $this->customerClient->getDomainList($hostingDeployment->plesk_customer_username);

        $domains = [];
        foreach ($result->domains as $domain) {
            if ($domain->type === self::DOMAIN_TYPE_ALIAS || $domain->type === self::DOMAIN_TYPE_SUBDOMAIN) {
                continue;
            }

            $domains[] = $domain->name;
        }

        return $domains;
    }

    public function isDkimEnabled(HostingDeployment $hostingDeployment, string $domain): bool
    {
        Assert::notNull($hostingDeployment->server);

        $this->hostingPackageClient->setServer($hostingDeployment->server);

        return $this->hostingPackageClient->isDkimEnabled($domain);
    }

    public function setDkim(HostingDeployment $hostingDeployment, string $domain, bool $enable): void
    {
        Assert::notNull($hostingDeployment->server);

        $this->hostingPackageClient->setServer($hostingDeployment->server);
        $this->hostingPackageClient->setDkim($enable, $domain);
    }

    public function getDkimRecord(HostingDeployment $hostingDeployment, string $domain): ?DnsRecord
    {
        Assert::notNull($hostingDeployment->server);

        $this->hostingPackageClient->setServer($hostingDeployment->server);
        $result = $this->hostingPackageClient->getDnsRecords($domain);

        $dkimRecords = array_filter(
            $result->records,
            fn ($record) => $record->type === 'TXT' && str_contains($record->value, 'v=DKIM1'),
        );

        if (count($dkimRecords) === 0) {
            return null;
        }

        if (count($dkimRecords) > 1) {
            $this->logger->warning(
                'Multiple dkim records found for domain {domain.name}, picking the first one.',
                [
                    LoggingContextKeys::DOMAIN_NAME => $hostingDeployment->subscription->domain,
                ],
            );
        }

        /** @var DnsRecord $dkimRecord */
        $dkimRecord = current($dkimRecords);

        return new DnsRecord($dkimRecord->type, $dkimRecord->host, $dkimRecord->value);
    }

    public function syncSubscription(HostingDeployment $hostingDeployment, string $domain): Result
    {
        Assert::notNull($hostingDeployment->server);

        $this->hostingPackageClient->setServer($hostingDeployment->server);
        $result = $this->hostingPackageClient->syncSubscription($domain);

        if ($result->getStatus() !== Result::STATUS_OK) {
            $this->logger->warning('Error when syncing plesk subscription', [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::PROVISIONING_ID => $hostingDeployment->id,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::PLESK,
                LoggingContextKeys::META => [
                    'error_code' => $result->getErrorCode(),
                    'error_message' => $result->getErrorMessage(),
                ],
            ]);

            $hostingDeployment->last_created_result_received = CarbonImmutable::now();
            $hostingDeployment->last_created_result = $result->getErrorMessage();
            $hostingDeployment->save();
        }

        return $result;
    }

    private function userHasWebspaces(HostingDeployment $hostingDeployment): bool
    {
        Assert::notNull($hostingDeployment->plesk_customer_username);
        $webspaces = $this->hostingPackageClient->getWebspaces($hostingDeployment->plesk_customer_username);
        $subscription = $hostingDeployment->subscription;

        if ($webspaces->getStatus() !== Result::STATUS_OK) {
            $this->logger->warning(
                'Error when retrieving plesk customer webspaces. Returning true as fallback.',
                [
                    LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::CUSTOMER_ID => $subscription->customer_id,
                    LoggingContextKeys::SERVER_ID => $hostingDeployment->server_id,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING,
                    LoggingContextKeys::PROVISIONING_ID => $hostingDeployment->id,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::PLESK,
                    LoggingContextKeys::META => [
                        'plesk_customer_username' => $hostingDeployment->plesk_customer_username,
                    ],
                ],
            );

            return true;
        }

        /** @var array<mixed>|null $sites */
        $sites = Arr::get($webspaces->getResponseBody(), 'webspace.get.result');

        $this->logger->debug(
            sprintf(
                'Retrieving plesk customer [%s] webspaces. Found %d',
                $hostingDeployment->plesk_customer_username,
                $sites !== null ? count($sites) : 0,
            ),
            [
                LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                LoggingContextKeys::CUSTOMER_ID => $subscription->customer_id,
                LoggingContextKeys::SERVER_ID => $hostingDeployment->server_id,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING,
                LoggingContextKeys::PROVISIONING_ID => $hostingDeployment->id,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::PLESK,
                LoggingContextKeys::RESPONSE_DATA => $webspaces->getResponseBody(),
                LoggingContextKeys::META => [
                    'plesk_customer_username' => $hostingDeployment->plesk_customer_username,
                ],
            ],
        );

        return $sites !== null && count($sites) > 0;
    }

    private function disableDnsZones(Parameters $parameters): void
    {
        $server = $parameters->getServer();
        Assert::isInstanceOf($server, Server::class);

        $this->customerClient->setServer($server);

        $domainList = $this->customerClient->getDomainList($parameters->getUsername());

        $this->logger->info('Plesk getDomainList results', [
            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING->value,
            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::PLESK->value,
            LoggingContextKeys::SERVER_HOSTNAME => $server->hostname,
            LoggingContextKeys::META => [
                'plesk_customer_username' => $parameters->getUsername(),
                'status' => $domainList->getStatus(),
                'result_message' => $domainList->getResponseResult(),
                'error_code' => $domainList->getErrorCode(),
                'error_message' => $domainList->getErrorMessage(),
            ],
        ]);

        foreach ($domainList->domains as $domain) {
            if ($domain->type === self::DOMAIN_TYPE_ALIAS) {
                continue;
            }

            $this->logger->info('Plesk attempting disableDnsZone', [
                LoggingContextKeys::DOMAIN_NAME => $domain->name,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING->value,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::PLESK->value,
                LoggingContextKeys::SERVER_HOSTNAME => $server->hostname,
                LoggingContextKeys::META => [
                    'plesk_customer_username' => $parameters->getUsername(),
                ],
            ]);

            $this->hostingPackageClient->setServer($server);

            try {
                $result = $this->hostingPackageClient->disableDnsZone($domain->name);

                $this->logger->info('Plesk disableDnsZone result', [
                    LoggingContextKeys::DOMAIN_NAME => $domain->name,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING->value,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::PLESK->value,
                    LoggingContextKeys::SERVER_HOSTNAME => $server->hostname,
                    LoggingContextKeys::META => [
                        'plesk_customer_username' => $parameters->getUsername(),
                        'status' => $result->getStatus(),
                        'result_message' => $result->getResponseResult(),
                    ],
                ]);
            } catch (Throwable $exception) { // @phpstan-ignore thecodingmachine.exceptionMustBeRethrown
                $this->logger->info('Plesk disableDnsZone resulted in exception', [
                    LoggingContextKeys::DOMAIN_NAME => $domain->name,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING->value,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::PLESK->value,
                    LoggingContextKeys::SERVER_HOSTNAME => $server->hostname,
                    LoggingContextKeys::META => [
                        'plesk_customer_username' => $parameters->getUsername(),
                        'exception' => $exception,
                    ],
                ]);

                continue;
            }
        }
    }

    /**
     * @param array<IsMailable> $recipients
     */
    private function sendEmail(
        HostingDeployment $hostingDeployment,
        array $recipients,
        string $username,
        string $password,
        string $domain,
        ?string $ipv4Address,
    ): void {
        if ($this->isPleskMailOnly($hostingDeployment->subscription->product)) {
            $this->mailer->send(
                $recipients,
                new MailPleskEmailOnlyDetails(
                    username: $username,
                    domain: $domain,
                    ipv4_address: $ipv4Address,
                    password: $password,
                ),
            );

            return;
        }

        $this->mailer->send(
            $recipients,
            new MailPleskDetails(
                username: $username,
                domain: $domain,
                ipv4_address: $ipv4Address,
                password: $password,
            ),
        );
    }

    private function generatePassword(): string
    {
        $passwordNumBaseLength = 5;
        $passwordSpecialBaseLength = 2;

        $numbers = '123456789';
        $specials = '!@#$%^*?_~';

        $password = Str::random();
        $maxNum = strlen($numbers) - 1;
        $maxSpecial = strlen($specials) - 1;

        for ($i = 0; $i < $passwordNumBaseLength; $i++) {
            $password .= $numbers[random_int(0, $maxNum)];
        }

        for ($i = 0; $i < $passwordSpecialBaseLength; $i++) {
            $password .= $specials[random_int(0, $maxSpecial)];
        }

        assert($password !== '');

        return $password;
    }

    private function generateUsername(): string
    {
        $username = $this->pleskUsernameBroker->generateUsername();

        return HostingDeployment::where('plesk_customer_username', $username)->exists()
            ? $this->generateUsername()
            : $username;
    }

    private function isPleskMailOnly(Product $product): bool
    {
        $pleskSlugConfig = Config::get('hostingservice.plesk.mail_only_slugs');

        if ($pleskSlugConfig === null) {
            $this->logger->warning(
                'No plesk mail only slugs have been set. Check the .env for PLESK_MAIL_ONLY_START_SLUG & PLESK_MAIL_ONLY_MAX_SLUG',
            );

            return false;
        }

        assert(is_array($pleskSlugConfig));

        /** @var string[] $mailOnlySlugs */
        $mailOnlySlugs = array_filter($pleskSlugConfig, fn (mixed $value): bool => (bool) $value);

        return in_array($product->slug, $mailOnlySlugs, true);
    }

    /**
     * @param array<mixed> $space
     */
    private function resolveMainDomainFromWebspace(array $space, Server $server, string $identifier): string
    {
        $defaultDomain = Arr::get($space, 'webspace.get.result.data.gen_info.name');

        return is_string($defaultDomain)
            ? $defaultDomain
            : sprintf(
                '%s.%s',
                $identifier,
                $server->hostname,
            );
    }
}
