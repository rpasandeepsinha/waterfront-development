<?php

declare(strict_types=1);

namespace Waterfront\Domain\MailManagement\Services;

use Illuminate\Contracts\Bus\Dispatcher as JobDispatcher;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Waterfront\Domain\DNS\Events\UpdateDns;
use Waterfront\Domain\DNS\Services\DnsZoneService;
use Waterfront\Domain\Email\Jobs\AddDomainToSpamFilter;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Plesk\Services\PleskHostingService;
use Waterfront\Domain\Hosting\Repositories\HostingDeploymentRepository;
use Waterfront\Domain\Hosting\Repositories\ServerRepository;
use Waterfront\Domain\MailManagement\Exceptions\MailOnlyException;
use Waterfront\Domain\MailManagement\Interfaces\MailManagementDriverInterface;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Subscriptions\Exceptions\SubscriptionNotFoundException;
use Waterfront\Infra\PleskClient\DTO\MailAccount;
use Waterfront\Infra\PleskClient\Exceptions\PleskClientException;
use Waterfront\Support\Exceptions\NotImplementedException;

class MailManagementPleskService implements MailManagementDriverInterface
{
    public function __construct(
        private readonly HostingDeploymentRepository $hostingDeploymentRepository,
        private readonly PleskHostingService $hostingService,
        private readonly ServerRepository $serverRepository,
        private readonly DnsZoneService $dnsZoneService,
        private readonly JobDispatcher $jobDispatcher,
        private readonly EventDispatcher $eventDispatcher,
    ) {
    }

    public function listDomain(string $hostname, string $domain, string $domainUser): array
    {
        $server = $this->serverRepository->findByHostname($hostname);
        $mailAccounts = $this->hostingService->getMailAccounts($server, $domain);

        $mailboxAccounts = array_filter($mailAccounts, fn (MailAccount $mailAccount) => $mailAccount->isMailboxEnabled());
        $mailUsers = array_map(fn (MailAccount $mailAccount) => $mailAccount->getMailName(), $mailboxAccounts);

        return [
            'domain' => $domain,
            'users' => array_values($mailUsers),
        ];
    }

    /**
     * @throws NotImplementedException
     *
     * @return array<string, mixed>
     */
    public function listDomainFromServer(Server $server, string $domain, string $domainUser): array
    {
        throw new NotImplementedException();
    }

    /**
     * @throws PleskClientException
     * @throws SubscriptionNotFoundException
     */
    public function createDomain(string $domain, string $customerEmail): Result
    {
        $subscription = $this->hostingDeploymentRepository->getByActiveDomain($domain);

        if (! $subscription->product->isSitebuilderProduct() && ! $subscription->product->isMailOnlyServer()) {
            throw new NotImplementedException(sprintf(
                'Plesk mail-only does only support sitebuilder, subscription %s is not a sitebuilder subscription.',
                $subscription->uuid,
            ));
        }

        $server = $this->serverRepository->findAvailableServer(
            ServerType::PLESK,
        );

        $ipAddress = $server->getIpv4();
        $ip6Address = $server->getIpv6();

        if ($ipAddress === null || $ip6Address === null) {
            throw new MailOnlyException(
                message: sprintf(
                    'Server %s does not have an IPv4 or IPv6 address.',
                    $server->id,
                ),
                server: $server,
            );
        }

        $hostingResult = $this->hostingService->createForMailOnly(
            contactPersonName: $subscription->customer->contact_name,
            contactEmail: $subscription->customer->email,
            customerEmail: $subscription->customer->email,
            customerUuid: $subscription->customer->uuid,
            domain: $domain,
            subscriptionUuid: $subscription->uuid,
        );

        if ($hostingResult === Result::STATUS_ERROR) {
            throw new MailOnlyException(
                sprintf(
                    'Could not create mail only hosting in Plesk for SubscriptionUuid "%s"',
                    $subscription->uuid,
                ),
            );
        }

        $result = new Result();
        $result->setStatus($hostingResult);

        $result->setResourceId((string) $subscription->hostingDeployment?->plesk_customer_username);
        $result->setServerId($server->id);

        $this->setDnsForEmailOnly($server, $domain, $subscription->product->isSitebuilderProduct());

        return $result;
    }

