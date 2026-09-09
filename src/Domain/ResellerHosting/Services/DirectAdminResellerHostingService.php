<?php

declare(strict_types=1);

namespace Waterfront\Domain\ResellerHosting\Services;

use Carbon\CarbonImmutable;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\UuidInterface;
use ReflectionException;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Email\Dto\Recipient;
use Waterfront\Domain\Hosting\DirectAdmin\DirectAdminPassword;
use Waterfront\Domain\Hosting\DirectAdmin\Mailer\MailDirectAdminDetails;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\ResellerHosting\Exceptions\ResellerHostingException;
use Waterfront\Domain\ResellerHosting\Exceptions\ResellerHostingNameserverCoupleException;
use Waterfront\Domain\ResellerHosting\Exceptions\ResellerHostingSslCoupleException;
use Waterfront\Domain\ResellerHosting\Interfaces\ResellerHostingServiceInterface;
use Waterfront\Domain\ResellerHosting\Models\ResellerHostingDeployment;
use Waterfront\Domain\ResellerHosting\Parameters\AppResellerHostingDomainCoupleParameters;
use Waterfront\Domain\ResellerHosting\Parameters\ResellerHostingParameters;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Exceptions\ServerNotFoundException;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Ssl\Interfaces\Models\CertificateInstall\Parameters as CertificateParameters;
use Waterfront\Domain\Ssl\Services\CertificateService;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Configuration\ConfigurationInterface as Configuration;
use Waterfront\Infra\DirectAdminClient\BehavesAsDirectAdmin;
use Waterfront\Infra\DirectAdminClient\Commands\Domains\GetNameServers;
use Waterfront\Infra\DirectAdminClient\Commands\Domains\ModifyDomain;
use Waterfront\Infra\DirectAdminClient\Commands\Resellers\ShowResellerUsers;
use Waterfront\Infra\DirectAdminClient\Commands\Ssl\UploadSsl;
use Waterfront\Infra\DirectAdminClient\Commands\Users\ChangePassword;
use Waterfront\Infra\DirectAdminClient\Commands\Users\DeleteUsers;
use Waterfront\Infra\DirectAdminClient\Connection\DirectAdminServer;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminFieldException;
use Waterfront\Support\Enums\LoggingContextKeys;

class DirectAdminResellerHostingService implements ResellerHostingServiceInterface
{
    public function __construct(
        private readonly DomainService $domainService,
        private readonly CertificateService $certificateService,
        private readonly MailerInterface $mailer,
        private readonly BehavesAsDirectAdmin $directAdmin,
        private readonly Configuration $configuration,
        private readonly DirectAdminPassword $passwordGenerator
    ) {
    }

    /**
     * @return array<string>
     */
    public function list(Server $server): array
    {
        return $this->directAdmin->reseller($server)->all();
    }

    /**
     * @param array<int, array<string, mixed>> $specs
     *
     * @throws ResellerHostingException
     * @throws GuzzleException
     */
    public function create(
        string $contactPersonName,
        string $contactEmail,
        string $customerEmail,
        UuidInterface $customerUuid,
        string $subscriptionUuid,
        array $specs,
        int $providerId,
        ?Server $server = null
    ): string {
        $server ??= $this->findServer();

        Log::info(
            self::class . '::create - Create new reseller hosting',
            [
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscriptionUuid,
                LoggingContextKeys::META => [
                    'contact person' => $contactPersonName,
                    'contact email' => $contactEmail,
                    'customer email' => $customerEmail,
                    'specs' => $specs,
                ],
            ]
        );

        $parameters = $this->parameters($providerId, $customerEmail, $server, $specs);

        $createResellerResult = $this->createReseller($parameters, $server);

        $this->sendMail($contactPersonName, $customerEmail, $customerUuid, $parameters);

        $this->updateOrCreateResellerHostingDeployment(
            $subscriptionUuid,
            $server,
            $parameters,
            $specs,
            $createResellerResult->getStatus()
        );

        return $createResellerResult->getStatus();
    }

