<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\DirectAdmin\Services;

use Carbon\CarbonImmutable;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Bus\Dispatcher as JobDispatcher;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use JsonException;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;
use ReflectionException;
use RuntimeException;
use Waterfront\Domain\DNS\Events\ReplaceParkingAndUpdateDns;
use Waterfront\Domain\DNS\Services\DnsZoneService;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Domains\Rules\DomainNameRule;
use Waterfront\Domain\Email\Dto\Recipient;
use Waterfront\Domain\Email\Jobs\AddDomainToSpamFilter;
use Waterfront\Domain\Email\Jobs\RemoveDomainFromSpamFilter;
use Waterfront\Domain\Hosting\Actions\DirectAdmin\DirectAdminGetSsoUrlAction;
use Waterfront\Domain\Hosting\Actions\DirectAdmin\DirectAdminSuspendHostingAction;
use Waterfront\Domain\Hosting\Actions\DirectAdmin\DirectAdminUnsuspendHostingAction;
use Waterfront\Domain\Hosting\DirectAdmin\DirectAdminPassword;
use Waterfront\Domain\Hosting\DirectAdmin\DTO\DomainOccupation;
use Waterfront\Domain\Hosting\DirectAdmin\Exceptions\CoupleHostingException;
use Waterfront\Domain\Hosting\DirectAdmin\Mailer\MailDirectAdminDetails;
use Waterfront\Domain\Hosting\DTO\DnsRecord;
use Waterfront\Domain\Hosting\DTO\UserStatistics;
use Waterfront\Domain\Hosting\Exceptions\HostingException;
use Waterfront\Domain\Hosting\Interfaces\Drivers\HostingServiceInterface;
use Waterfront\Domain\Hosting\Interfaces\Hosting\HostingOfferingInterface;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\HostingDeploymentInterface;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Parameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Domain\Hosting\Interfaces\Hosting\SiteConfigInterface;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Repositories\HostingDeploymentRepository;
use Waterfront\Domain\Hosting\Repositories\ServerRepository;
use Waterfront\Domain\Mailer\Exceptions\MailValidationException;
use Waterfront\Domain\Mailer\Mailer;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Exceptions\ServerNotFoundException;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Ssl\Interfaces\Models\CertificateInstall\Parameters as CertificateInstallParameters;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Infra\DirectAdminClient\BehavesAsDirectAdmin;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;
use Waterfront\Infra\DirectAdminClient\Commands\Domains\DeleteDomains;
use Waterfront\Infra\DirectAdminClient\Commands\Domains\EnableDisableDKIM;
use Waterfront\Infra\DirectAdminClient\Commands\Domains\FetchDkimRecord;
use Waterfront\Infra\DirectAdminClient\Commands\Domains\GetEmail;
use Waterfront\Infra\DirectAdminClient\Commands\Domains\ModifyDomain;
use Waterfront\Infra\DirectAdminClient\Commands\Domains\ShowAllUserDomains;
use Waterfront\Infra\DirectAdminClient\Commands\Ftp\ChangePassword as ChangeFtpPassword;
use Waterfront\Infra\DirectAdminClient\Commands\Resellers\ShowResellerIPs;
use Waterfront\Infra\DirectAdminClient\Commands\Ssl\DisableLetsEncryptAutoRenew;
use Waterfront\Infra\DirectAdminClient\Commands\Ssl\UploadCaCrt;
use Waterfront\Infra\DirectAdminClient\Commands\Ssl\UploadSsl;
use Waterfront\Infra\DirectAdminClient\Commands\Users\ChangePassword;
use Waterfront\Infra\DirectAdminClient\Commands\Users\DeleteUsers;
use Waterfront\Infra\DirectAdminClient\Commands\Users\ModifyUser;
use Waterfront\Infra\DirectAdminClient\Commands\Users\ShowUserStats;
use Waterfront\Infra\DirectAdminClient\Connection\DirectAdminServer;
use Waterfront\Infra\DirectAdminClient\DTO\DirectAdminUserPackage;
use Waterfront\Infra\DirectAdminClient\DTO\UserConfig;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminException;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminFieldException;
use Waterfront\Infra\DirectAdminClient\Serializers\DirectAdminSerializerFactory;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Exceptions\NotImplementedException;
use Waterfront\Support\Helpers\DnsHelper;
use Webmozart\Assert\Assert;

class DirectAdminHostingService implements HostingServiceInterface
{
    public function __construct(
        private readonly HostingDeploymentRepository $subscriptionRepo,
        private readonly ServerRepository $serverRepository,
        private readonly DnsZoneService $dnsZoneService,
        private readonly BehavesAsDirectAdmin $directAdmin,
        private readonly DirectadminUsernameBroker $broker,
        private readonly Mailer $mailer,
        private readonly DomainNameRule $domainNameRule,
        private readonly JobDispatcher $jobDispatcher,
        private readonly EventDispatcher $eventDispatcher,
        private readonly DirectAdminGetSsoUrlAction $directAdminGetSsoUrlAction,
        private readonly DirectAdminSuspendHostingAction $directAdminSuspendHostingAction,
        private readonly DirectAdminUnsuspendHostingAction $directAdminUnsuspendHostingAction,
        private readonly DirectAdminPassword $passwordGenerator,
        private readonly DnsHelper $dnsHelper,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Gets a suitable server and fetches the secret key if not already available.
     *
     */
    public function getServer(?HostingDeploymentInterface $hostingDeployment = null): Server
    {
        return $hostingDeployment->server ?? $this->findServer(); /** @phpstan-ignore property.notFound */
    }

    public function findServer(): Server
    {
        return $this->serverRepository->findAvailableServer(
            serverType: ServerType::DIRECTADMIN,
        );
    }

    /**
     * Create a hosting package.
     *
     * @throws RuntimeException
     */
    public function createPackage(Parameters $parameters): Result
    {
        throw new RuntimeException(
            'Packages are already predefined on the directadmin servers (brons, zilver, groot)!
            Custom Packages not supported at this time!'
        );
    }

    /**
     * @param mixed[] $specs
     *
     * @throws MailValidationException
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws Exception
     *
     * @return mixed[]
     *
     */
    public function create(
        string $contactPersonName,
        string $contactEmail,
        string $customerEmail,
        UuidInterface $customerUuid,
        string $subscriptionUuid,
        array $specs,
        ?Server $server = null,
        ?string $forwardingUrl = null,
        ?string $domain = null
    ): array {
        $result = TechnicalStatus::ERROR->value;
        $return = [];

        $username = $this->generateUsername();
        $hasToGenerateDomain  = is_null($domain);

        if (! $hasToGenerateDomain) {
            if (! $this->isValidDomain($domain)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Invalid domain: %s',
                        $domain
                    )
                );
            }
        }

