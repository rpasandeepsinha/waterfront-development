<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Fakers;

use GuzzleHttp\Psr7\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\ChangeHostingPackageStatus\Parameters as ChangeHostingPackageStatusParameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\DeleteWebsite\Parameters as WebsiteDeleteParameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\EmailForwardingCreate\Parameters as EmailForwardingCreateParameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\EmailGetAccountSettings\Parameters as EmailGetAccountSettingsParameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\EmailSetCatchAll\Parameters as EmailSetCatchAllParameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Parameters as HostingParameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Infra\PleskClient\Messages\ChangeHostingPackageStatus\Response as ChangeHostingPackageStatusResponse;
use Waterfront\Infra\PleskClient\Messages\ChangeServicePlan\ChangeServicePlanResponse;
use Waterfront\Infra\PleskClient\Messages\DnsDisableZone\DnsDisableZoneResponse;
use Waterfront\Infra\PleskClient\Messages\DnsGetRecords\DnsRecordsResponse;
use Waterfront\Infra\PleskClient\Messages\DnsGetRecords\DnsRecordsResult;
use Waterfront\Infra\PleskClient\Messages\EmailForwardingCreate\Response as EmailForwardCreateResponse;
use Waterfront\Infra\PleskClient\Messages\EmailGetAccountSettings\Response as EmailGetAccountSettingsResponse;
use Waterfront\Infra\PleskClient\Messages\EmailGetAccountSettings\Result as EmailGetAccountSettingsResult;
use Waterfront\Infra\PleskClient\Messages\EmailGetPreferences\Response as EmailGetPreferencesResponse;
use Waterfront\Infra\PleskClient\Messages\EmailGetPreferences\Result as EmailGetPreferencesResult;
use Waterfront\Infra\PleskClient\Messages\EmailSetCatchAll\Response as EmailSetCatchAllResponse;
use Waterfront\Infra\PleskClient\Messages\EmailSetDkim\Response as DkimResponse;
use Waterfront\Infra\PleskClient\Messages\HostingCreate\Response as HostingCreateResponse;
use Waterfront\Infra\PleskClient\Messages\HostingSiteFetch\Response as HostingFetchResponse;
use Waterfront\Infra\PleskClient\Messages\ServicePlanGet\Response as ServiceplanGetResponse;
use Waterfront\Infra\PleskClient\Messages\SyncSubscription\SyncSubscriptionResponse;
use Waterfront\Infra\PleskClient\Messages\WebsiteDelete\Response as WebsiteDeleteResponse;
use Waterfront\Infra\PleskClient\Services\HostingPackageClient;
use Waterfront\Infra\PleskClient\Traits\DesiredResponseCodeTrait;

class HostingPackageClientFaker extends HostingPackageClient
{
    use DesiredResponseCodeTrait;

    public function createHosting(HostingParameters $parameters): Result
    {
        $desiredResponseCode = $this->getDesiredResponseCode();
        if ($desiredResponseCode === 0) {
            $desiredResponseCode = Response::HTTP_OK;
        }

        $xmlMessage = (string) file_get_contents(__DIR__ . '/data/plesk_hosting_create_response.xml');
        $httpResponse = new HttpResponse($desiredResponseCode, [], $xmlMessage);

        return new HostingCreateResponse($httpResponse)->getResult();
    }

    public function setDkim(bool $enable, string $domain): Result
    {
        if ($this->getDesiredResponseCode() === 0) {
            $this->setDesiredResponseCode(Response::HTTP_OK);
        }

        $xmlMessage = (string) file_get_contents(__DIR__ . '/data/plesk_email_dkim_all_response.xml');
        $httpResponse = new HttpResponse($this->getDesiredResponseCode(), [], $xmlMessage);

        return new DkimResponse($httpResponse)->getResult();
    }

    public function getDnsRecords(string $domain): DnsRecordsResult
    {
        if ($this->getDesiredResponseCode() === 0) {
            $this->setDesiredResponseCode(Response::HTTP_OK);
        }

        $xmlMessage = (string) file_get_contents(__DIR__ . '/data/plesk_dns_get_records_response.xml');
        $httpResponse = new HttpResponse($this->getDesiredResponseCode(), [], $xmlMessage);

        return new DnsRecordsResponse($httpResponse)->getResult();
    }

    public function getWebspaces(string $username): Result
    {
        return new Result();
    }