    public function findServer(): Server
    {
        return Server::query()
            ->where([
                'allow_new_websites' => true,
                'type' => ServerType::DIRECTADMIN,
            ])
            ->with('hostingDeployments')
            ->get()
            ->sortByDesc(fn (Server $server): int => $server->available_websites)->firstOrFail();
    }

    public function generateUsername(): string
    {
        do {
            $username = 'r' . random_int(11111, 99999);
        } while (
            ResellerHostingDeployment::withTrashed()
                ->where('directadmin_customer_username', $username)
                ->exists()
        );

        return $username;
    }

    public function generateDomain(string $username, string $fqdn): string
    {
        return sprintf('%s.%s', trim($username, '.'), trim($fqdn, '.'));
    }

    /**
     * @throws DirectAdminCommandException
     * @throws GuzzleException
     * @throws ReflectionException
     * @throws ResellerHostingException
     *
     * @return string[]
     */
    public function getSubAccounts(ResellerHostingDeployment $resellerHostingDeployment): array
    {
        $resellerUsername = $resellerHostingDeployment->directadmin_customer_username;

        if ($resellerUsername === null) {
            throw ResellerHostingException::noDirectAdminUserNameForReseller(
                $resellerHostingDeployment->subscription_uuid
            );
        }

        /** @var DirectAdminServer $server */
        $server = $resellerHostingDeployment->server;

        $command = new ShowResellerUsers();
        $command->setReseller($resellerUsername);

        /** @var ShowResellerUsers $result */
        $result = $this->directAdmin
            ->useServer($server)
            ->loginAs($resellerUsername)
            ->call($command);

        return array_merge([$resellerUsername], $result->getResellerUsersList());
    }

    /**
     * @throws Exception|GuzzleException
     */
    public function createReseller(ResellerHostingParameters $parameters, Server $server): Result
    {
        $result = new Result();
        $result->setStatus(Result::STATUS_ERROR);

        $response = $this->directAdmin->reseller($server)->create([
            'username' => $parameters->username,
            'passwd' => $parameters->password,
            'email' => $parameters->email,
            'domain' => $parameters->domain,
            'ip' => $parameters->ipv4Address ?? $parameters->ipv6Address,
            'package' => $parameters->packageName,
        ]);

        if ($response->hasSucceeded()) {
            Log::info(self::class . '::create - New reseller created successfully', [
                LoggingContextKeys::META => [
                    'result' => sprintf(
                        '%s successfully created under domain %s',
                        $parameters->username,
                        $parameters->domain
                    ),
                ],
            ]);

            $result->setStatus(Result::STATUS_OK);

            /**
             * DirectAdmin gives us an empty body in the response (with a 200 http)
             * so we return the parameters as an array to be saved later in the
             * 'last_result' field on the Subscription model.
             */
            $result->setResponseBody([
                'username' => $parameters->username,
                'email' => $parameters->email,
                'domain' => $parameters->domain,
                'ip' => $parameters->ipv4Address ?? $parameters->ipv6Address,
                'package' => $parameters->packageName,
            ]);
        }

        return $result;
    }

    /**
     *
     * @throws GuzzleException
     * @throws ServerNotFoundException
     * @throws DirectAdminCommandException
     * @throws ResellerHostingException
     */
    public function terminate(ResellerHostingDeployment $deployment): bool
    {
        $server = $deployment->server;

        $username = $deployment->directadmin_customer_username;

        if ($username === null) {
            throw ResellerHostingException::noDirectAdminUserNameForReseller(
                $deployment->subscription_uuid
            );
        }

        $command = new DeleteUsers()->addUser($username);
        $result = $this->directAdmin->useServer($server)->call($command);

        if (! $result->hasSucceeded()) {
            Log::error(
                self::class . '::delete user - the user could not be deleted.',
                [
                    LoggingContextKeys::META => [
                        'user' => $username,
                    ],
                ]
            );

            return false;
        }

        return true;
    }

