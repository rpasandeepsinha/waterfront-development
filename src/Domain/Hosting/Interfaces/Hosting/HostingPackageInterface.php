<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Interfaces\Hosting;

use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\ChangeHostingPackageStatus\Parameters as ChangeHostingPackageStatusParameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\DeleteWebsite\Parameters as WebsiteDeleteParameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\EmailForwardingCreate\Parameters as EmailForwardingCreateParameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\EmailGetAccountSettings\Parameters as EmailGetAccountSettingsParameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\EmailSetCatchAll\Parameters as EmailSetCatchAllParameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Parameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Parameters as HostingParameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Infra\PleskClient\Messages\CreateSite\CreateSiteResult;
use Waterfront\Infra\PleskClient\Messages\DnsGetRecords\DnsRecordsResult;
use Waterfront\Infra\PleskClient\Messages\EmailAccountCreate\EmailAccountCreateResponse;
use Waterfront\Infra\PleskClient\Messages\EmailAccountDelete\EmailAccountDeleteResponse;
use Waterfront\Infra\PleskClient\Messages\EmailGetAccountSettings\Result as EmailGetAccountSettingsResult;
use Waterfront\Infra\PleskClient\Messages\EmailPasswordReset\EmailPasswordResetResponse;
use Waterfront\Infra\PleskClient\Messages\RemoveSite\RemoveSiteResult;

interface HostingPackageInterface extends ClientInterface
{
    public function createHosting(Parameters $parameters): Result;

    public function createEmailAccount(string $domain, string $emailAccount, string $password): EmailAccountCreateResponse;

    public function deleteEmailAccount(string $domain, string $emailAccount): EmailAccountDeleteResponse;

    public function getHostingSite(HostingParameters $parameters): Result;

    public function getWebspaces(string $username): Result;

    public function getServicePlanByGuid(HostingParameters $parameters): string;

    public function changeServicePlan(string $domain, string $servicePlanGuuid): Result;

    public function getIpAddresses(): Result;

    public function isDkimEnabled(string $domain): bool;

    public function setDkim(bool $enable, string $domain): Result;

    public function getDnsRecords(string $domain): DnsRecordsResult;

    public function servicePlanExists(HostingParameters $parameters): bool;

    /**
     * @return array<mixed>
     */
    public function getServicePlan(HostingParameters $parameters): array;

    /**
     * @return array<mixed>
     */
    public function getServicePlans(HostingParameters $parameters): array;

    public function deleteWebsite(WebsiteDeleteParameters $parameters): Result;

    public function createEmailForward(EmailForwardingCreateParameters $parameters): Result;

    public function setEmailCatchAll(EmailSetCatchAllParameters $parameters): Result;

    public function changeServicePlanSwitchBetweenHostingType(HostingParameters $hostingParameters, string $domain, string $servicePlanGuuid): Result;

    public function isServicePlanChangeable(string $domain, string $servicePlanGuuid): bool;

    public function enable(ChangeHostingPackageStatusParameters $parameters): Result;

    public function disable(ChangeHostingPackageStatusParameters $parameters): Result;

    public function disableDnsZone(string $domain): Result;

    public function syncSubscription(string $domain): Result;

    public function setFtpPassword(string $domain, string $user, string $password): Result;

    public function getSiteIdByDomain(string $domain): int;

    public function getExistingEmailAccounts(EmailGetAccountSettingsParameters $parameters): EmailGetAccountSettingsResult;

    public function createSite(string $domain, int $webspaceId): CreateSiteResult;

    public function removeSite(string $domain): RemoveSiteResult;

    public function resetEmailPassword(string $domain, string $emailAccount, string $password): EmailPasswordResetResponse;
}