    public function createMailOnlyHosting(HostingParameters $parameters): Result
    {
        $desiredResponseCode = $this->getDesiredResponseCode();
        if ($desiredResponseCode === 0) {
            $desiredResponseCode = Response::HTTP_OK;
        }

        $xmlMessage = (string) file_get_contents(__DIR__ . '/data/plesk_hosting_create_response.xml');
        $httpResponse = new HttpResponse($desiredResponseCode, [], $xmlMessage);

        return new HostingCreateResponse($httpResponse)->getResult();
    }

    public function changeServicePlan(string $domain, string $servicePlanGuuid): Result
    {
        $desiredResponseCode = $this->getDesiredResponseCode();
        if ($desiredResponseCode === 0) {
            $desiredResponseCode = Response::HTTP_OK;
        }

        $xmlMessage = (string) file_get_contents(__DIR__ . '/data/plesk_change_serviceplan_response.xml');
        $httpResponse = new HttpResponse($desiredResponseCode, [], $xmlMessage);

        return new ChangeServicePlanResponse($httpResponse)->getResult();
    }

    public function getServicePlanGuid(string $servicePlanGuuid): string
    {
        $desiredResponseCode = $this->getDesiredResponseCode();
        if ($desiredResponseCode === 0) {
            $desiredResponseCode = Response::HTTP_OK;
        }

        $xmlMessage = (string) file_get_contents(__DIR__ . '/data/plesk_serviceplan_get_response.xml');
        $httpResponse = new HttpResponse($desiredResponseCode, [], $xmlMessage);

        return new ServiceplanGetResponse($httpResponse)->getServicePlanGuid();
    }

    public function deleteWebsite(WebsiteDeleteParameters $parameters): Result
    {
        $desiredResponseCode = $this->getDesiredResponseCode();
        if ($desiredResponseCode === 0) {
            $desiredResponseCode = Response::HTTP_OK;
        }

        $xmlMessage = (string) file_get_contents(__DIR__ . '/data/plesk_website_delete_response.xml');
        $httpResponse = new HttpResponse($desiredResponseCode, [], $xmlMessage);

        return new WebsiteDeleteResponse($httpResponse)->getResult();
    }

    public function getHostingSite(HostingParameters $parameters): Result
    {
        $desiredResponseCode = $this->getDesiredResponseCode();
        if ($desiredResponseCode === 0) {
            $desiredResponseCode = Response::HTTP_OK;
        }

        $xmlMessage = (string) file_get_contents(__DIR__ . '/data/plesk_get_hosting_website_response.xml');
        $httpResponse = new HttpResponse($desiredResponseCode, [], $xmlMessage);

        return new HostingFetchResponse($httpResponse)->getResult();
    }

    public function createEmailForward(EmailForwardingCreateParameters $parameters): Result
    {
        $desiredResponseCode = $this->getDesiredResponseCode();
        if ($desiredResponseCode === 0) {
            $desiredResponseCode = Response::HTTP_OK;
        }

        $xmlMessage = (string) file_get_contents(__DIR__ . '/data/plesk_email_forwarding_create_response.xml');
        $httpResponse = new HttpResponse($desiredResponseCode, [], $xmlMessage);

        return new EmailForwardCreateResponse($httpResponse)->getResult();
    }

    public function setEmailCatchAll(EmailSetCatchAllParameters $parameters): Result
    {
        $desiredResponseCode = $this->getDesiredResponseCode();
        if ($desiredResponseCode === 0) {
            $desiredResponseCode = Response::HTTP_OK;
        }

        $xmlMessage = (string) file_get_contents(__DIR__ . '/data/plesk_email_set_catch_all_response.xml');
        $httpResponse = new HttpResponse($desiredResponseCode, [], $xmlMessage);

        return new EmailSetCatchAllResponse($httpResponse)->getResult();
    }

    public function getExistingEmailAccounts(EmailGetAccountSettingsParameters $parameters): EmailGetAccountSettingsResult
    {
        $desiredResponseCode = $this->getDesiredResponseCode();
        if ($desiredResponseCode === 0) {
            $desiredResponseCode = Response::HTTP_OK;
        }

        $xmlMessage = (string) file_get_contents(__DIR__ . '/data/plesk_email_get_account_settings_response.xml');
        $httpResponse = new HttpResponse($desiredResponseCode, [], $xmlMessage);

        return new EmailGetAccountSettingsResponse($httpResponse)->getResult();
    }

