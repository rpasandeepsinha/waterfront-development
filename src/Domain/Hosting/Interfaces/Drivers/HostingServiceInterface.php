<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Interfaces\Drivers;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Ramsey\Uuid\UuidInterface;
use ReflectionException;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Hosting\DirectAdmin\DTO\DomainOccupation;
use Waterfront\Domain\Hosting\DTO\DnsRecord;
use Waterfront\Domain\Hosting\DTO\UserStatistics;
use Waterfront\Domain\Hosting\Exceptions\HostingException;
use Waterfront\Domain\Hosting\Interfaces\Hosting\HostingOfferingInterface;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\CreateCustomer\Result as CreateCustomerResult;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Parameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Domain\Hosting\Interfaces\Hosting\SiteConfigInterface;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Ssl\Interfaces\Models\CertificateInstall\Parameters as CertificateInstallParameters;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;

interface HostingServiceInterface
{
    public const UNLIMITED_DOMAIN_REPRESENTATION = 999999;

    public function coupleDomainToExistingHosting(
        DomainDeployment $domainDeployment,
        HostingDeployment $hostingDeployment
    ): bool;

    public function getSsoUrl(
        string $username,
        Server $server,
        string $ipAddress,
        bool $redirectToMail = false
    ): string;

    public function serverIsValid(Server $server): bool;

    public function getCoupledHostingByDomain(DomainDeployment $domainDeployment): ?HostingDeployment;

    public function decoupleHostingByDomain(DomainDeployment $domainDeployment): void;

    /**
     * Gets a suitable server and fetches the secret key if not already available.
     */
    public function getServer(?HostingDeployment $hostingDeployment = null): Server;

    public function findServer(): ?Server;

    /**
     * @throws Exception
     */
    public function createCustomer(Parameters $parameters): CreateCustomerResult|DirectAdminCommand;

    /**
     * @throws Exception
     */
    public function createPackage(Parameters $parameters): Result;

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
    ): array;

    public function getServerSsoUrl(int $serverId): string;

    public function selectCertificate(Server $server, CertificateInstallParameters $parameters): string;

    /**
     * @param array<string> $data
     */
    public function installCertificate(string $subscriptionUuid, array $data): string;

    public function terminate(string $domain, string $subscriptionUuid): bool;

    /**
     * @return array<string, string>
     */
    public function resetPassword(Parameters $parameters, UuidInterface $customerUuid): array;

    public function getUserStats(Parameters $parameters): ?UserStatistics;

    public function changeServicePlan(HostingDeployment $deployment, Product $oldProduct, Product $newProduct): Result;

    /**
     * @return array<mixed, mixed>
     */
    public function getPackagesOnServer(Server $server): array;

    /**
     * @return array<mixed, mixed>
     */
    public function getPackageOnServer(Server $server, string $packageName): array;

    /**
     * @throws ReflectionException
     * @throws HostingException
     * @throws GuzzleException
     */
    public function getPackageOnServerAsDto(Server $server, string $packageName): HostingOfferingInterface;

    /**
     * @return array<mixed>
     */
    public function getCustomerConfig(Parameters $parameters): array;

    /**
     * @throws HostingException
     */
    public function getUserConfigAsDto(string $identifier, Server $server): SiteConfigInterface;

    /**
     * @return array<mixed>
     */
    public function getUserConfig(string $identifier, Server $server): array;

    /**
     * Please only use this function for migrations!!!
     *
     * @return array<mixed>
     */
    public function getUserConfigAsAdmin(string $identifier, Server $server): array;

    public function isUsingHostingServerAsNameserver(string|null $ipv4HostingServer, string|null $ipv6HostingServer, SiteConfigInterface $userConfig): bool;

    public function modifyCustomer(Parameters $parameters): bool;

    public function createEmailForward(Server $server, string $domain, string $sourceEmailAddressUsername, string $destinationEmailAddresses): string;

    public function setEmailCatchAll(Server $server, string $domain, string $destinationEmailAddresses): string;

    public function setDnsForHosting(Server $server, string $domain): void;

    public function resetDnsForSitebuilder(Server $server, Server $mailOnlyServer, string $domain): void;

    /**
     * @throws HostingException
     */
    public function getDomainOccupation(HostingDeployment $hostingDeployment): DomainOccupation;

    public function suspend(HostingDeployment $hostingDeployment): void;

    public function unsuspend(HostingDeployment $hostingDeployment): void;

    public function getDefaultDomain(string $username, Server $server): string|null;

    /**
     * @return string[]
     */
    public function getCustomerDomainsForDkim(HostingDeployment $hostingDeployment): array;

    public function isDkimEnabled(HostingDeployment $hostingDeployment, string $domain): bool;

    public function setDkim(HostingDeployment $hostingDeployment, string $domain, bool $enable): void;

    public function getDkimRecord(HostingDeployment $hostingDeployment, string $domain): ?DnsRecord;
}