    public function createUser(
        string $hostname,
        string $domain,
        string $domainUser,
        string $mailUser,
        string $password,
        int $limit,
        int $quota,
    ): Result {
        $server = $this->serverRepository->findByHostname($hostname);

        return $this->hostingService->createEmailAccount(
            server: $server,
            mailAccount: $mailUser,
            domain: $domain,
            password: $password,
        );
    }

    public function resetPassword(
        string $hostname,
        string $domain,
        string $domainUser,
        string $mailUser,
        string $password,
        int $quota,
    ): Result {
        $server = $this->serverRepository->findByHostname($hostname);

        return $this->hostingService->resetEmailPassword(
            server: $server,
            mailAccount: $mailUser,
            domain: $domain,
            password: $password,
        );
    }

    public function deleteDomain(string $hostname, string $domain, string $domainUser): Result
    {
        $subscription = $this->hostingDeploymentRepository->getByDomain($domain);

        if (! $subscription->product->isSitebuilderProduct() && ! $subscription->product->isMailOnlyServer()) {
            throw new NotImplementedException(sprintf(
                'Plesk mail-only does only support sitebuilder, subscription %s is not a sitebuilder subscription.',
                $subscription->uuid,
            ));
        }

        /** @var HostingDeployment $hostingDeployment */
        $hostingDeployment = $subscription->hostingDeployment;

        $hostingResult = $this->hostingService->terminatePleskMailOnly($domain, $hostingDeployment);
        if ($hostingResult === Result::STATUS_ERROR) {
            throw new MailOnlyException(
                sprintf(
                    'Could not terminate mail only hosting in Plesk for hostingSubscriptionUuid "%s"',
                    $hostingDeployment->uuid,
                ),
            );
        }

        $result = new Result();
        $result->setStatus(Result::STATUS_OK);

        return $result;
    }

    public function deleteUser(string $hostname, string $domain, string $domainUser, string $mailUser): Result
    {
        $server = $this->serverRepository->findByHostname($hostname);

        return $this->hostingService->deleteEmailAccount($server, $domain, $mailUser);
    }

    /**
     * {@inheritDoc}
     */
    public function getUsername(HostingDeployment $hostingDeployment): string
    {
        $username = $hostingDeployment->plesk_customer_username;

        if ($username === null) {
            throw new MailOnlyException(
                sprintf(
                    'Could not retrieve plesk customer username from hosting deployment UUID "%s"',
                    $hostingDeployment->uuid,
                ),
            );
        }

        return $username;
    }

    /**
     * @param array<int, string> $destinationEmailAddresses
     */
    public function createEmailForward(
        Server $server,
        string $identifier,
        string $domain,
        string $sourceEmailAddressUsername,
        array $destinationEmailAddresses,
    ): bool {
        throw new NotImplementedException();
    }

    public function getEmailForwards(Server $server, string $domain, string $identifier): array
    {
        throw new NotImplementedException();
    }

    public function deleteEmailForward(Server $server, string $domain, string $identifier, string $source): bool
    {
        throw new NotImplementedException();
    }

    private function setDnsForEmailOnly(Server $server, string $domain, bool $siteBuilderSubscription): void
    {
        if ($siteBuilderSubscription === false) {
            $changes = $this->dnsZoneService->getHostingDnsRecords(
                $domain,
                $server->getIpv4(),
                $server->getIpv6(),
            );

            $this->eventDispatcher->dispatch(new UpdateDns($domain, $changes));
        }

        $this->jobDispatcher->dispatch(new AddDomainToSpamFilter($domain, null));
    }
}
