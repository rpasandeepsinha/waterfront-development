<?php

declare(strict_types=1);

namespace Waterfront\Domain\MailManagement\Interfaces;

use GuzzleHttp\Exception\GuzzleException;
use ReflectionException;
use Waterfront\Domain\Hosting\DirectAdmin\Exceptions\EmailForwardException;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;

interface MailManagementDriverInterface
{
    public function getUsername(HostingDeployment $hostingDeployment): string;

    /**
     * @return array<string, mixed>
     */
    public function listDomain(string $hostname, string $domain, string $domainUser): array;

    /**
     * @throws GuzzleException
     * @throws DirectAdminCommandException
     * @throws ReflectionException
     *
     * @return array<string, mixed>
     */
    public function listDomainFromServer(Server $server, string $domain, string $domainUser): array;

    public function createDomain(string $domain, string $customerEmail): Result;

    public function createUser(
        string $hostname,
        string $domain,
        string $domainUser,
        string $mailUser,
        string $password,
        int $limit,
        int $quota
    ): Result;

    public function resetPassword(
        string $hostname,
        string $domain,
        string $domainUser,
        string $mailUser,
        string $password,
        int $quota
    ): Result;

    public function deleteDomain(string $hostname, string $domain, string $domainUser): Result;

    public function deleteUser(string $hostname, string $domain, string $domainUser, string $mailUser): Result;

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
    ): bool;

    /**
     * @return array<int, EmailForwardInterface>
     */
    public function getEmailForwards(Server $server, string $domain, string $identifier): array;

    public function deleteEmailForward(Server $server, string $domain, string $identifier, string $source): bool;
}
