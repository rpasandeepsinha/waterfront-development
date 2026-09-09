<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Plesk\Services;

use InvalidArgumentException;
use RuntimeException;
use Waterfront\Domain\Hosting\Interfaces\Hosting\CustomerInterface;
use Waterfront\Domain\Hosting\Interfaces\Hosting\HostingPackageInterface;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\ChangeHostingPackageStatus\Parameters as ChangeHostingPackageStatusParameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\CreateCustomer\Result as CustomerCreateResult;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\DeleteCustomer\Parameters as CustomerDeleteParameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\DeleteCustomer\Result as CustomerDeleteResult;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\DeleteWebsite\Parameters as WebsiteDeleteParameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\EmailForwardingCreate\Parameters as EmailForwardingCreateParameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\EmailGetAccountSettings\Parameters as EmailGetAccountSettingsParameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\EmailSetCatchAll\Parameters as EmailSetCatchAllParameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Parameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Parameters as HostingParameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\SecretKeyCreate\Result as SecretKeyCreateResult;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\SecretKeyGet\Result as SecretKeyGetResult;
use Waterfront\Domain\Hosting\Interfaces\Hosting\SecretKeyInterface;
use Waterfront\Domain\Hosting\Interfaces\Hosting\SessionTokenInterface;
use Waterfront\Domain\Hosting\Plesk\Services\ClientList\ClientListStrategyInterface;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Ssl\Interfaces\InstallInterface;
use Waterfront\Domain\Ssl\Interfaces\SelectInterface;
use Waterfront\Infra\PleskClient\Messages\CreateSite\CreateSiteResult;
use Waterfront\Infra\PleskClient\Messages\CustomerGetDomainList\CustomerGetDomainListResult;
use Waterfront\Infra\PleskClient\Messages\DnsGetRecords\DnsRecordsResult;
use Waterfront\Infra\PleskClient\Messages\EmailAccountCreate\EmailAccountCreateResponse;
use Waterfront\Infra\PleskClient\Messages\EmailAccountDelete\EmailAccountDeleteResponse;
use Waterfront\Infra\PleskClient\Messages\EmailGetAccountSettings\Result as EmailGetAccountSettingsResult;
use Waterfront\Infra\PleskClient\Messages\EmailPasswordReset\EmailPasswordResetResponse;
use Waterfront\Infra\PleskClient\Messages\RemoveSite\RemoveSiteResult;

/**
 * Class that iterates over a list of HostingPackageInterface instances until one of them returns true on setServer.
 *
 */
