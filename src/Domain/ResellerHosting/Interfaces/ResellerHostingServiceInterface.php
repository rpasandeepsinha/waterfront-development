<?php

declare(strict_types=1);

namespace Waterfront\Domain\ResellerHosting\Interfaces;

use GuzzleHttp\Exception\GuzzleException;
use Ramsey\Uuid\UuidInterface;
use ReflectionException;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Domain\ResellerHosting\Exceptions\ResellerHostingException;
use Waterfront\Domain\ResellerHosting\Exceptions\ResellerHostingNameserverCoupleException;
use Waterfront\Domain\ResellerHosting\Exceptions\ResellerHostingSslCoupleException;
use Waterfront\Domain\ResellerHosting\Models\ResellerHostingDeployment;
use Waterfront\Domain\ResellerHosting\Parameters\AppResellerHostingDomainCoupleParameters;
use Waterfront\Domain\ResellerHosting\Parameters\ResellerHostingParameters;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;

interface ResellerHostingServiceInterface
{
    public function findServer(): Server;

    /**
     * Create a reseller hosting account.
     */
    public function createReseller(ResellerHostingParameters $parameters, Server $server): Result;

    /**
     * Get the sub-accounts of a reseller.
     *
     * @return string[]
     */
    public function getSubAccounts(ResellerHostingDeployment $resellerHostingDeployment): array;

    /**
     * Create a hosting subscription and handle everything it requires.
     *
     * @param array<string, string> $specs
     *
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
    ): string;

    /**
     * Generate a unique reseller username.
     */
    public function generateUsername(): string;

    /**
     * Terminate a subscription.
     * We do this by deleting the customer. The subscription will automatically be deleted as well.
     */
    public function terminate(ResellerHostingDeployment $deployment): bool;

    /**
     * @return array<string,string>
     */
    public function resetPassword(ResellerHostingParameters $parameters, UuidInterface $customerUuid): array;

    /**
     * @return array<string,string>
     */
    public function getUserStats(ResellerHostingParameters $parameters): array;

    /**
     * Generate a domain to be used when creating a reseller hosting account.
     */
    public function generateDomain(string $username, string $fqdn): string;

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
    ): bool;

    public function modifyCustomerForResellerMigrations(string $username): bool;
}
