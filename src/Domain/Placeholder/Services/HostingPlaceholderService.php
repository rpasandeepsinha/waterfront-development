<?php

declare(strict_types=1);

namespace Waterfront\Domain\Placeholder\Services;

use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Hosting\DirectAdmin\DTO\DomainOccupation;
use Waterfront\Domain\Hosting\DTO\DnsRecord;
use Waterfront\Domain\Hosting\DTO\UserStatistics;
use Waterfront\Domain\Hosting\Interfaces\Drivers\HostingServiceInterface;
use Waterfront\Domain\Hosting\Interfaces\Hosting\HostingOfferingInterface;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\CreateCustomer\Result as CreateCustomerResult;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Parameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Domain\Hosting\Interfaces\Hosting\SiteConfigInterface;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Provision\Hosting\Exceptions\DriverNotFoundException;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Ssl\Interfaces\Models\CertificateInstall\Parameters as CertificateInstallParameters;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;
use Waterfront\Support\Exceptions\NotImplementedException;

/**
 * This service mocks a HostingService, so we can migrate or handle hosting subscription without the technical execution part.
 */
class HostingPlaceholderService extends PlaceHolderService implements HostingServiceInterface
{
    public function coupleDomainToExistingHosting(
        DomainDeployment $domainDeployment,
        HostingDeployment $hostingDeployment,
    ): bool {
        throw new NotImplementedException();
    }

    public function serverIsValid(Server $server): bool
    {
        return true;
    }

    public function getCoupledHostingByDomain(DomainDeployment $domainDeployment): ?HostingDeployment
    {
        return null;
    }

    public function decoupleHostingByDomain(DomainDeployment $domainDeployment): void
    {
        throw new NotImplementedException();
    }

    /**
     * Gets a suitable server and fetches the secret key if not already available.
     */
    public function getServer(?HostingDeployment $hostingDeployment = null): Server
    {
        throw new NotImplementedException();
    }

    public function findServer(): ?Server
    {
        return null;
    }

    public function createCustomer(Parameters $parameters): CreateCustomerResult|DirectAdminCommand
    {
        throw new NotImplementedException();
    }

    public function createPackage(Parameters $parameters): Result
    {
        throw new NotImplementedException();
    }

    /**
     * Create a hosting deployment and handle everything it requires.
     *
     *
     * @throws DriverNotFoundException
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
        ?string $domain = null,
    ): array {
        $provider = Provider::where('slug', ProviderSlug::PLACEHOLDER)
            ->where('type', ProviderType::HOSTING)
            ->firstOrFail();

        $hostingDeployment = HostingDeployment::updateOrCreate(
            ['subscription_uuid' => $subscriptionUuid],
            ['provider_id' => $provider->id],
        );

        /** @var HostingDeployment $hostingDeployment */
        $subscription = $hostingDeployment->subscription;

        $provisionDetail = $this->getProvisionDetailFromSubscription($subscription);
        $this->notificationService->sendCreationNotification($provisionDetail);

        $return = [];
        $return['result'] = TechnicalStatus::PENDING->value;
        $return['domain'] = $domain;
        $return['username'] = '';