    /**
     *
     * @throws GuzzleException
     * @throws ResellerHostingException
     *
     * @return array<string, string>
     *
     */
    public function resetPassword(ResellerHostingParameters $parameters, UuidInterface $customerUuid): array
    {
        $resellerHostingDeployment = ResellerHostingDeployment::query()
            ->where('directadmin_customer_username', $parameters->username)
            ->first();

        if (! $resellerHostingDeployment instanceof ResellerHostingDeployment) {
            throw ResellerHostingException::noDirectadminUsernameFound(
                $parameters->username,
                'resetPassword'
            );
        }

        $server = $resellerHostingDeployment->server;

        if ($server->type !== ServerType::DIRECTADMIN) {
            throw ResellerHostingException::noDirectadminCompatibleServerFound();
        }

        Log::info(self::class . '::resetPassword - Change Password', [
            LoggingContextKeys::META => [
                'username' => $parameters->username,
            ],
        ]);

        $cmd = new ChangePassword();
        $cmd->setUsername($parameters->username);
        $cmd->setPasswd($parameters->password);

        try {
            Log::info(self::class . '::resetPassword - Change Password', [
                LoggingContextKeys::META => [
                    'username' => $parameters->username,
                ],
            ]);

            $result = $this->directAdmin->useServer($server)->call($cmd);
        } catch (DirectAdminCommandException $error) {
            throw ResellerHostingException::directadminCommandFailed(
                'resetPassword - ChangePassword',
                $error->getCode(),
                $error
            );
        }

        if (! $result->hasSucceeded()) {
            throw new ResellerHostingException('Reset password - The password could not be reset.');
        }

        if ($parameters->contactPerson !== null) {
            $this->sendMail($parameters->contactPerson, $parameters->email, $customerUuid, $parameters);
        }

        return ['username' => $parameters->username, 'password' => $parameters->password];
    }

    /**
     * @throws DirectAdminCommandException
     * @throws GuzzleException
     * @throws ReflectionException
     * @throws ResellerHostingException
     * @throws ResellerHostingNameserverCoupleException
     * @throws ResellerHostingSslCoupleException
     */
    public function coupleExistingDomain(
        ResellerHostingDeployment $resellerHostingDeployment,
        Subscription $domainDeployment,
        AppResellerHostingDomainCoupleParameters $parameters,
    ): bool {
        $domain = $domainDeployment->domain;

        if ($domain === null) {
            return false;
        }

        $domainCommand = new ModifyDomain();
        $domainCommand->setAction('create');
        $domainCommand->setDomain($domain);

        if ($resellerHostingDeployment->server->type !== ServerType::DIRECTADMIN) {
            throw ResellerHostingException::noCompatibleServerFound(ServerType::DIRECTADMIN->value);
        }

        if ($resellerHostingDeployment->directadmin_customer_username === null) {
            throw ResellerHostingException::noDirectAdminUserNameForReseller(
                $resellerHostingDeployment->subscription_uuid
            );
        }

        $resultDomain = $this->directAdmin
            ->useServer($resellerHostingDeployment->server)
            ->loginAs($parameters->getUserName())
            ->call($domainCommand);

        if ($resultDomain->hasSucceeded() === false) {
            return false;
        }

        try {
            /**
             * @var DomainDeployment $domainParentSubscription
             */
            $domainParentSubscription = DomainDeployment::where('subscription_uuid', $domainDeployment->uuid)
                ->first();

            $resultSetNameServer = $this->setNameserversForDomain(
                $domainParentSubscription,
                $resellerHostingDeployment->server,
            );
        } catch (DirectAdminCommandException $exception) {
            throw  new ResellerHostingNameserverCoupleException($domain, $exception->getCode(), $exception);
        } catch (Exception $exception) {
            throw new ResellerHostingException($exception->getMessage(), $exception->getCode(), $exception);
        }

        if (! $resultSetNameServer) {
            throw new ResellerHostingNameserverCoupleException($domain);
        }

        $subscriptionSsl = Subscription::where('administrative_status', '<>', AdministrativeStatus::ARCHIVED->value)
            ->where('domain', $domain)
            ->whereHas('product.productGroup', function (Builder $q): void {
                $q->where('slug', ProductGroupType::SSL);
            })
            ->first();

        if ($subscriptionSsl !== null) {
            $this->installCertificate(
                $resellerHostingDeployment->server,
                $domain,
                $parameters->getUserName()
            );
        }
        return true;
    }

