<?php

declare(strict_types=1);

namespace Waterfront\Domain\MailManagement\Services;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ReflectionException;
use RuntimeException;
use Waterfront\Domain\DNS\Events\UpdateDns;
use Waterfront\Domain\DNS\Services\DnsZoneService;
use Waterfront\Domain\Email\Jobs\AddDomainToSpamFilter;
use Waterfront\Domain\Hosting\DirectAdmin\Exceptions\EmailForwardException;
use Waterfront\Domain\Hosting\DirectAdmin\Services\DirectadminUsernameBroker;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\MailManagement\Exceptions\MailOnlyException;
use Waterfront\Domain\MailManagement\Interfaces\MailManagementDriverInterface;
use Waterfront\Domain\MailManagement\Jobs\CleanupMailOnlyDns;
use Waterfront\Domain\MailManagement\Jobs\ConfigureMailOnlyDns;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\DirectAdminClient\BehavesAsDirectAdmin;
use Waterfront\Infra\DirectAdminClient\Commands\EmailForwards\CreateEmailForward;
use Waterfront\Infra\DirectAdminClient\Commands\EmailForwards\DeleteEmailForward;
use Waterfront\Infra\DirectAdminClient\Commands\EmailForwards\ShowEmailForwards;
use Waterfront\Infra\DirectAdminClient\Commands\Pop\CreatePopUser;
use Waterfront\Infra\DirectAdminClient\Commands\Pop\DeletePopUser;
use Waterfront\Infra\DirectAdminClient\Commands\Pop\ListPopDomain;
use Waterfront\Infra\DirectAdminClient\Commands\Pop\ModifyPopUser;
use Waterfront\Infra\DirectAdminClient\Commands\Users\CreateUser;
use Waterfront\Infra\DirectAdminClient\Commands\Users\DeleteUsers;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminException;
use Waterfront\Support\Enums\LoggingContextKeys;

class MailManagementDirectAdminService implements MailManagementDriverInterface
{
    public function __construct(
        private readonly BehavesAsDirectAdmin $directAdmin,
        private readonly Dispatcher $jobDispatcher,
        private readonly ConfigurationInterface $configuration,
        private readonly DnsZoneService $dnsZoneService,
        private readonly EventDispatcher $eventDispatcher,
        private readonly DirectadminUsernameBroker $directadminUsernameBroker,
        private readonly MailManagementServerService $serverService,
    ) {
    }

    /**
     * @throws GuzzleException
     * @throws DirectAdminCommandException
     * @throws ReflectionException
     * @throws RuntimeException
     *
     * @return array<string, mixed>
     */
    public function listDomain(string $hostname, string $domain, string $domainUser): array
    {
        $server = $this->serverService->findServerByDomain($domain);
        $listCommand = new ListPopDomain()->setDomain($domain);

        /**
         * @var ListPopDomain $result
         */
        $result = $this->directAdmin->useServer($server)->loginAs($domainUser)->call($listCommand);

        return [
            'domain' => $domain,
            'users' => $result->getUsers(),
        ];
    }

    /**
     * @throws GuzzleException
     * @throws DirectAdminCommandException
     * @throws ReflectionException
     *
     * @return array<string, mixed>
     */
    public function listDomainFromServer(Server $server, string $domain, string $domainUser): array
    {
        $listCommand = new ListPopDomain()->setDomain($domain);

        /**
         * @var ListPopDomain $result
         */
        $result = $this->directAdmin->useServer($server)->loginAs($domainUser)->call($listCommand);

        return [
            'domain' => $domain,
            'users' => $result->getUsers(),
        ];
    }

    /**
     * @throws GuzzleException
     */
    public function createDomain(string $domain, string $customerEmail): Result
    {
        $server = $this->serverService->findServerByDomain($domain);

        $ipAddress = $server->getIpv4();

        if (is_null($ipAddress)) {
            throw new MailOnlyException('Unable to resolve ipv4', 0, null, null, $server);
        }

        $mailUsername = $this->directadminUsernameBroker->generateUsername();

        $result = new Result();
        $command = new CreateUser()
            ->setDomain($domain)
            ->setEmail($customerEmail)
            ->setPasswd(Str::random(20))
            ->setUsername($mailUsername)
            ->setIp($ipAddress);

        try {
            $response = $this->directAdmin->useServer($server)->call($command);

            Log::info('Response received', [
                LoggingContextKeys::RESPONSE_DATA => $response->getResponseBody(),
            ]);

            $result->setStatus(Result::STATUS_OK);

            $this->jobDispatcher->dispatch(new ConfigureMailOnlyDns(
                $domain,
                $this->configuration->getAsString('mailonly.connection.hostname'),
                $this->configuration->getAsString('mailonly.connection.fallback_hostname'),
                $ipAddress,
            ));
        } catch (DirectAdminException $exception) {
            throw MailOnlyException::fromDirectAdminResponseException($exception, $server, $domain);
        }

        $result->setResourceId($mailUsername);
        $result->setServerId($server->id);

        $this->setDnsForEmailOnly($server, $domain);

        return $result;
    }