    public function getPreferencesEmail(EmailGetAccountSettingsParameters $parameters): EmailGetPreferencesResult
    {
        $desiredResponseCode = $this->getDesiredResponseCode();
        if ($desiredResponseCode === 0) {
            $desiredResponseCode = Response::HTTP_OK;
        }

        $xmlMessage = (string) file_get_contents(__DIR__ . '/data/plesk_email_get_preferences_response.xml');
        $httpResponse = new HttpResponse($desiredResponseCode, [], $xmlMessage);

        return new EmailGetPreferencesResponse($httpResponse)->getResult();
    }

    public function servicePlanExists(HostingParameters $parameters): bool
    {
        $desiredResponseCode = $this->getDesiredResponseCode();
        if ($desiredResponseCode === 0) {
            $desiredResponseCode = Response::HTTP_OK;
        }

        $xmlMessage = (string) file_get_contents(__DIR__ . '/data/plesk_serviceplan_get_response.xml');
        $httpResponse = new HttpResponse($desiredResponseCode, [], $xmlMessage);

        return new ServiceplanGetResponse($httpResponse)->getServicePlanGuid() !== '';
    }

    public function isServicePlanChangeable(string $domain, string $servicePlanGuuid): bool
    {
        $desiredResponseCode = $this->getDesiredResponseCode();
        if ($desiredResponseCode === 0) {
            $desiredResponseCode = Response::HTTP_OK;
        }

        $hostingParameters = new HostingParameters();
        $hostingParameters->setDomain($domain);
        $result = $this->getHostingSite($hostingParameters);
        $current = $result->getResponseBody();
        assert(is_array($current['site']));
        $currenHostingType = $current['site']['get']['result']['data']['gen_info']['htype'];

        $xmlMessage = (string) file_get_contents(__DIR__ . '/data/plesk_serviceplan_get_response.xml');
        $httpResponse = new HttpResponse($desiredResponseCode, [], $xmlMessage);

        $response = new ServiceplanGetResponse($httpResponse);
        $newHostingType = $response->getServicePlanHostingType();

        return $currenHostingType === 'vrt_hst' && $newHostingType === 'none';
    }

    public function enable(ChangeHostingPackageStatusParameters $parameters): Result
    {
        $desiredResponseCode = $this->getDesiredResponseCode();
        if ($desiredResponseCode === 0) {
            $desiredResponseCode = Response::HTTP_OK;
        }

        $xmlMessage = (string) file_get_contents(__DIR__ . '/data/plesk_enable_response.xml');
        $httpResponse = new HttpResponse($desiredResponseCode, [], $xmlMessage);

        return new ChangeHostingPackageStatusResponse($httpResponse)->getResult();
    }

    public function disable(ChangeHostingPackageStatusParameters $parameters): Result
    {
        $desiredResponseCode = $this->getDesiredResponseCode();
        if ($desiredResponseCode === 0) {
            $desiredResponseCode = Response::HTTP_OK;
        }

        $xmlMessage = (string) file_get_contents(__DIR__ . '/data/plesk_disable_response.xml');
        $httpResponse = new HttpResponse($desiredResponseCode, [], $xmlMessage);

        return new ChangeHostingPackageStatusResponse($httpResponse)->getResult();
    }

    public function disableDnsZone(string $domain): Result
    {
        $desiredResponseCode = $this->getDesiredResponseCode();
        if ($desiredResponseCode === 0) {
            $desiredResponseCode = Response::HTTP_OK;
        }

        $xmlMessage = (string) file_get_contents(__DIR__ . '/data/plesk_disable_dns.xml');
        $httpResponse = new HttpResponse($desiredResponseCode, [], $xmlMessage);

        return new DnsDisableZoneResponse($httpResponse)->getResult();
    }

    public function syncSubscription(string $domain): Result
    {
        $desiredResponseCode = $this->getDesiredResponseCode();
        if ($desiredResponseCode === 0) {
            $desiredResponseCode = Response::HTTP_OK;
        }

        $xmlMessage = (string) file_get_contents(__DIR__ . '/data/plesk_sync_subscription_response.xml');
        $httpResponse = new HttpResponse($desiredResponseCode, [], $xmlMessage);

        return new SyncSubscriptionResponse($httpResponse)->getResult();
    }
}