    /**
     * @throws GuzzleException
     * @throws DirectAdminCommandException
     * @throws Exception
     */
    public function setNameserversForDomain(DomainDeployment $deployment, Server $server): bool
    {
        $nameserversCommand = new GetNameServers();
        /** @var GetNameServers $nameserver */
        $nameserver = $this->directAdmin->useServer($server)->call($nameserversCommand);
        $ns1 = $nameserver->getFormValues()['NS1'];
        $ns2 = $nameserver->getFormValues()['NS2'];
        $nameservers = [
            new Nameserver(is_array($ns1) ? reset($ns1) : $ns1),
            new Nameserver((is_array($ns2) ? reset($ns2) : $ns2)),
        ];

        return $this->domainService->setCustomNameservers(
            $deployment,
            $nameservers,
        );
    }

    /**
     * @throws ResellerHostingNameserverCoupleException
     * @throws ResellerHostingSslCoupleException
     */
    public function installCertificate(Server $server, string $domain, string $directAdminUserName): bool
    {
        try {
            $parameters = $this->getCertificateParameters($domain);

            $sslCommand = new UploadSsl()
                ->setDomain($parameters->getDomain())
                ->setCert($parameters->getCert())
                ->setKey($parameters->getPvt());

            $response = $this->directAdmin->sslCerificate(
                $sslCommand,
                $directAdminUserName,
                $server,
            );

            $status = $response->hasSucceeded();
        } catch (Exception $exception) {
            Log::error(
                self::class . '::installCertificate',
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                ]
            );
            throw ResellerHostingSslCoupleException::installCertificateError($domain, $exception->getCode(), $exception);
        }