    /**
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function createUser(
        string $hostname,
        string $domain,
        string $domainUser,
        string $mailUser,
        string $password,
        int $limit,
        int $quota,
    ): Result {
        $result = new Result();

        $server = $this->serverService->findServerByDomain($domain);
        $command = new CreatePopUser()
            ->setDomain($domain)
            ->setUser($mailUser)
            ->setPassword($password)
            ->setLimit($limit)
            ->setQuota($quota);

        try {
            $this->directAdmin->useServer($server)->loginAs($domainUser)->call($command);

            $result->setStatus(Result::STATUS_OK);
        } catch (DirectAdminException $exception) {
            throw MailOnlyException::fromDirectAdminResponseException($exception, $server, $domain);
        }

        return $result;
    }

    /**
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function resetPassword(
        string $hostname,
        string $domain,
        string $domainUser,
        string $mailUser,
        string $password,
        int $quota,
    ): Result {
        $result = new Result();
        $server = $this->serverService->findServerByDomain($domain);

        $command = new ModifyPopUser()
            ->setDomain($domain)
            ->setUser($mailUser)
            ->setPassword($password)
            ->setQuota($quota);

        try {
            $this->directAdmin->useServer($server)->loginAs($domainUser)->call($command);

            $result->setStatus(Result::STATUS_OK);
        } catch (DirectAdminException $exception) {
            throw MailOnlyException::fromDirectAdminResponseException($exception, $server, $domain);
        }

        return $result;
    }

    /**
     * @throws GuzzleException
     */
    public function deleteDomain(string $hostname, string $domain, string $domainUser): Result
    {
        $server = $this->serverService->findServerByDomain($domain);
        $command = new DeleteUsers()->addUser($domainUser);
        $result = new Result();

        try {
            $this->directAdmin->useServer($server)->call($command);
            $result->setStatus(Result::STATUS_OK);

            $this->jobDispatcher->dispatch(new CleanupMailOnlyDns(
                $domain,
                $this->configuration->getAsString('mailonly.connection.hostname'),
                $this->configuration->getAsString('mailonly.connection.fallback_hostname'),
            ));
        } catch (DirectAdminException $exception) {
            throw MailOnlyException::fromDirectAdminResponseException($exception, $server, $domain);
        }

        return $result;
    }

    /**
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function deleteUser(string $hostname, string $domain, string $domainUser, string $mailUser): Result
    {
        $server = $this->serverService->findServerByDomain($domain);

        $result = new Result();

        $command = new DeletePopUser()
            ->setDomain($domain)
            ->setUser($mailUser);

        try {
            $this->directAdmin->useServer($server)->loginAs($domainUser)->call($command);

            $result->setStatus(Result::STATUS_OK);
        } catch (DirectAdminException $exception) {
            throw MailOnlyException::fromDirectAdminResponseException($exception, $server, $domain);
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function getUsername(HostingDeployment $hostingDeployment): string
    {
        $username = $hostingDeployment->directadmin_customer_username;

        if ($username === null) {
            throw new MailOnlyException(
                sprintf(
                    'Could not retrieve directadmin customer username from hostingSubscriptionUuid "%s"',
                    $hostingDeployment->uuid,
                ),
            );
        }

        return $username;
    }

    /**
     * @param array<int, string> $destinationEmailAddresses
     *
     * @throws EmailForwardException
     */
    public function createEmailForward(
        Server $server,
        string $identifier,
        string $domain,
        string $sourceEmailAddressUsername,
        array $destinationEmailAddresses,
    ): bool {
        $command = new CreateEmailForward();
        $command->setDomain($domain)->setUser($sourceEmailAddressUsername)->setEmail($destinationEmailAddresses);

        try {
            /** @var DeleteEmailForward $executedCommand */
            $executedCommand = $this->directAdmin->useServer($server)->loginAs($identifier)->call($command);
        } catch (DirectAdminCommandException $exception) {
            throw new EmailForwardException(
                server: $server,
                domain: $domain,
                identifier: $identifier,
                payload: [
                    'source' => $sourceEmailAddressUsername,
                    'destination' => $destinationEmailAddresses,
                ],
                previous: $exception,
            );
        }

        return $executedCommand->hasSucceeded();
    }

    /**
     * @returns array<int, EmailForwardInterface>
     *
     * @throws EmailForwardException
     */
    public function getEmailForwards(Server $server, string $domain, string $identifier): array
    {
        $command = new ShowEmailForwards();
        $command->setDomain($domain);

        try {
            /** @var ShowEmailForwards $executedCommand */
            $executedCommand = $this->directAdmin->useServer($server)->loginAs($identifier)->call($command);
        } catch (DirectAdminCommandException $exception) {
            throw new EmailForwardException(
                server: $server,
                domain: $domain,
                identifier: $identifier,
                previous: $exception,
            );
        }

        return $executedCommand->getForwards();
    }

    public function deleteEmailForward(Server $server, string $domain, string $identifier, string $source): bool
    {
        $command = new DeleteEmailForward();
        $command->setDomain($domain)->setSelect0($source);

        try {
            /** @var DeleteEmailForward $executedCommand */
            $executedCommand = $this->directAdmin->useServer($server)->loginAs($identifier)->call($command);
        } catch (DirectAdminCommandException $exception) {
            throw new EmailForwardException(
                server: $server,
                domain: $domain,
                identifier: $identifier,
                payload: [
                    'source' => $source,
                ],
                previous: $exception,
            );
        }

        return $executedCommand->hasSucceeded();
    }

    private function setDnsForEmailOnly(Server $server, string $domain): void
    {
        $changes = $this->dnsZoneService->getHostingDnsRecords(
            $domain,
            $server->getIpv4(),
            $server->getIpv6(),
        );

        $this->eventDispatcher->dispatch(new UpdateDns($domain, $changes));

        $this->jobDispatcher->dispatch(new AddDomainToSpamFilter($domain, null));
    }
}
