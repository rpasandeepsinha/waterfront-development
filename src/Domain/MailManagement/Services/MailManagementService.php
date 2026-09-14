<?php

declare(strict_types=1);

namespace Waterfront\Domain\MailManagement\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use UnexpectedValueException;
use Waterfront\Apps\API\Waterfront\Policies\HostingDeploymentPolicy;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Email\Jobs\AddDomainToSpamFilter;
use Waterfront\Domain\Email\Jobs\RemoveDomainFromSpamFilter;
use Waterfront\Domain\Hosting\DirectAdmin\Exceptions\EmailForwardException;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Domain\Hosting\Mailers\EmailAccountCreatedMail;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Services\HostingDeploymentService;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\MailManagement\Factories\MailOnlyServiceFactory;
use Waterfront\Domain\MailManagement\Interfaces\EmailForwardInterface;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Repositories\ProductSpecRepository;
use Waterfront\Domain\Providers\Enums\ProviderSettingKey;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Providers\ProviderRepository;
use Waterfront\Domain\Servers\Exceptions\ServerNotFoundException;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\SpamExpertsClient\SpamExpertsClient;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class MailManagementService
{
    public function __construct(
        private readonly SpamExpertsClient $spamExpertsClient,
        private readonly MailOnlyServiceFactory $mailOnlyServiceFactory,
        private readonly Dispatcher $jobDispatcher,
        private readonly ConfigurationInterface $configuration,
        private readonly ProviderRepository $providerRepository,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly ProductSpecRepository $productSpecRepository,
        private readonly HostingDeploymentPolicy $deploymentPolicy,
        private readonly MailManagementServerService $serverService,
        private readonly HostingDeploymentService $hostingDeploymentService,
    ) {
    }

    /**
     * @return mixed[]
     */
    public function configuration(): array
    {
        return $this->configuration->getAsArray('mailonly.connection_details');
    }

    /**
     * @throws ModelNotFoundException
     * @throws InvalidArgumentException
     * @throws RuntimeException
     *
     * @return array<string, mixed>
     */
    public function users(Subscription $subscription): array
    {
        $domain = $subscription->domain;

        if ($domain === null) {
            throw new InvalidArgumentException(sprintf('No domain found for subscription [%s].', $subscription->uuid));
        }

        $hostingDeployment = $this->getHostingDeployment($subscription);

        $provider = $this->getMailHostingProviderSlug($hostingDeployment);
        $domainUsername = $this->mailOnlyServiceFactory->driver($provider)->getUsername($hostingDeployment);

        $server = $this->serverService->getServer($hostingDeployment);

        return $this->mailOnlyServiceFactory->driver($provider)->listDomain(
            $server->hostname,
            $domain,
            $domainUsername,
        );
    }

    /**
     * @return array<string, mixed>.
     */
    public function getEmailUsersRaw(
        ProviderSlug $providerSlug,
        Server $server,
        string $username,
        string $domain,
    ): array {
        return $this->mailOnlyServiceFactory->driver($providerSlug)->listDomainFromServer($server, $domain, $username);
    }

    public function domainUserExists(Subscription $subscription, string $username): bool
    {
        $users = Arr::get($this->users($subscription), 'users', []);
        assert(is_array($users));

        return new Collection($users)
            ->filter(fn (string $user): bool => $user === $username)
            ->isNotEmpty();
    }

    public function createDomain(Subscription $subscription, string $email): bool
    {
        $domain = $subscription->domain;

        if ($domain === null) {
            throw new RuntimeException('MailOnlyService expects domain to not be null for subscription: '
            . $subscription->uuid);
        }

        if ($subscription->hostingDeployment()->doesntExist()) {
            $subscription->hostingDeployment()->save(new HostingDeployment());
        }

        $hostingDeployment = $subscription->fresh()?->hostingDeployment;
        Assert::notNull($hostingDeployment);

        $this->deploymentPolicy->assertCanManageMailAccounts($hostingDeployment);

        $this->logger->info(
            self::class . '::createDomain - Creating mail only domain',
            [
                LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::META => [
                    'fullDomain' => $domain,
                    'email' => $email,
                ],
            ],
        );

        $result = $this->mailOnlyServiceFactory->driver()->createDomain($domain, $email);

        $usesMailOnlyServer = $this->productSpecRepository->booleanSpecificationIsTrue(
            $subscription->product,
            ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER,
        );

        $usesMailOnlyServer
            ? $hostingDeployment->update([
                'mail_only_provider_id' =>
                    $this->providerRepository->getEnabledDefaultByType(ProviderType::MAILONLY)->id,
                'mail_only_server_id' => $result->getServerId(),
            ])
            : $hostingDeployment->update([
                'server_id' => $result->getServerId(),
                'provider_id' => $this->providerRepository->getEnabledDefaultByType(ProviderType::HOSTING)->id,
            ]);

        /** @var Subscription $subscription */
        $subscription = $subscription->fresh();

        /** @var HostingDeployment $hostingDeployment */
        $hostingDeployment = $subscription->hostingDeployment;

        $usesMailOnlyServer
            ? ($hostingDeployment->provider_id = null)
            // This gets set automatically, so we revert it in case of mail-only.
            : ($hostingDeployment->mail_only_provider_id = null);

        if ($result->getStatus() === Result::STATUS_OK && ! is_null($result->getResourceId())) {
            $this->hostingDeploymentService->setMailUsername($hostingDeployment, $result->getResourceId());
            $subscription->technical_status = TechnicalStatus::OK->value;

            $this->jobDispatcher->dispatch(new AddDomainToSpamFilter($domain, null));
        } else {
            $subscription->technical_status = TechnicalStatus::FAILED->value;
            $hostingDeployment->last_created_result = $result->getResponseResult();
            $hostingDeployment->last_created_result_received = CarbonImmutable::now();
        }

        $hostingDeployment->save();
        $subscription->save();

        return $result->getStatus() === Result::STATUS_OK;
    }

    /**
     * @throws RuntimeException
     */
    public function createUser(Subscription $subscription, string $mailUser, string $password): bool
    {
        $domain = $subscription->domain;

        if (is_null($domain)) {
            throw new InvalidArgumentException(
                "Invalid subscription provided with uuid {$subscription->uuid} Unable to resolve domain name.",
            );
        }

        $hostingDeployment = $this->getHostingDeployment($subscription);
        $this->deploymentPolicy->assertCanManageMailAccounts($hostingDeployment);

        $driver = $this->getMailHostingProviderSlug($hostingDeployment);
        $domainUsername = $this->mailOnlyServiceFactory->driver($driver)->getUsername($hostingDeployment);

        $server = $this->serverService->getServer($hostingDeployment);
        $hostname = $server->hostname;

        $providerType = $subscription->product->isMailOnlyServer() ? ProviderType::MAILONLY : ProviderType::HOSTING;

        $limit = $this->getLimit($driver, $providerType);
        $quota = $this->getQuota($driver, $providerType);

        $this->logger->info(
            'Mail createUser - Creating mail user',
            [
                LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                LoggingContextKeys::PRODUCT_SLUG => $subscription->product->slug,
                LoggingContextKeys::PRODUCT_ID => $subscription->product->id,
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::META => [
                    'hostname' => $hostname,
                    'domain' => $domain,
                    'domainUsername' => $domainUsername,
                    'mailUser' => $mailUser,
                    'limit' => $limit,
                    'quota' => $quota,
                    'driver' => $driver->value,
                ],
            ],
        );

        $result = $this->mailOnlyServiceFactory->driver($driver)->createUser(
            $hostname,
            $domain,
            $domainUsername,
            $mailUser,
            $password,
            $limit,
            $quota,
        );

        $emailAddress = $domainUsername . '@' . $domain;
        $this->sendUserCreatedConfirmationMail($subscription->customer, $emailAddress);

        return $result->getStatus() === Result::STATUS_OK;
    }

    /**
     * @throws RuntimeException
     */
    public function resetPassword(Subscription $subscription, string $mailUser, string $password): bool
    {
        $domain = $subscription->domain;

        if ($domain === null) {
            throw new RuntimeException('MailOnlyService expects domain to not be null for subscription: '
            . $subscription->uuid);
        }

        $hostingDeployment = $this->getHostingDeployment($subscription);
        $this->deploymentPolicy->assertCanManageMailAccounts($hostingDeployment);

        $driver = $this->getMailHostingProviderSlug($hostingDeployment);
        $domainUsername = $this->mailOnlyServiceFactory->driver($driver)->getUsername($hostingDeployment);

        $server = $this->serverService->getServer($hostingDeployment);
        $hostname = $server->hostname;

        $providerType = $subscription->product->isMailOnlyServer() ? ProviderType::MAILONLY : ProviderType::HOSTING;

        $quota = $this->getQuota($driver, $providerType);

        $this->logger->info(
            'Mail account - Resetting password for mail only user',
            [
                LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                LoggingContextKeys::PRODUCT_SLUG => $subscription->product->slug,
                LoggingContextKeys::PRODUCT_ID => $subscription->product->id,
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::META => [
                    'hostname' => $hostname,
                    'domainUsername' => $domainUsername,
                    'mailUser' => $mailUser,
                    'quota' => $quota,
                ],
            ],
        );

        $result = $this->mailOnlyServiceFactory->driver($driver)->resetPassword(
            $hostname,
            $domain,
            $domainUsername,
            $mailUser,
            $password,
            $quota,
        );

        return $result->getStatus() === Result::STATUS_OK;
    }

    public function spamExpertsSso(Subscription $subscription): string
    {
        $domain = $subscription->domain;

        if (is_null($domain)) {
            throw new InvalidArgumentException(
                "Invalid subscription provided with uuid {$subscription->uuid} Unable to resolve domain name.",
            );
        }

        $spamExpertsCluster = $subscription->hostingDeployment?->spamExpertsCluster;

        $token = $this->spamExpertsClient->generateSsoToken($domain, $spamExpertsCluster);

        $spamexpertsEndpoint = $spamExpertsCluster !== null
            ? $spamExpertsCluster->hostname
            : $this->configuration->getAsString('spamexpertsclient.connection.api_url');

        if ($spamexpertsEndpoint === '') {
            throw new UnexpectedValueException(
                'Spamexperts base url not set in the env file!',
            );
        }

        return $spamexpertsEndpoint . '/?authticket=' . $token;
    }

    public function deleteDomain(Subscription $subscription): bool
    {
        $domain = $subscription->domain;

        if (is_null($domain)) {
            throw new InvalidArgumentException(
                "Invalid subscription
                provided with uuid {$subscription->uuid} Unable to resolve domain name.",
            );
        }

        $hostingDeployment = $this->getHostingDeployment($subscription);

        $this->deploymentPolicy->assertCanManageMailAccounts($hostingDeployment);

        $domainUsername = $this->mailOnlyServiceFactory
            ->driver($this->getMailHostingProviderSlug($hostingDeployment))
            ->getUsername($hostingDeployment);

        $server = $this->serverService->getServer($hostingDeployment);
        $hostname = $server->hostname;

        $this->logger->info(
            self::class . '::deleteDomain - Deleting mail domain',
            [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::META => [
                    'hostname' => $hostname,
                    'domainUsername' => $domainUsername,
                ],
            ],
        );

        $result = $this->mailOnlyServiceFactory->driver($this->getMailHostingProviderSlug(
            $hostingDeployment,
        ))->deleteDomain($hostname, $domain, $domainUsername);

        if ($result->getStatus() === Result::STATUS_OK) {
            $spamExpertsCluster = $subscription->hostingDeployment?->spamExpertsCluster;
            $this->jobDispatcher->dispatch(new RemoveDomainFromSpamFilter($subscription->domain, $spamExpertsCluster));
        }

        return $result->getStatus() === Result::STATUS_OK;
    }

    public function deleteUser(Subscription $subscription, string $mailUser): bool
    {
        $domain = $subscription->domain;

        if (is_null($domain)) {
            throw new InvalidArgumentException(
                "Invalid subscription provided with uuid {$subscription->uuid} Unable to resolve domain name.",
            );
        }

        $hostingDeployment = $this->getHostingDeployment($subscription);
        $this->deploymentPolicy->assertCanManageMailAccounts($hostingDeployment);

        $domainUsername = $this->mailOnlyServiceFactory
            ->driver($this->getMailHostingProviderSlug($hostingDeployment))
            ->getUsername($hostingDeployment);

        $server = $this->serverService->getServer($hostingDeployment);
        $hostname = $server->hostname;

        $this->logger->info(
            'Deleting mail user account',
            [
                LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                LoggingContextKeys::PRODUCT_SLUG => $subscription->product->slug,
                LoggingContextKeys::PRODUCT_ID => $subscription->product->id,
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::META => [
                    'hostname' => $hostname,
                    'domainUsername' => $domainUsername,
                    'mailUser' => $mailUser,
                ],
            ],
        );

        $result = $this->mailOnlyServiceFactory->driver($this->getMailHostingProviderSlug(
            $hostingDeployment,
        ))->deleteUser($hostname, $domain, $domainUsername, $mailUser);

        return $result->getStatus() === Result::STATUS_OK;
    }

    /**
     * @throws ModelNotFoundException
     * @throws ServerNotFoundException
     * @throws InvalidArgumentException
     */
    public function terminate(Subscription $subscription): void
    {
        $this->deleteDomain($subscription);
    }

    /**
     * @param array<int, string> $destinationEmailAddresses
     *
     * @throws EmailForwardException
     */
    public function createEmailForward(
        HostingDeployment $hostingDeployment,
        string $sourceEmailAddressUsername,
        array $destinationEmailAddresses,
    ): bool {
        /** @var Provider $provider */
        $provider = $hostingDeployment->mailProvider()->firstOrFail();

        $domain = $hostingDeployment->subscription->domain;
        $server = $hostingDeployment->mailOnlyServer;
        $username = $this->hostingDeploymentService->getMailUsername($hostingDeployment);

        Assert::string($domain);
        Assert::isInstanceOf($server, Server::class);
        Assert::string($username);

        return $this->mailOnlyServiceFactory->driver($provider->slug)->createEmailForward(
            server: $server,
            identifier: $username,
            domain: $domain,
            sourceEmailAddressUsername: $sourceEmailAddressUsername,
            destinationEmailAddresses: $destinationEmailAddresses,
        );
    }

    /**
     * @throws EmailForwardException
     */
    public function deleteEmailForward(HostingDeployment $hostingDeployment, string $source): bool
    {
        /** @var Provider $provider */
        $provider = $hostingDeployment->mailProvider()->firstOrFail();
        $domain = $hostingDeployment->subscription->domain;
        $server = $hostingDeployment->mailOnlyServer;
        $username = $this->hostingDeploymentService->getMailUsername($hostingDeployment);

        Assert::string($domain);
        Assert::isInstanceOf($server, Server::class);
        Assert::string($username);

        return $this->mailOnlyServiceFactory->driver($provider->slug)->deleteEmailForward(
            server: $server,
            domain: $domain,
            identifier: $username,
            source: $source,
        );
    }

    /**
     * @throws EmailForwardException
     *
     * @return array<int, EmailForwardInterface>
     */
    public function getEmailForwards(
        string $domain,
        Server $server,
        string $username,
        ProviderSlug $providerSlug,
    ): array {
        return $this->mailOnlyServiceFactory->driver($providerSlug)->getEmailForwards(
            server: $server,
            domain: $domain,
            identifier: $username,
        );
    }

    /**
     * @throws EmailForwardException
     *
     * @return array<int, EmailForwardInterface>
     */
    public function getEmailForwardsFromDeployment(HostingDeployment $hostingDeployment): array
    {
        /** @var Provider $provider */
        $provider = $hostingDeployment->mailProvider()->firstOrFail();

        $domain = $hostingDeployment->subscription->domain;
        $server = $hostingDeployment->mailOnlyServer;
        $username = $this->hostingDeploymentService->getMailUsername($hostingDeployment);

        Assert::string($domain);
        Assert::isInstanceOf($server, Server::class);
        Assert::string($username);

        return $this->getEmailForwards(
            domain: $domain,
            server: $server,
            username: $username,
            providerSlug: $provider->slug,
        );
    }

    public function getMailHostingProviderSlug(HostingDeployment $hostingDeployment): ProviderSlug
    {
        $subscription = $hostingDeployment->subscription;
        $hasMailOnlyServer = $subscription->product->isMailOnlyServer();

        // Fallback for when server or mail only server is null because product spec changed
        if ($hasMailOnlyServer || $hostingDeployment->server === null) {
            if ($hostingDeployment->mailOnlyServer !== null) {
                return $this->providerRepository->getEnabledDefaultByType(ProviderType::MAILONLY)->slug;
            }
        }

        if ($subscription->hostingDeployment !== null) {
            $slug = $subscription->hostingDeployment->provider->slug ?? null;
            if ($slug !== null) {
                return $slug;
            }
        }

        $key = $subscription->id;

        $this->logger->warning(
            sprintf(
                'Subscription [%s - %s] is a hosting deployment but was not coupled to a HostingDeployment provider.',
                $subscription->domain,
                $subscription->uuid,
            ),
            [
                LoggingContextKeys::SUBSCRIPTION_ID => $key,
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                LoggingContextKeys::PRODUCT_SLUG => $subscription->product->slug,
                LoggingContextKeys::PRODUCT_ID => $subscription->product->id,
                LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                LoggingContextKeys::META => [
                    'hasMailOnlyServer' => false,
                    'hosting_deployment' => $subscription->hostingDeployment?->id,
                ],
            ],
        );

        return $this->providerRepository->getEnabledDefaultByType(ProviderType::HOSTING)->slug;
    }

    private function getQuota(ProviderSlug $slug, ProviderType $providerType): int
    {
        try {
            $provider = $this->providerRepository->getEnabledByType($providerType, $slug);
            $quota = $this->providerRepository->getSettingByKey($provider, ProviderSettingKey::QUOTA)->value;
        } catch (ModelNotFoundException) {
            $quota = 0;
        }

        return intval($quota);
    }

    private function getLimit(ProviderSlug $slug, ProviderType $providerType): int
    {
        $provider = $this->providerRepository->getEnabledByType($providerType, $slug);
        try {
            $limit = $this->providerRepository->getSettingByKey($provider, ProviderSettingKey::LIMIT)->value;
        } catch (ModelNotFoundException) {
            $limit = 0;
        }

        return intval($limit);
    }

    private function getHostingDeployment(Subscription $subscription): HostingDeployment
    {
        $hostingDeployment = $subscription->hostingDeployment;

        if (is_null($hostingDeployment)) {
            throw new ModelNotFoundException(
                sprintf(
                    'Hosting deployment not found for subscription [%s - %s]',
                    $subscription->domain,
                    $subscription->uuid,
                ),
            );
        }

        return $hostingDeployment;
    }

    private function sendUserCreatedConfirmationMail(Customer $customer, string $emailAddress): void
    {
        $this->mailer->send(
            [$customer],
            new EmailAccountCreatedMail(
                $emailAddress,
            ),
        );
    }
}
