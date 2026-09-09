<?php

declare(strict_types=1);

namespace Waterfront\Domain\MailManagement\Services;

use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\MailManagement\Interfaces\MailManagementDriverInterface;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Support\Exceptions\NotImplementedException;

class MailManagementPlaceholderService implements MailManagementDriverInterface
{
    public function getUsername(HostingDeployment $hostingDeployment): string
    {
        throw new NotImplementedException();
    }

    public function listDomain(string $hostname, string $domain, string $domainUser): array
    {
        return [];
    }

    public function listDomainFromServer(Server $server, string $domain, string $domainUser): array
    {
        throw new NotImplementedException();
    }

    public function createDomain(string $domain, string $customerEmail): Result
    {
        throw new NotImplementedException();
    }

    public function createUser(string $hostname, string $domain, string $domainUser, string $mailUser, string $password, int $limit, int $quota): Result
    {
        throw new NotImplementedException();
    }

    public function resetPassword(string $hostname, string $domain, string $domainUser, string $mailUser, string $password, int $quota): Result
    {
        throw new NotImplementedException();
    }

    public function deleteDomain(string $hostname, string $domain, string $domainUser): Result
    {
        throw new NotImplementedException();
    }

    public function deleteUser(string $hostname, string $domain, string $domainUser, string $mailUser): Result
    {
        throw new NotImplementedException();
    }

    public function createEmailForward(Server $server, string $identifier, string $domain, string $sourceEmailAddressUsername, array $destinationEmailAddresses): bool
    {
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
}