class ChainableHostingPackageClient implements
    HostingPackageInterface,
    InstallInterface,
    SecretKeyInterface,
    SelectInterface,
    SessionTokenInterface,
    CustomerInterface
{
    private ?Server $selectedServer = null;

    /**
     * @var string[]|null
     */
    private ?array $selectedCredentials = null;

    public function __construct(
        private readonly ClientListStrategyInterface $hostingClients,
        private readonly ClientListStrategyInterface $installClients,
        private readonly ClientListStrategyInterface $secretKeyClients,
        private readonly ClientListStrategyInterface $selectClients,
        private readonly ClientListStrategyInterface $sessionTokenClients,
        private readonly ClientListStrategyInterface $customerClients
    ) {
    }

    /**
     * Set a specific target server for the hosting.
     *
     * @param string[]|null $credentials
     */
    public function setServer(Server $server, ?array $credentials = null): bool
    {
        $this->selectedServer = $server;
        $this->selectedCredentials = $credentials;

        return true;
    }

    public function createHosting(Parameters $parameters): Result
    {
        if ($this->selectedServer === null) {
            throw new RuntimeException('I have no selected server instance.');
        }

        $hostingClient = $this->hostingClients->selectClient($this->selectedServer, $this->selectedCredentials);
        if (! $hostingClient instanceof HostingPackageInterface) {
            throw new RuntimeException('Hosting client should be an instance of HostingPackageInterface.');
        }

        return $hostingClient->createHosting($parameters);
    }

    public function resetEmailPassword(string $domain, string $emailAccount, string $password): EmailPasswordResetResponse
    {
        if ($this->selectedServer === null) {
            throw new RuntimeException('I have no selected server instance.');
        }

        $hostingClient = $this->hostingClients->selectClient($this->selectedServer, $this->selectedCredentials);
        if (! $hostingClient instanceof HostingPackageInterface) {
            throw new RuntimeException('Hosting client should be an instance of HostingPackageInterface.');
        }

        return $hostingClient->resetEmailPassword($domain, $emailAccount, $password);
    }

    public function createEmailAccount(string $domain, string $emailAccount, string $password): EmailAccountCreateResponse
    {
        if ($this->selectedServer === null) {
            throw new RuntimeException('I have no selected server instance.');
        }

        $hostingClient = $this->hostingClients->selectClient($this->selectedServer, $this->selectedCredentials);
        if (! $hostingClient instanceof HostingPackageInterface) {
            throw new RuntimeException('Hosting client should be an instance of HostingPackageInterface.');
        }

        return $hostingClient->createEmailAccount($domain, $emailAccount, $password);
    }

    public function createCustomer(Parameters $parameters): CustomerCreateResult
    {
        if ($this->selectedServer === null) {
            throw new RuntimeException('I have no selected server instance.');
        }

        $customerClient = $this->customerClients->selectClient($this->selectedServer, $this->selectedCredentials);
        if (! $customerClient instanceof CustomerInterface) {
            throw new RuntimeException('Customer client should be an instance of CustomerInterface.');
        }

        return $customerClient->createCustomer($parameters);
    }

    public function deleteCustomer(CustomerDeleteParameters $parameters): CustomerDeleteResult
    {
        if ($this->selectedServer === null) {
            throw new RuntimeException('I have no selected server instance.');
        }

        $customerClient = $this->customerClients->selectClient($this->selectedServer, $this->selectedCredentials);
        if (! $customerClient instanceof CustomerInterface) {
            throw new RuntimeException('Customer client should be an instance of CustomerInterface.');
        }

        return $customerClient->deleteCustomer($parameters);
    }

    public function installCertificate(\Waterfront\Domain\Ssl\Interfaces\Models\CertificateInstall\Parameters $parameters): string
    {
        if ($this->selectedServer === null) {
            throw new RuntimeException('I have no selected server instance.');
        }

        $installClient = $this->installClients->selectClient($this->selectedServer, $this->selectedCredentials);
        if (! $installClient instanceof InstallInterface) {
            throw new RuntimeException('Install client should be an instance of InstallInterface.');
        }

        return $installClient->installCertificate($parameters);
    }

    public function getSecretKeys(): SecretKeyGetResult
    {
        if ($this->selectedServer === null) {
            throw new RuntimeException('I have no selected server instance.');
        }

        $secretKeyClient = $this->secretKeyClients->selectClient($this->selectedServer, $this->selectedCredentials);
        if (! $secretKeyClient instanceof SecretKeyInterface) {
            throw new RuntimeException('Secret key client should be an instance of SecretKeyInterface.');
        }

        return $secretKeyClient->getSecretKeys();
    }

    public function createSecretKey(string $ipAddress): SecretKeyCreateResult
    {
        if ($this->selectedServer === null) {
            throw new RuntimeException('I have no selected server instance.');
        }

        $secretKeyClient = $this->secretKeyClients->selectClient($this->selectedServer, $this->selectedCredentials);
        if (! $secretKeyClient instanceof SecretKeyInterface) {
            throw new RuntimeException('Secret key client should be an instance of SecretKeyInterface.');
        }

        return $secretKeyClient->createSecretKey($ipAddress);
    }

    public function selectCertificate(string $domain, string $certificateName): string
    {
        if ($this->selectedServer === null) {
            throw new RuntimeException('I have no selected server instance.');
        }

        $selectClient = $this->selectClients->selectClient($this->selectedServer, $this->selectedCredentials);
        if (! $selectClient instanceof SelectInterface) {
            throw new RuntimeException('Select client should be an instance of SelectInterface.');
        }

        return $selectClient->selectCertificate($domain, $certificateName);
    }

    /**
     * Get the URL to log into the hosting provider for SSO.
     */
    public function getSsoUrl(
        string $username,
        string $ipAddress,
        bool $redirectToMail = false
    ): string {
        if ($this->selectedServer === null) {
            throw new RuntimeException('I have no selected server instance.');
        }

        $sessionTokenClient = $this->sessionTokenClients
            ->selectClient($this->selectedServer, $this->selectedCredentials);
        if (! $sessionTokenClient instanceof SessionTokenInterface) {
            throw new RuntimeException('Session token client should be an instance of SessionTokenInterface.');
        }

        return $sessionTokenClient->getSsoUrl($username, $ipAddress, $redirectToMail);
    }

    public function getServerSsoUrl(): string
    {
        if ($this->selectedServer === null) {
            throw new RuntimeException('I have no selected server instance.');
        }

        $sessionTokenClient = $this->sessionTokenClients
            ->selectClient($this->selectedServer, $this->selectedCredentials);
        if (! $sessionTokenClient instanceof SessionTokenInterface) {
            throw new RuntimeException('Session token client should be an instance of SessionTokenInterface.');
        }

        return $sessionTokenClient->getServerSsoUrl();
    }

    /**
     * Upgrade hosting package.
     */
    public function changeServicePlan(string $domain, string $servicePlanGuid): Result
    {
        if ($this->selectedServer === null) {
            throw new RuntimeException('I have no selected server instance.');
        }

        $hostingClient = $this->hostingClients->selectClient($this->selectedServer, $this->selectedCredentials);
        if (! $hostingClient instanceof HostingPackageInterface) {
            throw new RuntimeException('Hosting client should be an instance of HostingPackageInterface.');
        }

        return $hostingClient->changeServicePlan($domain, $servicePlanGuid);
    }

    /**
     * @return array<mixed>
     */
    public function getServicePlan(Parameters $parameters): array
    {
        if ($this->selectedServer === null) {
            throw new RuntimeException('I have no selected server instance.');
        }

        $hostingClient = $this->hostingClients->selectClient($this->selectedServer, $this->selectedCredentials);
        if (! $hostingClient instanceof HostingPackageInterface) {
            throw new RuntimeException('Hosting client should be an instance of HostingPackageInterface.');
        }

        return $hostingClient->getServicePlan($parameters);
    }

    public function getServicePlanByGuid(HostingParameters $parameters): string
    {
        if ($this->selectedServer === null) {
            throw new RuntimeException('I have no selected server instance.');
        }

        $hostingClient = $this->hostingClients->selectClient($this->selectedServer, $this->selectedCredentials);
        if (! $hostingClient instanceof HostingPackageInterface) {
            throw new RuntimeException('Hosting client should be an instance of HostingPackageInterface.');
        }

        return $hostingClient->getServicePlanByGuid($parameters);
    }

    public function servicePlanExists(Parameters $parameters): bool
    {
        if ($this->selectedServer === null) {
            throw new RuntimeException('I have no selected server instance.');
        }

        $hostingClient = $this->hostingClients->selectClient($this->selectedServer, $this->selectedCredentials);
        if (! $hostingClient instanceof HostingPackageInterface) {
            throw new RuntimeException('Hosting client should be an instance of HostingPackageInterface.');
        }

        return $hostingClient->servicePlanExists($parameters);
    }

    public function deleteWebsite(WebsiteDeleteParameters $parameters): Result
    {
        if ($this->selectedServer === null) {
            throw new RuntimeException('I have no selected server instance.');
        }

        $hostingClient = $this->hostingClients->selectClient($this->selectedServer, $this->selectedCredentials);
        if (! $hostingClient instanceof HostingPackageInterface) {
            throw new RuntimeException('Hosting client should be an instance of HostingPackageInterface.');
        }

        return $hostingClient->deleteWebsite($parameters);
    }

    public function fetchCustomer(Parameters $parameters): Result
    {
        if ($this->selectedServer === null) {
            throw new RuntimeException('I have no selected server instance.');
        }

        $customerClient = $this->customerClients->selectClient($this->selectedServer, $this->selectedCredentials);
        if (! $customerClient instanceof CustomerInterface) {
            throw new RuntimeException('Customer client should be an instance of CustomerInterface.');
        }

        return $customerClient->fetchCustomer($parameters);
    }

    public function getWebspaces(string $username): Result
    {
        if ($this->selectedServer === null) {
            throw new RuntimeException('I have no selected server instance.');
        }

        $hostingClient = $this->hostingClients->selectClient($this->selectedServer, $this->selectedCredentials);
        if (! $hostingClient instanceof HostingPackageInterface) {
            throw new RuntimeException('Hosting client should be an instance of HostingPackageInterface.');
        }

        return $hostingClient->getWebspaces($username);
    }

    public function getDomainList(string $customerLogin): CustomerGetDomainListResult
    {
        if ($this->selectedServer === null) {
            throw new RuntimeException('I have no selected server instance.');
        }

        $customerClient = $this->customerClients->selectClient($this->selectedServer, $this->selectedCredentials);
        if (! $customerClient instanceof CustomerInterface) {
            throw new RuntimeException('Customer client should be an instance of CustomerInterface.');
        }

        return $customerClient->getDomainList($customerLogin);
    }

    public function getHostingSite(HostingParameters $parameters): Result
    {
        if ($this->selectedServer === null) {
            throw new RuntimeException('I have no selected server instance.');
        }

        $hostingClient = $this->hostingClients->selectClient($this->selectedServer, $this->selectedCredentials);
        if (! $hostingClient instanceof HostingPackageInterface) {
            throw new RuntimeException('Hosting client should be an instance of HostingPackageInterface.');
        }

        return $hostingClient->getHostingSite($parameters);
    }

    public function createEmailForward(EmailForwardingCreateParameters $parameters): Result
    {
        if ($this->selectedServer === null) {
            throw new RuntimeException('I have no selected server instance.');
        }

        $hostingClient = $this->hostingClients->selectClient($this->selectedServer, $this->selectedCredentials);
        if (! $hostingClient instanceof HostingPackageInterface) {
            throw new RuntimeException('Hosting client should be an instance of HostingPackageInterface.');
        }

        return $hostingClient->createEmailForward($parameters);
    }

    public function getIpAddresses(): Result
    {
        if ($this->selectedServer === null) {
            throw new RuntimeException('I have no selected server instance.');
        }

        $hostingClient = $this->hostingClients->selectClient($this->selectedServer, $this->selectedCredentials);
        if (! $hostingClient instanceof HostingPackageInterface) {
            throw new RuntimeException('Hosting client should be an instance of HostingPackageInterface.');
        }

        return $hostingClient->getIpAddresses();
    }

    public function setEmailCatchAll(EmailSetCatchAllParameters $parameters): Result
    {
        if ($this->selectedServer === null) {
            throw new RuntimeException('I have no selected server instance.');
        }

        $hostingClient = $this->hostingClients->selectClient($this->selectedServer, $this->selectedCredentials);
        if (! $hostingClient instanceof HostingPackageInterface) {
            throw new RuntimeException('Hosting client should be an instance of HostingPackageInterface.');
        }

        return $hostingClient->setEmailCatchAll($parameters);
    }

    public function setFtpPassword(string $domain, string $user, string $password): Result
    {
        if ($this->selectedServer === null) {
            throw new RuntimeException('I have no selected server instance.');
        }

        $hostingClient = $this->hostingClients->selectClient($this->selectedServer, $this->selectedCredentials);
        if (! $hostingClient instanceof HostingPackageInterface) {
            throw new RuntimeException('Hosting client should be an instance of HostingPackageInterface.');
        }

        return $hostingClient->setFtpPassword($domain, $user, $password);
    }

    public function isDkimEnabled(string $domain): bool
    {
        if ($this->selectedServer === null) {
            throw new RuntimeException('I have no selected server instance.');
        }

        $hostingClient = $this->hostingClients->selectClient($this->selectedServer, $this->selectedCredentials);
        if (! $hostingClient instanceof HostingPackageInterface) {
            throw new RuntimeException('Hosting client should be an instance of HostingPackageInterface.');
        }

        return $hostingClient->isDkimEnabled($domain);
    }

    public function setDkim(bool $enable, string $domain): Result
    {
        if ($this->selectedServer === null) {
            throw new RuntimeException('I have no selected server instance.');
        }

        $hostingClient = $this->hostingClients->selectClient($this->selectedServer, $this->selectedCredentials);
        if (! $hostingClient instanceof HostingPackageInterface) {
            throw new RuntimeException('Hosting client should be an instance of HostingPackageInterface.');
        }

        return $hostingClient->setDkim($enable, $domain);
    }

    public function getDnsRecords(string $domain): DnsRecordsResult
    {
        if ($this->selectedServer === null) {
            throw new RuntimeException('I have no selected server instance.');
        }

        $hostingClient = $this->hostingClients->selectClient($this->selectedServer, $this->selectedCredentials);
        if (! $hostingClient instanceof HostingPackageInterface) {
            throw new RuntimeException('Hosting client should be an instance of HostingPackageInterface.');
        }

        return $hostingClient->getDnsRecords($domain);
    }

    public function disableDnsZone(string $domain): Result
    {
        if ($this->selectedServer === null) {
            throw new RuntimeException('I have no selected server instance.');
        }

        $hostingClient = $this->hostingClients->selectClient($this->selectedServer, $this->selectedCredentials);
        if (! $hostingClient instanceof HostingPackageInterface) {
            throw new RuntimeException('Hosting client should be an instance of HostingPackageInterface.');
        }

        return $hostingClient->disableDnsZone($domain);
    }

    public function changeServicePlanSwitchBetweenHostingType(HostingParameters $hostingParameters, string $domain, string $servicePlanGuid): Result
    {
        if ($this->selectedServer === null) {
            throw new RuntimeException('I have no selected server instance.');
        }

        $hostingClient = $this->hostingClients->selectClient($this->selectedServer, $this->selectedCredentials);
        if (! $hostingClient instanceof HostingPackageInterface) {
            throw new RuntimeException('Hosting client should be an instance of HostingPackageInterface.');
        }

        return $hostingClient->changeServicePlanSwitchBetweenHostingType($hostingParameters, $domain, $servicePlanGuid);
    }

    public function isServicePlanChangeable(string $domain, string $servicePlanGuuid): bool
    {
        if ($this->selectedServer === null) {
            throw new RuntimeException('I have no selected server instance.');
        }

        $hostingClient = $this->hostingClients->selectClient($this->selectedServer, $this->selectedCredentials);
        if (! $hostingClient instanceof HostingPackageInterface) {
            throw new RuntimeException('Hosting client should be an instance of HostingPackageInterface.');
        }

        return $hostingClient->isServicePlanChangeable($domain, $servicePlanGuuid);
    }

    /**
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function enable(ChangeHostingPackageStatusParameters $parameters): Result
    {
        if ($this->selectedServer === null) {
            throw new InvalidArgumentException('I have no selected server instance.');
        }

        $hostingClient = $this->hostingClients->selectClient($this->selectedServer, $this->selectedCredentials);
        if (! $hostingClient instanceof HostingPackageInterface) {
            throw new RuntimeException('Hosting client should be an instance of HostingPackageInterface.');
        }

        return $hostingClient->enable($parameters);
    }

    /**
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function disable(ChangeHostingPackageStatusParameters $parameters): Result
    {
        if ($this->selectedServer === null) {
            throw new InvalidArgumentException('I have no selected server instance.');
        }

        $hostingClient = $this->hostingClients->selectClient($this->selectedServer, $this->selectedCredentials);
        if (! $hostingClient instanceof HostingPackageInterface) {
            throw new RuntimeException('Hosting client should be an instance of HostingPackageInterface.');
        }

        return $hostingClient->disable($parameters);
    }

    /**
     * @return array<mixed>
     */
    public function getServicePlans(HostingParameters $parameters): array
    {
        if ($this->selectedServer === null) {
            throw new InvalidArgumentException('I have no selected server instance.');
        }

        $hostingClient = $this->hostingClients->selectClient($this->selectedServer, $this->selectedCredentials);
        if (! $hostingClient instanceof HostingPackageInterface) {
            throw new RuntimeException('Hosting client should be an instance of HostingPackageInterface.');
        }

        return $hostingClient->getServicePlans($parameters);
    }

    public function syncSubscription(string $domain): Result
    {
        if ($this->selectedServer === null) {
            throw new InvalidArgumentException('I have no selected server instance.');
        }

        $hostingClient = $this->hostingClients->selectClient($this->selectedServer, $this->selectedCredentials);
        if (! $hostingClient instanceof HostingPackageInterface) {
            throw new RuntimeException('Hosting client should be an instance of HostingPackageInterface.');
        }

        return $hostingClient->syncSubscription($domain);
    }

    public function getSiteIdByDomain(string $domain): int
    {
        if ($this->selectedServer === null) {
            throw new InvalidArgumentException('I have no selected server instance.');
        }
        $hostingClient = $this->hostingClients->selectClient($this->selectedServer, $this->selectedCredentials);
        if (! $hostingClient instanceof HostingPackageInterface) {
            throw new RuntimeException('Hosting client should be an instance of HostingPackageInterface.');
        }
        return $hostingClient->getSiteIdByDomain($domain);
    }

    public function getExistingEmailAccounts(EmailGetAccountSettingsParameters $parameters): EmailGetAccountSettingsResult
    {
        if ($this->selectedServer === null) {
            throw new InvalidArgumentException('I have no selected server instance.');
        }

        $hostingClient = $this->hostingClients->selectClient($this->selectedServer, $this->selectedCredentials);
        if (! $hostingClient instanceof HostingPackageInterface) {
            throw new RuntimeException('Hosting client should be an instance of HostingPackageInterface.');
        }

        return $hostingClient->getExistingEmailAccounts($parameters);
    }

    public function createSite(string $domain, int $webspaceId): CreateSiteResult
    {
        if ($this->selectedServer === null) {
            throw new RuntimeException('I have no selected server instance.');
        }

        $hostingClient = $this->hostingClients->selectClient($this->selectedServer, $this->selectedCredentials);
        if (! $hostingClient instanceof HostingPackageInterface) {
            throw new RuntimeException('Hosting client should be an instance of HostingPackageInterface.');
        }

        return $hostingClient->createSite($domain, $webspaceId);
    }

    public function removeSite(string $domain): RemoveSiteResult
    {
        if ($this->selectedServer === null) {
            throw new RuntimeException('I have no selected server instance.');
        }

        $hostingClient = $this->hostingClients->selectClient($this->selectedServer, $this->selectedCredentials);
        if (! $hostingClient instanceof HostingPackageInterface) {
            throw new RuntimeException('Hosting client should be an instance of HostingPackageInterface.');
        }

        return $hostingClient->removeSite($domain);
    }

    public function deleteEmailAccount(string $domain, string $emailAccount): EmailAccountDeleteResponse
    {
        if ($this->selectedServer === null) {
            throw new RuntimeException('I have no selected server instance.');
        }

        $hostingClient = $this->hostingClients->selectClient($this->selectedServer, $this->selectedCredentials);
        if (! $hostingClient instanceof HostingPackageInterface) {
            throw new RuntimeException('Hosting client should be an instance of HostingPackageInterface.');
        }

        return $hostingClient->deleteEmailAccount($domain, $emailAccount);
    }
}