        return $return;
    }

    public function getSsoUrl(
        string $username,
        Server $server,
        string $ipAddress,
        bool $redirectToMail = false,
    ): never {
        throw new NotImplementedException();
    }

    /**
     * Get the URL to log into the hosting provider for SSO.
     */
    public function getServerSsoUrl(int $serverId): string
    {
        throw new NotImplementedException();
    }

    public function selectCertificate(Server $server, CertificateInstallParameters $parameters): string
    {
        throw new NotImplementedException();
    }

    public function installCertificate(string $subscriptionUuid, array $data): string
    {
        throw new NotImplementedException();
    }

    /**
     * Terminate a subscription.
     * We do this by deleting the customer. The subscription will automatically be deleted as well.
     *
     * @return bool true if successful or false if unsuccessful
     */
    public function terminate(string $domain, string $subscriptionUuid): bool
    {
        $subscription = Subscription::where('uuid', $subscriptionUuid)->first();

        if ($subscription === null) {
            return false;
        }

        $provisionDetail = $this->getProvisionDetailFromSubscription($subscription);
        $this->notificationService->sendTerminationNotification($provisionDetail);

        return true;
    }

    /**
     *
     *
     * @return array<string, string>
     */
    public function resetPassword(Parameters $parameters, UuidInterface $customerUuid): array
    {
        throw new NotImplementedException();
    }

    public function getUserStats(Parameters $parameters): UserStatistics
    {
        throw new NotImplementedException();
    }

    public function changeServicePlan(HostingDeployment $deployment, Product $oldProduct, Product $newProduct): Result
    {
        throw new NotImplementedException();
    }

    /**
     * @return array<mixed>
     */
    public function getCustomerConfig(Parameters $parameters): array
    {
        throw new NotImplementedException();
    }

    public function modifyCustomer(Parameters $parameters): bool
    {
        throw new NotImplementedException();
    }

    public function createEmailForward(
        Server $server,
        string $domain,
        string $sourceEmailAddressUsername,
        string $destinationEmailAddresses,
    ): string {
        throw new NotImplementedException();
    }

    public function setEmailCatchAll(Server $server, string $domain, string $destinationEmailAddresses): string
    {
        throw new NotImplementedException();
    }

    public function setDnsForHosting(Server $server, string $domain): void
    {
        throw new NotImplementedException();
    }

    public function resetDnsForSitebuilder(Server $server, Server $mailOnlyServer, string $domain): void
    {
        throw new NotImplementedException();
    }

    public function getDomainOccupation(HostingDeployment $hostingDeployment): DomainOccupation
    {
        throw new NotImplementedException();
    }

    public function suspend(HostingDeployment $hostingDeployment): void
    {
        throw new NotImplementedException();
    }

    public function unsuspend(HostingDeployment $hostingDeployment): void
    {
        throw new NotImplementedException();
    }

    public function getUserConfig(string $identifier, Server $server): array
    {
        throw new NotImplementedException();
    }

    /**
     * Please only use this function for migrations!!!
     *
     * @return array<mixed>
     */
    public function getUserConfigAsAdmin(string $identifier, Server $server): array
    {
        throw new NotImplementedException();
    }

    public function getUserConfigAsDto(string $identifier, Server $server): SiteConfigInterface
    {
        throw new NotImplementedException();
    }

    public function getDefaultDomain(string $username, Server $server): ?string
    {
        throw new NotImplementedException();
    }

    public function isUsingHostingServerAsNameserver(
        ?string $ipv4HostingServer,
        ?string $ipv6HostingServer,
        SiteConfigInterface $userConfig,
    ): bool {
        // throw new NotImplementedException here as we are not implementing hosting migration support for placholder subscriptions
        // but if the code ever gets here it will error out cause of the true since Nameservers
        // have to be different from the hosting server to continue migrating
        throw new NotImplementedException();
    }

    public function getPackagesOnServer(Server $server): array
    {
        throw new NotImplementedException();
    }

    public function getPackageOnServer(Server $server, string $packageName): array
    {
        throw new NotImplementedException();
    }

    public function getPackageOnServerAsDto(Server $server, string $packageName): HostingOfferingInterface
    {
        throw new NotImplementedException();
    }

    public function getCustomerDomainsForDkim(HostingDeployment $hostingDeployment): array
    {
        throw new NotImplementedException();
    }

    public function setDkim(HostingDeployment $hostingDeployment, string $domain, bool $enable): void
    {
        throw new NotImplementedException();
    }

    public function isDkimEnabled(HostingDeployment $hostingDeployment, string $domain): bool
    {
        throw new NotImplementedException();
    }

    public function getDkimRecord(HostingDeployment $hostingDeployment, string $domain): ?DnsRecord
    {
        throw new NotImplementedException();
    }
}