        if (! $status) {
            Log::error(
                self::class . '::installCertificate',
                [
                    LoggingContextKeys::META => [
                        'message' => "Failed to install an SSL certificate for domain {$parameters->getDomain()}",
                    ],
                ]
            );
            throw new ResellerHostingNameserverCoupleException($domain);
        }
        return true;
    }

    /**
     * @return array<string,string>
     */
    public function getUserStats(ResellerHostingParameters $parameters): array
    {
        return ['' => ''];
    }

    /**
     * @throws DirectAdminFieldException
     */
    public function modifyCustomerForResellerMigrations(string $username): bool
    {
        $resellerHostingDeployment = ResellerHostingDeployment::where(
            'directadmin_customer_username',
            $username
        )->firstOrFail();

        $server = $resellerHostingDeployment->server;

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
        $baseConfig = $this->directAdmin->reseller($server)->showResellerConfig($username);
        unset($baseConfig['package']);

        /** @var array<string, string> $newPayload */
        $newPayload = array_merge(
            $baseConfig,
            [
                // Dev note: These fields are only added if there is a "set" function in the ModifyReseller class
                'dnscontrol' => 'OFF',
                'login_keys' => 'ON',
            ]
        );

        Log::debug(
            'Updating reseller config',
            [
                LoggingContextKeys::SERVER_ID => $server?->id,
                LoggingContextKeys::SERVER_HOSTNAME => $server?->hostname,
                LoggingContextKeys::SERVER_TYPE => $server?->type->value,
                LoggingContextKeys::META => [
                    'username' => $username,
                    'new_reseller_payload' => $newPayload,
                ],
            ]
        );

        $response = $this->directAdmin->reseller($server)->update(
            $username,
            $newPayload,
        );

        Log::debug(
            'Updating reseller config response',
            [
                LoggingContextKeys::SERVER_ID => $server?->id,
                LoggingContextKeys::SERVER_HOSTNAME => $server?->hostname,
                LoggingContextKeys::SERVER_TYPE => $server?->type->value,
                LoggingContextKeys::META => [
                    'username' => $username,
                    'response_body' => $response->getResponseBody(),
                ],
            ]
        );

        return $response->hasSucceeded();
    }

    /**
     * Get a filled parameter instance.
     *
     * @param array<int, array<string|mixed>> $specs
     *
     * @throws ResellerHostingException
     */
    private function parameters(
        int $providerId,
        string $customerEmail,
        Server $server,
        array $specs
    ): ResellerHostingParameters {
        $username = $this->generateUsername();

        $domain = $this->generateDomain(
            $username,
            $this->configuration->getAsString('hostingservice.directadmin.create_placeholder_domain')
        );

        $specsArray = (array) Arr::first($specs);
        $productId = is_numeric(Arr::get($specsArray, 'product_id'))
            ? (int) Arr::get($specsArray, 'product_id')
            : 0;

        Log::info(
            self::class . '::parameters',
            [
                LoggingContextKeys::META => [
                    'message' => "The following specifications are configured for the product with id {$productId}",
                    'specs' => $specs,
                ],
            ]
        );

        $product = Product::findOrFail($productId);

        return new ResellerHostingParameters(
            contactPerson: null,
            username: $username,
            password: $this->passwordGenerator->generatePassword(12),
            email: $customerEmail,
            domain: $domain,
            ipv4Address: $server->getIpv4(),
            ipv6Address: $server->getIpv6(),
            packageName: $product->slug,
            resellerHostingId: null,
            providerId: $providerId
        );
    }

    /**
     * Send the customer an email with details about the new reseller hosting account.
     */
    private function sendMail(
        string $contactPersonName,
        string $contactEmail,
        UuidInterface $uuid,
        ResellerHostingParameters $parameters
    ): void {
        $resellerHostingDeployment = ResellerHostingDeployment::find($parameters->resellerHostingId);
        $port = null;
        $use_ssl = false;
        if (! is_null($resellerHostingDeployment)) {
            $port = $resellerHostingDeployment->server->port;
            $use_ssl = $resellerHostingDeployment->server->use_ssl;
        }

        $recipient = new Recipient($contactPersonName, $contactEmail, $uuid);

        $this->mailer->send(
            [$recipient],
            new MailDirectAdminDetails(
                $parameters->username,
                $parameters->password,
                $parameters->domain ?? '',
                $parameters->ipv4Address ?? '',
                $use_ssl,
                $port,
            )
        );
    }

    /**
     * @param array<int, array<string, mixed>> $specs
     */
    private function updateOrCreateResellerHostingDeployment(
        string $subscriptionUuid,
        Server $server,
        ResellerHostingParameters $parameters,
        array $specs,
        ?string $commandResult = null
    ): void {
        $resellerHostingDeployment = ResellerHostingDeployment::query()
            ->where('subscription_uuid', $subscriptionUuid)
            ->first();

        if ($resellerHostingDeployment === null) {
            $resellerHostingDeployment = new ResellerHostingDeployment();
            $resellerHostingDeployment->subscription_uuid = $subscriptionUuid;
            $resellerHostingDeployment->provider_id = $parameters->providerId;
            $resellerHostingDeployment->directadmin_customer_username = $parameters->username;
            $resellerHostingDeployment->server_id = $server->id;
            $resellerHostingDeployment->save();
        }

        $specs = new Collection($specs)->pluck('value', 'name');

        $resellerHostingDeployment->update([
            'disk_space' => $specs->get('resellerhosting.limits.disk_space'),
            'max_users' => $specs->get('resellerhosting.limits.max_users'),
            'max_domains' => $specs->get('resellerhosting.limits.max_domains'),
            'max_email_addresses' => $specs->get('resellerhosting.limits.max_email_addresses'),
            'max_traffic' => $specs->get('resellerhosting.limits.max_traffic'),
            'max_databases' => $specs->get('resellerhosting.limits.max_databases'),
            'last_created_result' => $commandResult,
            'last_created_result_received' => CarbonImmutable::now(),
        ]);

        $resellerHostingDeployment->subscription->update([
            'domain' => $parameters->domain,
        ]);
    }

    private function getCertificateParameters(string $domain): CertificateParameters
    {
        return $this->certificateService->prepareCertificateInstallParameters($domain);
    }
}