        // Find a compatible server if not set
        $server ??= $this->getServer();

        if ($hasToGenerateDomain) {
            /**
             * We can no longer use the server hostname because CLDIN changed
             * that the domain with a brand name is now forbidden.
             *
             * @see https://cldin.atlassian.net/servicedesk/customer/portal/5/CLDIN-9593
             */
            $domain = $username . '.com';
        }

        // Log creating the hosting
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
            ]
        );

        // Setup data to use to create customer and package
        $parameters = Parameters::create(
            [
                'contactPersonName' => $contactPersonName,
                'emailAddress' => $customerEmail,
                'domain' => $domain,
                'username' => $username,
                'ipv4Address' => $server->getIpv4(),
                'ipv6Address' => $server->getIpv6(),
                // Add 1 off all the must-haves to enforce a correct variation for DA.
                'password' => $this->passwordGenerator->generatePassword(12),
                'specs' => $specs,
                'phpVersion' => $server->getPhpVersion(),
                'forwardingUrl' => $forwardingUrl,
                'directAdminUserName' => $username,
                'enableDns' => 'OFF',
                'enableSsh' => 'OFF',
                'enableSsl' => 'ON',
                'notify' => 'yes',
                'package' => 'standard',
            ]
        );

        // Create the customer, update parameters with user id, then create the package
        $createCustomerResult = $this->createCustomer($parameters);

        $recipient = new Recipient($contactPersonName, $contactEmail, $customerUuid);

        // Check if email should be sent to user with hosting info
        $this->mailer->send(
            [$recipient],
            new MailDirectAdminDetails(
                $parameters->getUsername(),
                $parameters->getPassword(),
                $parameters->getDomain(),
                $parameters->getIpv4Address(),
                $server->use_ssl,
                $server->port,
            )
        );

        $hostingDeployment = HostingDeployment::query()
            ->where('subscription_uuid', $subscriptionUuid)
            ->first();

        // Create the subscription for hosting if it does not already exists.
        if ($hostingDeployment === null) {
            $hostingDeployment = $this->subscriptionRepo->create(
                [
                    'directadmin_customer_username' => $parameters->getUsername(),
                ],
                $subscriptionUuid,
                $server
            );

            $hostingDeployment->last_created_result = $createCustomerResult->getResponseBody()
                ?? json_encode($parameters->toArray(), JSON_THROW_ON_ERROR);

            $hostingDeployment->last_created_result_received = CarbonImmutable::now();
        }

        $hostingDeployment->directadmin_customer_username = $parameters->getUsername();
        $hostingDeployment->server_id = $server->id;
        $hostingDeployment->save();

        // Update DNS after we know the server
        if (! $hasToGenerateDomain) {
            $this->setDnsForHosting(server: $server, domain: $domain);
            $this->jobDispatcher->dispatch(new AddDomainToSpamFilter($domain, null));
        }

        if ($createCustomerResult->hasSucceeded()) {
            $result = TechnicalStatus::OK->value;
        }

        $return['result'] = $result;
        $return['domain'] = $domain;
        $return['username'] = $parameters->getUsername();

        return $return;
    }

    /**
     * @throws CoupleHostingException
     * @throws DirectAdminCommandException
     * @throws GuzzleException
     * @throws ReflectionException
     */
    public function decoupleHostingByDomain(DomainDeployment $domainDeployment): void
    {
        $hostingDeployment = $this->getCoupledHostingByDomain($domainDeployment);

        $domain = $domainDeployment->subscription->domain;
        Assert::notNull($domain, 'Provided subscription has no domain');

        if ($hostingDeployment === null) {
            throw new CoupleHostingException(
                sprintf(
                    'Could not find any coupled hosting for %s in Domain deployment %d with Subscription %s',
                    $domain,
                    $domainDeployment->id,
                    $domainDeployment->subscription->uuid
                )
            );
        }

        $server = $this->subscriptionRepo->getServer($hostingDeployment);
        assert($server instanceof Server);

        $deleteDomainCommand = new DeleteDomains();
        $deleteDomainCommand->addDomain($domain);

        $directAdminData = $this->directAdmin
            ->useServer($server)
            ->loginAs((string) $hostingDeployment->directadmin_customer_username)
            ->call($deleteDomainCommand);

        if (! $directAdminData->hasSucceeded()) {
            throw new DirectAdminCommandException(
                sprintf(
                    'Could not remove Domaindeployment %d (%s) from Hostingdeployment %d. DeleteDomain command failed with response: %s',
                    $domainDeployment->id,
                    $domain,
                    $hostingDeployment->id,
                    $directAdminData->getResponseBody()
                )
            );
        }
    }

    /**
     * @throws DirectAdminCommandException|GuzzleException
     */
    public function getCoupledHostingByDomain(DomainDeployment $domainDeployment): ?HostingDeployment
    {
        $domain = $domainDeployment->subscription->domain;
        Assert::notNull($domain, 'Provided subscription has no domain');

        $customer = $domainDeployment->subscription->customer;

        $customerHostingSubscriptions = $this->subscriptionRepo->getActiveSharedByCustomer($customer);

        foreach ($customerHostingSubscriptions as $hostingDeployment) {
            $daUserDataCommand = new ShowUserStats();
            $daUserDataCommand->setUser((string) $hostingDeployment->directadmin_customer_username);

            $server = $this->subscriptionRepo->getServer($hostingDeployment);
            Assert::isInstanceOf($server, Server::class);

            $directAdminData = $this->directAdmin->useServer($server)->call($daUserDataCommand);

            if (! array_key_exists($domain, $directAdminData->getDomainSettings())) {
                continue;
            }

            return $hostingDeployment;
        }

        return null;
    }

    /**
     * Get the URL to log into the hosting provider for SSO.
     *
     * @throws ServerNotFoundException
     */
    public function getServerSsoUrl(int $serverId): string
    {
        $server = Server::find($serverId);

        if (! $server instanceof Server) {
            throw new ServerNotFoundException(
                sprintf(
                    'Server not found with id: %s',
                    $serverId
                )
            );
        }

        $this->directAdmin->useServer($server);

        return $this->directAdmin->getSSO($server->getUsername());
    }

    /**
     * @throws ServerNotFoundException
     * @throws GuzzleException
     */
    public function getUserStats(Parameters $parameters): ?UserStatistics
    {
        $cmd = new ShowUserStats();
        $cmd->setUser($parameters->getUsername());
        $emailCmd = new GetEmail($parameters->getDomain());
        $hostingDeployment = HostingDeployment::query()
            ->where('directadmin_customer_username', $parameters->getUsername())
            ->firstOrFail();
        $server = $this->subscriptionRepo->getServer($hostingDeployment);

        if (! $server instanceof Server) {
            throw new ServerNotFoundException(
                sprintf(
                    'Server not found on hostingsubscription with id: %s',
                    $hostingDeployment->id,
                )
            );
        }

        try {
            $result = $this->directAdmin->useServer($server)->call($cmd);
        } catch (DirectAdminCommandException $exception) {
            $this->logger->error(
                self::class . '::getUserStats - Cant retrieve the user usage statistics.',
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                ]
            );
            return null;
        }

        if (! $result->hasSucceeded()) {
            return null;
        }

        $userStats = $cmd->getStats();

        $intOrNull = function (string $key) use ($userStats): int|null {
            if (! array_key_exists($key, $userStats)) {
                return null;
            }

            if (! is_string($userStats[$key]) || ! is_numeric($userStats[$key])) {
                return null;
            }

            return (int) $userStats[$key];
        };

        $stats = new UserStatistics(
            activeDomains: $intOrNull('vdomains'),
            subdomains: $intOrNull('nsubdomains'),
            diskSpaceInMb: $intOrNull('quota') ?? 0,
            mailDiskSpaceInMb: null,
            mailBoxes: $intOrNull('nemails'),
            mailLists: $intOrNull('nemailml'),
            mailAutoResponders: $intOrNull('nemailr'),
            redirects: null,
            databases: $intOrNull('mysql'),
            traffic: $intOrNull('bandwidth') ?? 0
        );

        try {
            $emailResult = $this->directAdmin->useServer($server)->loginAs($parameters->getUsername())->call($emailCmd);
        } catch (DirectAdminCommandException $exception) {
            $this->logger->error(
                self::class . '::getUserStats - Cant retrieve the user email usage statistics.',
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                ]
            );
            return $stats;
        }

        if (! $emailResult->hasSucceeded()) {
            return $stats;
        }

        return new UserStatistics(
            activeDomains: $stats->activeDomains,
            subdomains: $stats->subdomains,
            diskSpaceInMb: $stats->diskSpaceInMb,
            mailDiskSpaceInMb: $emailResult->getStorage(),
            mailBoxes: $stats->mailBoxes,
            mailLists: $stats->mailLists,
            mailAutoResponders: $stats->mailAutoResponders,
            redirects: $stats->redirects,
            databases: $stats->databases,
            traffic: $stats->traffic,
        );
    }

    /**
     * Install an SSL certificate.
     *
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function installCertificate(string $subscriptionUuid, array $data): string
    {
        $hostingDeployment = HostingDeployment::where('subscription_uuid', $subscriptionUuid)->firstOrFail();
        $parameters = CertificateInstallParameters::create($data);

        try {
            $server = $this->getServer($hostingDeployment);

            $daUsername = $hostingDeployment->directadmin_customer_username ?? '';

            $userData = $this->getUserData($daUsername, $server);

            // TODO fix this in the DA package, see https://yh-jira.atlassian.net/browse/WATER-4565
            // The setting "login_keys" is not retrieved from DA and is overwritten when not
            // specified in the update calls. This is a hotfix to keep SSO working for now
            /** @var array<string, int|string> $userSettings */
            $userSettings = $userData->getUserSettings() + ['login_keys' => 'ON'];
            /** @var array<string, int|string> $domainSettings */
            $domainSettings = $userData->getDomainSettings() + ['login_keys' => 'ON'];

            $responseEnableSslForUser = $this->enableSslForUser($daUsername, $server, $userSettings);

            $this->logger->info(
                self::class . '::enableSslForUser - Enable Ssl for user.',
                [
                    LoggingContextKeys::RESPONSE_DATA => $responseEnableSslForUser->getResponseBody(),
                    LoggingContextKeys::META => [
                        'succeeded' => $responseEnableSslForUser->hasSucceeded(),
                    ],
                ]
            );

            $certificateDomain = $data['domain'];

            if (! array_key_exists($certificateDomain, $domainSettings)) {
                $this->logger->warning(
                    'Skipping certificate installation because exact domain not found on hosting server.',
                    [
                        LoggingContextKeys::DOMAIN_NAME => $certificateDomain,
                        LoggingContextKeys::SERVER_ID => $server->id,
                        LoggingContextKeys::SERVER_TYPE => $server->type->value,
                        LoggingContextKeys::META => [
                            'domain_settings' => $domainSettings,
                            'directadmin_username' => $daUsername,
                        ],
                    ]
                );

                return 'error';
            }

            $responseEnableSslForDomain = $this->enableSslForDomain(
                $certificateDomain,
                $server,
                $daUsername,
                $domainSettings
            );

            $this->logger->info(
                self::class . '::enableSslForDomain - Enable Ssl for domain.',
                [
                    LoggingContextKeys::RESPONSE_DATA => $responseEnableSslForDomain->getResponseBody(),
                    LoggingContextKeys::META => [
                        'succeeded' => $responseEnableSslForDomain->hasSucceeded(),
                    ],
                ]
            );

            $disableLetsEncryptAutoRenewCommand = new DisableLetsEncryptAutoRenew();
            $disableLetsEncryptAutoRenewCommand->setDomain($parameters->getDomain());
            $responseDisableLetsEncryptAutoRenew = $this->directAdmin->sslCerificate(
                $disableLetsEncryptAutoRenewCommand,
                $daUsername,
                $server
            );

            $this->logger->info(
                self::class . '::disableLetsEncryptAutoRenew - Disable Lets Encrypt for domain.',
                [
                    LoggingContextKeys::RESPONSE_DATA => $responseDisableLetsEncryptAutoRenew->getResponseBody(),
                    LoggingContextKeys::META => [
                        'succeeded' => $responseDisableLetsEncryptAutoRenew->hasSucceeded(),
                    ],
                ]
            );

            $sslCommand = new UploadSsl()
                ->setDomain($parameters->getDomain())
                ->setCert($parameters->getCert())
                ->setKey($parameters->getPvt());

            $responseUploadSsl = $this->directAdmin->sslCerificate(
                $sslCommand,
                $daUsername,
                $server,
            );
            assert($responseUploadSsl instanceof UploadSsl);

            $this->logger->info(
                self::class . '::installCertificate - Uploaded certificate to DirectAdmin.',
                [
                    LoggingContextKeys::RESPONSE_DATA => $responseUploadSsl->getResponseBody(),
                    LoggingContextKeys::META => [
                        'succeeded' => $responseUploadSsl->hasSucceeded(),
                    ],
                ]
            );

            $uploadCaCommand = new UploadCaCrt()
                ->setCaCert($parameters->getCa())
                ->setDomain($parameters->getDomain());

            $responseUploadCa = $this->directAdmin->sslCerificate(
                $uploadCaCommand,
                $daUsername,
                $server,
            );
            assert($responseUploadCa instanceof UploadCaCrt || $responseUploadCa instanceof UploadSsl);

            $this->logger->info(
                self::class . '::installCertificate - Uploaded root CA certificate to DirectAdmin.',
                [
                    LoggingContextKeys::RESPONSE_DATA => $responseUploadCa->getResponseBody(),
                    LoggingContextKeys::META => [
                        'succeeded' => $responseUploadCa->hasSucceeded(),
                    ],
                ]
            );

            $status = $responseDisableLetsEncryptAutoRenew->hasSucceeded()
                && $responseUploadSsl->hasSucceeded()
                && $responseUploadCa->hasSucceeded();
        } catch (Exception $exception) {
            $this->logger->error(
                self::class . '::installCertificate',
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                ]
            );

            return 'error';
        }

        if (! $status) {
            return "Failed to install an SSL certificate for domain {$parameters->getDomain()}";
        }

        return $this->selectCertificate($server, $parameters);
    }

    public function createCustomer(Parameters $parameters): DirectAdminCommand
    {
        // If you cannot find the given user already, create him/her
        // currently you can only retrieve all users, maybe we can call this at the beginning of the create ?
        $this->logger->info(self::class . '::create - Create new customer', [
            LoggingContextKeys::REQUEST_DATA => (string) json_encode($parameters->toArray()),
        ]);

        $parameters->setPackage(
            $this->resolvePackage($parameters->getSpecs())
        );

        $specs = $parameters->toDirectAdminUserSpecs();

        $logSpecs = $specs;
        $logSpecs['passwd'] = '********';

        $this->logger->info(
            'DirectAdmin specs:',
            [
                LoggingContextKeys::META => [
                    'specs' => $logSpecs,
                ],
            ],
        );

        $server = Server::where(
            [
                'ipv4' => $parameters->getIpv4Address(),
                'type' => ServerType::DIRECTADMIN,
            ]
        )->first();

        $this->logger->info(
            'DirectAdmin server:',
            [
                LoggingContextKeys::SERVER_ID => $server->id ?? null,
            ],
        );

        $result = new Result();
        $result->setStatus('error');

        try {
            $response = $this->directAdmin->user($server)->create($specs);
        } catch (Exception $exception) {
            $this->logger->error(
                'Something went wrong when creating new DA user and/or package.',
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::META => [
                        'specs' => $logSpecs,
                        'server' => $server === null ? 'no-server' : $server->toArray(),
                    ],
                ],
            );

            throw $exception;
        }

        $this->logger->info(
            'DirectAdmin create response:',
            [
                LoggingContextKeys::RESPONSE_DATA => $response->getResponseBody(),
            ],
        );

        if ($response->hasSucceeded()) {
            $this->logger->info(self::class . '::create - New customer created successfully', [
                LoggingContextKeys::META => [
                    'result' => $specs['username'] . " successfully created under domain {$parameters->getDomain()}",
                ],
            ]);
            $result->setStatus('ok');
        }

        return $response;
    }

    public function getCustomerConfig(Parameters $parameters): array
    {
        $hostingDeployment = HostingDeployment::where('directadmin_customer_username', $parameters->getUsername())
            ->firstOrFail();
        $server = $this->subscriptionRepo->getServer($hostingDeployment);

        return $this->directAdmin->user($server)
            ->showUserConfig($parameters->getUsername());
    }

    public function getUserConfig(string $identifier, Server $server): array
    {
        return $this->directAdmin->user($server)
            ->showUserConfig($identifier);
    }

    /**
     * Please only use this function for migrations!!!
     *
     * @return array<mixed>
     */
    public function getUserConfigAsAdmin(string $identifier, Server $server): array
    {
        return $this->directAdmin->user($server)
            ->showUserConfigAsAdmin($identifier);
    }

    /**
     * @throws HostingException
     */
    public function getUserConfigAsDto(string $identifier, Server $server): SiteConfigInterface
    {
        $result = $this->getUserConfigAsAdmin($identifier, $server);

        if ($result === []) {
            throw new HostingException("Directadmin User $identifier could not be found on server with hostname: {$server->hostname}");
        }

        $serializer = DirectAdminSerializerFactory::getSerializer();

        return $serializer->denormalize($result, UserConfig::class);
    }

    /**
     * @throws DirectAdminFieldException
     */
    public function modifyCustomer(Parameters $parameters): bool
    {
        $hostingDeployment = HostingDeployment::where('directadmin_customer_username', $parameters->getUsername())
            ->firstOrFail();
        $server = $this->subscriptionRepo->getServer($hostingDeployment);
        $specs = [];
        $enableDns = $parameters->getEnableDns();
        $enableSsl = $parameters->getEnableSsl();
        $enableSsh = $parameters->getEnableSsh();
        $enableLoginKeys = $parameters->getEnableLoginKeys();

        if ($enableDns !== null && $enableDns !== '') {
            $specs['dnscontrol'] = $enableDns;
        }
        if ($enableSsl !== null && $enableSsl !== '') {
            $specs['ssl'] = $enableSsl;
        }
        if ($enableSsh !== null && $enableSsh !== '') {
            $specs['ssh'] = $enableSsh;
        }
        if ($enableLoginKeys !== null && $enableLoginKeys !== '') {
            $specs['login_keys'] = $enableLoginKeys;
        }

        /**
         * Explanation for the random looking unset of the package value from the baseconfig.
         *
         * We need to unset the package cause of how the directadmin api works.
         * To update the directadmin customer you need the action in the user data be `customize`.
         * Since we get the baseConfig from the directadmin user before updating the directadmin user itself
         * we get the package name in the config array.
         *
         * If the package is set in the userdata from the baseConfig, the directadmin command action gets overridden by `package`.
         * When the action is `package` the user does not get updated with the new values but all the values from the package.
         */
        $baseConfig = $this->directAdmin->user($server)->showUserConfig($parameters->getUsername());
        unset($baseConfig['package']);

        $response = $this->directAdmin->user($server)->update(
            $parameters->getUsername(),
            array_merge(
                $baseConfig,
                $specs
            )
        );

        return $response->hasSucceeded();
    }

    /**
     * @throws ServerNotFoundException
     * @throws ReflectionException
     * @throws GuzzleException
     * @throws MailValidationException
     * @throws MailValidationException
     *
     * @return array<string, string>
     *
     */
    public function resetPassword(Parameters $parameters, UuidInterface $customerUuid): array
    {
        $hostingDeployment = HostingDeployment::where('directadmin_customer_username', $parameters->getUsername())
            ->firstOrFail();
        $server = $this->subscriptionRepo->getServer($hostingDeployment);

        if (is_null($server)) {
            throw new ServerNotFoundException(
                sprintf(
                    'Server not found on hostingsubscription with id: %s',
                    $hostingDeployment->id,
                )
            );
        }

        $cmd = new ChangePassword();
        $cmd->setUsername($parameters->getUsername());
        $cmd->setPasswd($parameters->getPassword());

        $cmdFtp = new ChangeFtpPassword();
        $cmdFtp->setUsername($parameters->getUsername());
        $cmdFtp->setPasswd($parameters->getPassword());
        $cmdFtp->setDomain($parameters->getDomain());

        try {
            $result = $this->directAdmin->useServer($server)->call($cmd);
        } catch (DirectAdminCommandException $exception) {
            $this->logger->error(
                self::class . '::reset password - The password could not be reset.',
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                ]
            );
            return ['result' => 'Reset password - The password could not be reset.'];
        }

        try {
            $resultFtp = $this->directAdmin->useServer($server)->loginAs($parameters->getUsername())->call($cmdFtp);
        } catch (DirectAdminCommandException $exception) {
            $this->logger->error(
                self::class . '::reset password - The FTP password could not be reset.',
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                ]
            );
            return ['result' => 'Reset password - The FTP password could not be reset.'];
        }

        if (! $result->hasSucceeded()) {
            return ['result' => 'Reset password - The password could not be reset.'];
        }

        if (! $resultFtp->hasSucceeded()) {
            return ['result' => 'Reset password - The FTP password could not be reset.'];
        }

        $recipient = new Recipient($parameters->getContactPersonName(), $parameters->getEmailAddress(), $customerUuid);

        $this->mailer->send(
            [$recipient],
            new MailDirectAdminDetails(
                $parameters->getUsername(),
                $parameters->getPassword(),
                $parameters->getDomain(),
                $parameters->getIpv4Address(),
                $server->use_ssl,
                $server->port,
            )
        );

        return Arr::only($parameters->toArray(false), ['username', 'password', 'domain']);
    }

    /**
     * Terminate a subscription.
     * We do this by deleting the customer. The subscription will automatically be deleted as well.
     *
     * @throws ServerNotFoundException
     *
     * @return bool true if successful or false if unsuccessful
     */
    public function terminate(string $domain, string $subscriptionUuid): bool
    {
        /** @var HostingDeployment|null $hostingDeployment */
        $hostingDeployment = HostingDeployment::query()->where('subscription_uuid', $subscriptionUuid)->first();

        if (is_null($hostingDeployment)) {
            return true;
        }

        $server = $this->subscriptionRepo->getServer($hostingDeployment);
        if (is_null($server)) {
            throw new ServerNotFoundException(
                sprintf(
                    'Server not found on hostingsubscription with id: %s and base subscription UUID: %s',
                    $hostingDeployment->id,
                    $subscriptionUuid
                )
            );
        }

        $user = $hostingDeployment->directadmin_customer_username;
        assert(is_string($user));
        $command = new DeleteUsers()->addUser($user);
        $result = $this->directAdmin->useServer($server)->call($command);

        if ($result->hasSucceeded()) {
            $spamExpertsCluster = $hostingDeployment->spamExpertsCluster;
            $hostingDeployment->delete();
            $this->jobDispatcher->dispatch(new RemoveDomainFromSpamFilter($domain, $spamExpertsCluster));
        } else {
            $this->logger->error(
                self::class . '::delete user - the user could not be deleted.',
                [
                    LoggingContextKeys::META => [
                        'user' => $user,
                    ],
                ]
            );
            return false;
        }

        return true;
    }

    /**
     * Generates a unique customer username for Directadmin that fits the length constraint (10 characters).
     *
     * TODO:: Rework the function name to better represent its use.
     * TODO:: https://yh-jira.atlassian.net/browse/WATER-2432
     */
    public function generateUsername(): string
    {
        $username = $this->broker->generateUsername();

        //Directadmin requires an username of between 3 and 10 characters
        //This is also custom configurable: https://www.directadmin.com/features.php?id=189
        //
        // NOTE:: If the uniqueness of the name generation still fails at this point you can retry the deployment through the hosting
        // retry in admin.

        if (HostingDeployment::withTrashed()->where('directadmin_customer_username', $username)->exists()) {
            return $this->generateUsername();
        }

        return $username;
    }

    public function isUsingHostingServerAsNameserver(string|null $ipv4HostingServer, string|null $ipv6HostingServer, SiteConfigInterface $userConfig): bool
    {
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
            $nsRecords
        );

        return new Collection($nsIPAddresses)
            ->contains(
                fn ($nsIPAddress): bool => $nsIPAddress === $ipv4HostingServer || $nsIPAddress === $ipv6HostingServer
            );
    }

    /**
     * Select an SSL certificate. Note:: This is the same response as EAT gives
     * Since uploading a cert is a one off action this is enough.
     */
    public function selectCertificate(Server $server, CertificateInstallParameters $parameters): string
    {
        return 'ok';
    }

    /**
     * @throws GuzzleException
     */
    public function changeServicePlan(HostingDeployment $deployment, Product $oldProduct, Product $newProduct): Result
    {
        $this->logger->info(
            'Change service plan on directadmin',
            [
                LoggingContextKeys::PRODUCT_ID => $newProduct->id,
                LoggingContextKeys::SUBSCRIPTION_UUID => $deployment->subscription->uuid,
                LoggingContextKeys::META => [
                    'hosting_deployment_id' => $deployment->id,
                    'directadmin_customer_username' => $deployment->directadmin_customer_username,
                ],
            ]
        );

        $result = new Result();
        $server = $this->subscriptionRepo->getServer($deployment);
        $user = $deployment->directadmin_customer_username;

        if (is_null($server) || is_null($user)) {
            $result->setErrorMessage('Server and/or DirectAdmin username is null/empty.');
            $result->setStatus('error');

            $this->logger->error(
                'The upgrade or downgrade can\'t be performed.',
                [
                    LoggingContextKeys::RESPONSE_CODE => 422,
                    LoggingContextKeys::RESPONSE_DATA => $result->getErrorMessage(),
                ]
            );
            return $result;
        }

        $command = new ModifyUser()->setUser($user)->setPackage($newProduct->slug);

        try {
            $command = $this->directAdmin->useServer($server)->call($command);
            $result->setStatus($command->hasSucceeded() ? 'ok' : 'error');
        } catch (DirectAdminCommandException $exception) {
            $this->logger->error(
                'The upgrade or downgrade can\'t be performed.',
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                ]
            );
            $result->setErrorMessage($exception->__toString());
            $result->setStatus('error');
        } finally {
            $this->storeLastResult($deployment, $command->getResponseBody());
        }

        return $result;
    }

    /**
     * @return array<mixed, mixed>
     */
    public function getPackagesOnServer(Server $server): array
    {
        return $this->directAdmin->package($server)->all();
    }

    /**
     * @throws ReflectionException
     * @throws DirectAdminException
     * @throws GuzzleException
     *
     * @return array<mixed, mixed>
     */
    public function getPackageOnServer(Server $server, string $packageName): array
    {
        return $this->directAdmin->package($server)->get($packageName);
    }

    /**
     * @throws HostingException
     */
    public function getPackageOnServerAsDto(Server $server, string $packageName): HostingOfferingInterface
    {
        /** @var array<string, string> $packageArray */
        $packageArray = $this->getPackageOnServer($server, $packageName);

        if ($packageArray === []) {
            throw new HostingException(sprintf(
                'Directadmin Package %s could not be found on server with hostname: %s',
                $packageName,
                $server->hostname
            ));
        }

        $packageArray['package'] = $packageName;

        $serializer = DirectAdminSerializerFactory::getSerializer();

        return $serializer->denormalize($packageArray, DirectAdminUserPackage::class);
    }

    public function storeLastResult(HostingDeployment $hostingDeployment, ?string $result): void
    {
        $hostingDeployment->last_created_result = $result;
        $hostingDeployment->last_created_result_received = CarbonImmutable::now();
        $hostingDeployment->save();
    }

    public function createEmailForward(
        Server $server,
        string $domain,
        string $sourceEmailAddressUsername,
        string $destinationEmailAddresses
    ): string {
        throw new NotImplementedException();
    }

    public function setEmailCatchAll(
        Server $server,
        string $domain,
        string $destinationEmailAddresses
    ): string {
        throw new NotImplementedException();
    }

    /**
     * @throws ReflectionException
     * @throws DirectAdminCommandException
     * @throws GuzzleException
     */
    public function coupleDomainToExistingHosting(
        DomainDeployment $domainDeployment,
        HostingDeployment $hostingDeployment
    ): bool {
        $domainSubscription = $domainDeployment->subscription;
        $domain = $domainSubscription->domain;
        Assert::notNull($domain, 'Provided subscription has no domain');

        $this->logger->debug(
            sprintf('Coupling domain [%s] to existing Directadmin hosting [%s]', $domain, $hostingDeployment->directadmin_customer_username),
            [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::DIRECTADMIN,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING,
                LoggingContextKeys::META => [
                    'hosting_deployment_id' => $hostingDeployment->id,
                    'domain_deployment_id' => $domainDeployment->id,
                    'server' => $hostingDeployment->server?->hostname,
                    'server_id' => $hostingDeployment->server?->id,
                ],
            ]
        );

        $server = $this->subscriptionRepo->getServer($hostingDeployment);
        assert($server instanceof Server);
        $user = $hostingDeployment->directadmin_customer_username;
        assert(is_string($user));

        $directAdminCommand = new ModifyDomain()
            ->setAction('create')
            ->setDomain($domain);

        $resultDomain = $this->directAdmin
            ->useServer($server)
            ->loginAs($user)
            ->call($directAdminCommand);

        $this->setDnsForHosting($server, $domain);
        $this->jobDispatcher->dispatch(new AddDomainToSpamFilter($domain, null));

        return $resultDomain->hasSucceeded();
    }

    public function setDnsForHosting(Server $server, string $domain): void
    {
        $changes = $this->dnsZoneService->getHostingDnsRecords(
            $domain,
            $server->getIpv4(),
            $server->getIpv6()
        );

        $this->eventDispatcher->dispatch(new ReplaceParkingAndUpdateDns($domain, $changes));
    }

    public function resetDnsForSitebuilder(Server $server, Server $mailOnlyServer, string $domain): void
    {
        $ipv4Host = $server->getIpv4();
        $ipv6Host = $server->getIpv6();

        $ipv4HostMail = $mailOnlyServer->getIpv4();
        $ipv6HostMail = $mailOnlyServer->getIpv6();

        $changes = $this->dnsZoneService->getExternalHostingDnsRecords($domain, $ipv4Host, $ipv6Host, $ipv4HostMail, $ipv6HostMail);

        $this->eventDispatcher->dispatch(new ReplaceParkingAndUpdateDns($domain, $changes));
    }

    public function getSsoUrl(
        string $username,
        Server $server,
        string $ipAddress,
        bool $redirectToMail = false
    ): string {
        return $this->directAdminGetSsoUrlAction->execute(
            $server,
            $username
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    public function suspend(HostingDeployment $hostingDeployment): void
    {
        $server = $this->subscriptionRepo->getServer($hostingDeployment);

        if ($server === null) {
            throw new InvalidArgumentException(
                sprintf(
                    'Server is not set for subscription uuid: %s',
                    $hostingDeployment->subscription->uuid
                )
            );
        }

        $this->directAdminSuspendHostingAction->execute($server, $hostingDeployment);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function unsuspend(HostingDeployment $hostingDeployment): void
    {
        $server = $this->subscriptionRepo->getServer($hostingDeployment);

        if ($server === null) {
            throw new InvalidArgumentException(
                sprintf(
                    'Server is not set for subscription uuid: %s',
                    $hostingDeployment->subscription->uuid
                )
            );
        }

        $this->directAdminUnsuspendHostingAction->execute($server, $hostingDeployment);
    }

    public function getDefaultDomain(string $username, Server $server): string|null
    {
        return $this->getUserConfigAsDto($username, $server)
            ->getDomain();
    }

    public function serverIsValid(Server $server): bool
    {
        $api = $this->directAdmin->useServer($server);
        $showUsers = new ShowResellerIPs();

        try {
            $api->call($showUsers);
        } catch (DirectAdminException $exception) {
            $this->logger->notice(
                'Validation of hosting server failed with server: {server.connection}',
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::META => [
                        'server.connection' => (string) $api->getConnection(),
                    ],
                ]
            );

            return false;
        }

        return true;
    }

    public function getCustomerDomainsForDkim(HostingDeployment $hostingDeployment): array
    {
        $serverData = $this->getServerData($hostingDeployment);
        $showAllUserDomains = new ShowAllUserDomains($serverData['directadmin_customer_username']);

        $this->directAdmin
            ->useServer($serverData['server'])
            ->loginAs($serverData['directadmin_customer_username'])
            ->call($showAllUserDomains);

        $domains = [];
        foreach ($showAllUserDomains->getDomainData() as $domain) {
            Assert::string($domain);

            $domains[] = $domain;
        }

        return $domains;
    }

    public function isDkimEnabled(HostingDeployment $hostingDeployment, string $domain): bool
    {
        $serverData = $this->getServerData($hostingDeployment);
        $getStateOfDkim = new GetEmail($domain);

        $this->directAdmin
            ->useServer($serverData['server'])
            ->loginAs($serverData['directadmin_customer_username'])
            ->call($getStateOfDkim);

        return $getStateOfDkim->getDkimEnabled();
    }

    public function setDkim(HostingDeployment $hostingDeployment, string $domain, bool $enable): void
    {
        $serverData = $this->getServerData($hostingDeployment);
        $setDkim = new EnableDisableDKIM($domain, $enable);

        $this->directAdmin
            ->useServer($serverData['server'])
            ->loginAs($serverData['directadmin_customer_username'])
            ->call($setDkim);
    }

    public function getDkimRecord(HostingDeployment $hostingDeployment, string $domain): ?DnsRecord
    {
        $serverData = $this->getServerData($hostingDeployment);

        $getDkimRecord = new FetchDkimRecord($domain, $this->logger);
        $this->directAdmin
            ->useServer($serverData['server'])
            ->call($getDkimRecord);

        $dkimRecord = $getDkimRecord->getDkimRecord();
        if ($dkimRecord === null) {
            return null;
        }

        $host = $dkimRecord['name'];
        if (! str_contains($dkimRecord['name'], $domain)) {
            $host .= '.' . $domain;
        }

        return new DnsRecord($dkimRecord['type'], $host, $dkimRecord['value']);
    }

    /**
     * @throws HostingException
     */
    public function getDomainOccupation(
        HostingDeployment $hostingDeployment,
    ): DomainOccupation {
        $server = $this->subscriptionRepo->getServer($hostingDeployment);
        Assert::isInstanceOf($server, Server::class);
        Assert::stringNotEmpty($hostingDeployment->directadmin_customer_username);

        try {
            $userData = $this->getUserData($hostingDeployment->directadmin_customer_username, $server);
        } catch (DirectAdminException|GuzzleException $exception) {
            throw new HostingException(previous: $exception);
        }

        $userSettings = $userData->getUserSettings();
        $domainSettings = $userData->getDomainSettings();
        $domainsFromServer = array_keys($domainSettings);

        $domainsInUse = count($domainSettings);

        if (array_key_exists('vdomains', $userSettings)) {
            Assert::true(is_string($userSettings['vdomains']) || is_int($userSettings['vdomains']));
            $maxDomains = intval($userSettings['vdomains']);
            return new DomainOccupation(
                hostingSubscription: $hostingDeployment,
                domains: $domainsFromServer,
                domainsInUse: $domainsInUse,
                domainsAvailable: $maxDomains - $domainsInUse,
                maxDomains: $maxDomains
            );
        }

        if (array_key_exists('uvdomains', $userSettings) && $userSettings['uvdomains'] === 'ON') {
            return new DomainOccupation(
                hostingSubscription: $hostingDeployment,
                domains: $domainsFromServer,
                domainsInUse: $domainsInUse,
                domainsAvailable: self::UNLIMITED_DOMAIN_REPRESENTATION,
                maxDomains: self::UNLIMITED_DOMAIN_REPRESENTATION
            );
        }

        throw new HostingException(previous: new DirectAdminCommandException(sprintf(
            'Could not retrieve available domains from DirectAdmin(Server: %d, Username: %s) for HostingDeployment %d',
            $server->id,
            $hostingDeployment->directadmin_customer_username,
            $hostingDeployment->id
        )));
    }

    private function isValidDomain(string $domain): bool
    {
        $validator = Validator::make(['domain' => $domain], ['domain' => [$this->domainNameRule]]);
        return $validator->passes();
    }

    /**
     * Get the product name relating to the directadmin reseller defaults.
     *
     * @param array<mixed[]> $specs
     */
    private function resolvePackage(array $specs): string
    {
        $first = Arr::first($specs);

        if (! is_array($first)) {
            $first = [];
        }

        $productId = Arr::get($first, 'product_id');

        if ($productId !== null) {
            $product = Product::findOrFail($productId);
            assert($product instanceof Product);
            return $product->slug;
        }

        throw new RuntimeException(
            'Unable to fetch product_id from provided array in Directadmin service resolvePackage!'
        );
    }

    /**
     * @throws DirectAdminCommandException
     * @throws GuzzleException
     */
    private function getUserData(string $userName, DirectAdminServer $server): ShowUserStats
    {
        $command = new ShowUserStats()->setUser($userName);

        return $this->directAdmin->useServer($server)->call($command);
    }

    /**
     * @param array<string, string|int> $userData
     *
     * @throws GuzzleException
     * @throws DirectAdminCommandException
     */
    private function enableSslForUser(string $user, DirectAdminServer $server, array $userData): DirectAdminCommand
    {
        $command = (new ModifyUser());
        $command->setUserData($userData);
        $command->setUser($user);
        $command->setSsl('ON');

        return $this->directAdmin->useServer($server)->call($command);
    }

    /**
     * @param array<int|string, mixed|string[]> $domainData
     *
     * @throws ReflectionException
     * @throws DirectAdminCommandException
     * @throws GuzzleException
     */
    private function enableSslForDomain(
        string $domain,
        DirectAdminServer $server,
        string $userName,
        array $domainData
    ): DirectAdminCommand {
        $command = (new ModifyDomain());
        $command->setDomainData($domainData[$domain]);
        $command->setDomain($domain)->setAction('modify');
        $command->setSsl('ON');

        return $this->directAdmin
            ->useServer($server)
            ->loginAs($userName)
            ->call($command);
    }

    /**
     * @return array{server: DirectadminServer, directadmin_customer_username: string}
     */
    private function getServerData(HostingDeployment $hostingDeployment): array
    {
        if ($hostingDeployment->subscription->product->isMailOnlyServer()) {
            Assert::notNull($hostingDeployment->mailOnlyServer);
            Assert::notNull($hostingDeployment->directadmin_customer_username);

            return [
                'server' => $hostingDeployment->mailOnlyServer,
                'directadmin_customer_username' => $hostingDeployment->directadmin_customer_username,
            ];
        }

        Assert::notNull($hostingDeployment->server);
        Assert::notNull($hostingDeployment->directadmin_customer_username);

        return [
            'server' => $hostingDeployment->server,
            'directadmin_customer_username' => $hostingDeployment->directadmin_customer_username,
        ];
    }
}
