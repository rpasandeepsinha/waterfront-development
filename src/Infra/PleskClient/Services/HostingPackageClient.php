<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Services;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use SensitiveParameter;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Domain\Hosting\Interfaces\Hosting\HostingPackageInterface;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\ChangeHostingPackageStatus\Parameters as ChangeHostingPackageStatusParameters
;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\DeleteWebsite\Parameters as WebsiteDeleteParameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\EmailForwardingCreate\Parameters as EmailForwardingCreateParameters
;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\EmailGetAccountSettings\Parameters as EmailGetAccountSettingsParameters
;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\EmailSetCatchAll\Parameters as EmailSetCatchAllParameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Parameters as HostingParameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Infra\PleskClient\DTO\DnsRecord;
use Waterfront\Infra\PleskClient\Enums\HostingPackageStatus;
use Waterfront\Infra\PleskClient\Exceptions\PleskClientException;
use Waterfront\Infra\PleskClient\Exceptions\PleskLackOffResourceException;
use Waterfront\Infra\PleskClient\Messages\BaseResponse;
use Waterfront\Infra\PleskClient\Messages\ChangeHostingPackageStatus\Request as ChangeHostingPackageStatusRequest;
use Waterfront\Infra\PleskClient\Messages\ChangeHostingPackageStatus\Response as ChangeHostingPackageStatusResponse;
use Waterfront\Infra\PleskClient\Messages\ChangeServicePlan\ChangeServicePlanRequest;
use Waterfront\Infra\PleskClient\Messages\ChangeServicePlan\ChangeServicePlanResponse;
use Waterfront\Infra\PleskClient\Messages\CreateSite\CreateSiteRequest;
use Waterfront\Infra\PleskClient\Messages\CreateSite\CreateSiteResponse;
use Waterfront\Infra\PleskClient\Messages\CreateSite\CreateSiteResult;
use Waterfront\Infra\PleskClient\Messages\DnsDisableZone\DnsDisableZoneRequest;
use Waterfront\Infra\PleskClient\Messages\DnsDisableZone\DnsDisableZoneResponse;
use Waterfront\Infra\PleskClient\Messages\DnsGetRecords\DnsRecordsRequest;
use Waterfront\Infra\PleskClient\Messages\DnsGetRecords\DnsRecordsResponse;
use Waterfront\Infra\PleskClient\Messages\DnsGetRecords\DnsRecordsResult;
use Waterfront\Infra\PleskClient\Messages\EmailAccountCreate\EmailAccountCreateRequest;
use Waterfront\Infra\PleskClient\Messages\EmailAccountCreate\EmailAccountCreateResponse;
use Waterfront\Infra\PleskClient\Messages\EmailAccountDelete\EmailAccountDeleteRequest;
use Waterfront\Infra\PleskClient\Messages\EmailAccountDelete\EmailAccountDeleteResponse;
use Waterfront\Infra\PleskClient\Messages\EmailForwardingCreate\Request as EmailForwardCreateRequest;
use Waterfront\Infra\PleskClient\Messages\EmailForwardingCreate\Response as EmailForwardCreateResponse;
use Waterfront\Infra\PleskClient\Messages\EmailGetAccountSettings\Request as EmailGetAccountSettingsRequest;
use Waterfront\Infra\PleskClient\Messages\EmailGetAccountSettings\Response as EmailGetAccountSettingsResponse;
use Waterfront\Infra\PleskClient\Messages\EmailGetAccountSettings\Result as EmailGetAccountSettingsResult;
use Waterfront\Infra\PleskClient\Messages\EmailGetPreferences\Request as EmailGetPreferencesRequest;
use Waterfront\Infra\PleskClient\Messages\EmailGetPreferences\Response as EmailGetPreferencesResponse;
use Waterfront\Infra\PleskClient\Messages\EmailGetPreferences\Result as EmailGetPreferencesResult;
use Waterfront\Infra\PleskClient\Messages\EmailPasswordReset\EmailPasswordResetRequest;
use Waterfront\Infra\PleskClient\Messages\EmailPasswordReset\EmailPasswordResetResponse;
use Waterfront\Infra\PleskClient\Messages\EmailSetCatchAll\Request as EmailSetCatchAllRequest;
use Waterfront\Infra\PleskClient\Messages\EmailSetCatchAll\Response as EmailSetCatchAllResponse;
use Waterfront\Infra\PleskClient\Messages\EmailSetDkim\Request as EmailSetDkimRequest;
use Waterfront\Infra\PleskClient\Messages\EmailSetDkim\Response as EmailSetDkimResponse;
use Waterfront\Infra\PleskClient\Messages\FtpSetPassword\FtpSetPasswordRequest;
use Waterfront\Infra\PleskClient\Messages\FtpSetPassword\FtpSetPasswordResponse;
use Waterfront\Infra\PleskClient\Messages\HostingCreate\MailOnlyRequest;
use Waterfront\Infra\PleskClient\Messages\HostingCreate\Request as HostingCreateRequest;
use Waterfront\Infra\PleskClient\Messages\HostingCreate\Response as HostingCreateResponse;
use Waterfront\Infra\PleskClient\Messages\HostingSiteFetch\Request as HostingFetchRequest;
use Waterfront\Infra\PleskClient\Messages\HostingSiteFetch\Response as HostingFetchResponse;
use Waterfront\Infra\PleskClient\Messages\IpAddressesGet\Request as IpAddressesRequest;
use Waterfront\Infra\PleskClient\Messages\IpAddressesGet\Response as IpAddressesResponse;
use Waterfront\Infra\PleskClient\Messages\RemoveSite\RemoveSiteRequest;
use Waterfront\Infra\PleskClient\Messages\RemoveSite\RemoveSiteResponse;
use Waterfront\Infra\PleskClient\Messages\RemoveSite\RemoveSiteResult;
use Waterfront\Infra\PleskClient\Messages\ServicePlanGet\GuidRequest as ServicePlanGuidGetRequest;
use Waterfront\Infra\PleskClient\Messages\ServicePlanGet\Request as ServicePlanGetRequest;
use Waterfront\Infra\PleskClient\Messages\ServicePlanGet\Response as ServicePlanGetResponse;
use Waterfront\Infra\PleskClient\Messages\ServicePlanIndex\Request as ServicePlanIndexRequest;
use Waterfront\Infra\PleskClient\Messages\ServicePlanIndex\Response as ServicePlanIndexResponse;
use Waterfront\Infra\PleskClient\Messages\SyncSubscription\SyncSubscriptionRequest;
use Waterfront\Infra\PleskClient\Messages\SyncSubscription\SyncSubscriptionResponse;
use Waterfront\Infra\PleskClient\Messages\WebsiteDelete\Request as WebsiteDeleteRequest;
use Waterfront\Infra\PleskClient\Messages\WebsiteDelete\Response as WebsiteDeleteResponse;
use Waterfront\Infra\PleskClient\Messages\WebspaceGet\Request as WebspaceGetRequest;
use Waterfront\Infra\PleskClient\Messages\WebspaceGet\Response as WebspaceGetResponse;
use Waterfront\Infra\PleskClient\PleskClient;
use Waterfront\Infra\PleskClient\Traits\PleskServerTrait;
use Waterfront\Support\Enums\LoggingContextKeys;

class HostingPackageClient extends PleskClient implements HostingPackageInterface
{
    use PleskServerTrait;

    /**
     * Set a specific target server for Plesk.
     *
     * @param array<string>|null $credentials
     */
    public function setServer(Server $server, #[SensitiveParameter] ?array $credentials = null): bool
    {
        return parent::setServer($server, $credentials);
    }

    /**
     * @throws Exception
     * @throws GuzzleException
     */
    public function createHosting(HostingParameters $parameters): Result
    {
        $this->logger->info('Create new hosting', [
            LoggingContextKeys::META => [
                'parameters' => $parameters->toArray(),
            ],
        ]);

        if ($parameters->getMailOnlyHosting()) {
            return $this->createMailOnlyHosting($parameters);
        }

        $request = new HostingCreateRequest($parameters);
        $httpResponse = $this->send($request);
        $response = new HostingCreateResponse($httpResponse);

        if ($response->getStatusCode() === Response::HTTP_OK && $response->getStatus() === BaseResponse::STATUS_OK) {
            $this->logger->info('New hosting created successfully', [
                LoggingContextKeys::RESPONSE_DATA => (string) json_encode($response->getResult()->toArray()),
            ]);
        }

        return $response->getResult();
    }

    public function getIpAddresses(): Result
    {
        $request = new IpAddressesRequest();
        $httpResponse = $this->send($request);
        $response = new IpAddressesResponse($httpResponse);

        return $response->getResult();
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     */
    public function createMailOnlyHosting(HostingParameters $parameters): Result
    {
        $request = new MailOnlyRequest($parameters);
        $httpResponse = $this->send($request);
        $response = new HostingCreateResponse($httpResponse);

        if ($response->getStatusCode() === Response::HTTP_OK && $response->getStatus() === BaseResponse::STATUS_OK) {
            $this->logger->info('New mail only hosting created successfully', [
                LoggingContextKeys::RESPONSE_DATA => (string) json_encode($response->getResult()->toArray()),
            ]);
        }

        return $response->getResult();
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     */
    public function getHostingSite(HostingParameters $parameters): Result
    {
        $request = new HostingFetchRequest($parameters);
        $httpResponse = $this->send($request);
        $response = new HostingFetchResponse($httpResponse);

        return $response->getResult();
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     */
    public function getWebspaces(string $username): Result
    {
        $request = new WebspaceGetRequest($username);
        $httpResponse = $this->send($request);
        $response = new WebspaceGetResponse($httpResponse);

        return $response->getResult();
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     */
    public function deleteWebsite(WebsiteDeleteParameters $parameters): Result
    {
        $request = new WebsiteDeleteRequest($parameters);
        $httpResponse = $this->send($request);
        $response = new WebsiteDeleteResponse($httpResponse);

        return $response->getResult();
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     *
     * @return array<int, string>
     *
     */
    public function getServicePlans(HostingParameters $parameters): array
    {
        $request = new ServicePlanIndexRequest();
        $httpResponse = $this->send($request);
        $response = new ServicePlanIndexResponse($httpResponse);

        if ($response->getStatusCode() === Response::HTTP_OK && $response->getStatus() === BaseResponse::STATUS_ERROR) {
            $this->logger->error('Service plan index request failed', [
                LoggingContextKeys::RESPONSE_DATA => (string) json_encode($response->getResult()->toArray()),
            ]);
        }

        return $response->plans;
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     */
    public function servicePlanExists(HostingParameters $parameters): bool
    {
        $request = new ServicePlanGetRequest($parameters);
        $httpResponse = $this->send($request);
        $response = new ServicePlanGetResponse($httpResponse);

        if ($response->getStatusCode() === Response::HTTP_OK && $response->getStatus() === BaseResponse::STATUS_ERROR) {
            $this->logger->error('Service plan exists error', [
                LoggingContextKeys::REQUEST_DATA => (string) json_encode($parameters->toArray()),
                LoggingContextKeys::RESPONSE_DATA => (string) json_encode($response->getResult()->toArray()),
            ]);

            return false;
        }

        return true;
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     */
    public function getServicePlanByGuid(HostingParameters $parameters): string
    {
        $request = new ServicePlanGuidGetRequest($parameters);
        $httpResponse = $this->send($request);

        return new ServicePlanGetResponse($httpResponse)->getServicePlanName();
    }

    /**
     * This method check if it is possible to change one servicplan to another. based on the htype
     * For now this is enough, For now that is enough because it mainly concerns the ESM migration,
     * later an extensive check on resources can be made here.
     *
     * todo :
     *  - Refactor this a bit after refactoring the response & result structure. So we dont't have the use a array structure here.
     *  - Ticket : https://yh-jira.atlassian.net/browse/WATER-3877
     *
     * @throws JsonException
     * @throws GuzzleException
     */
    public function isServicePlanChangeable(string $domain, string $servicePlanGuuid): bool
    {
        //Get current Serviceplan
        $hostingParameters = new HostingParameters();
        $hostingParameters->setDomain($domain);
        $result = $this->getHostingSite($hostingParameters);
        $current = $result->getResponseBody();
        assert(is_array($current['site']));

        if (! array_key_exists('data', $current['site']['get']['result'])) {
            $this->logger->error('Missing key data ', [
                LoggingContextKeys::RESPONSE_DATA => $result->getResponseResult(),
            ]);

            return false;
        }

        $currenHostingType = $current['site']['get']['result']['data']['gen_info']['htype'];

        //Get desired serviceplan
        $hostingParameters = new HostingParameters();
        $hostingParameters->setPackage($servicePlanGuuid);

        $request = new ServicePlanGetRequest($hostingParameters);
        $httpResponse = $this->send($request);
        $response = new ServicePlanGetResponse($httpResponse);

        $newHostingType = $response->getServicePlanHostingType();

        if ($currenHostingType === 'vrt_hst' && $newHostingType === 'none') {
            return false;
        }

        return true;
    }

    /**
     * @throws PleskLackOffResourceException
     * @throws GuzzleException
     * @throws PleskClientException
     * @throws JsonException
     */
    public function changeServicePlan(string $domain, string $servicePlanGuuid): Result
    {
        $servicePlanGuuid = $this->getServicePlanGuid($servicePlanGuuid);

        $this->logger->info('Switching between serviceplans', [
            LoggingContextKeys::META => [
                'domain' => $domain,
                'servicePlanGuuid' => $servicePlanGuuid,
            ],
        ]);

        $request = new ChangeServicePlanRequest($domain, $servicePlanGuuid);
        $httpResponse = $this->send($request);
        $response = new ChangeServicePlanResponse($httpResponse);

        if ($response->getStatusCode() === Response::HTTP_OK && $response->getStatus() === BaseResponse::STATUS_OK) {
            $this->logger->info('Serviceplan successful changed', [
                LoggingContextKeys::RESPONSE_DATA => (string) json_encode($response->getResult()->toArray()),
            ]);
        }

        if (
            $response->getStatusCode() === Response::HTTP_OK
            && $response->getStatus() === BaseResponse::STATUS_ERROR
            && $response->getResult()->getErrorCode() === 1023
        ) {
            $this->logger->info('There are not enough resources to make the change', [
                LoggingContextKeys::RESPONSE_DATA => (string) json_encode($response->getResult()->toArray()),
            ]);

            throw new PleskLackOffResourceException(
                serviceplan: $servicePlanGuuid,
                domain: $domain,
                pleskError: (string) $response->getResult()->getErrorMessage(),
            );
        }

        if (
            $response->getStatusCode() === Response::HTTP_OK
            && $response->getStatus() === BaseResponse::STATUS_ERROR
            && $response->getResult()->getErrorCode() === 1013
        ) {
            $this->logger->info('There was an error while changing the Serviceplan', [
                LoggingContextKeys::RESPONSE_DATA => (string) json_encode($response->getResult()->toArray()),
            ]);

            throw PleskClientException::pleskApiException(
                errorCode: $response->getResult()->getErrorCode(),
                ErrorMessage: (string) $response->getResult()->getErrorMessage(),
            );
        }

        return $response->getResult();
    }

    /**
     * @throws PleskClientException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function changeServicePlanSwitchBetweenHostingType(
        HostingParameters $hostingParameters,
        string $domain,
        string $servicePlanGuuid,
    ): Result {
        $this->logger->info(sprintf(
            'Switching serviceplan for domain %s from : %s to %s',
            $hostingParameters->getDomain(),
            $hostingParameters->getPackage(),
            $servicePlanGuuid,
        ));

        $siteId = $this->getSiteIdByDomain($hostingParameters->getDomain());

        //Get mail accounts
        $emailGetAccountSettings = EmailGetAccountSettingsParameters::create([
            'siteId' => $siteId,
        ]);

        $emailResult = $this->getExistingEmailAccounts($emailGetAccountSettings);
        $emailAccounts = $emailResult->getEmailAccounts();

        //For now we support only forwarded emailaccounts, so lets check
        foreach ($emailAccounts as $emailAccount) {
            if ($emailAccount->isForwarding() === false) {
                throw PleskClientException::unSupportedMailboxTypeException($emailAccount->getMailName());
            }
        }

        //Delete website / hosting
        $deleteWebsiteParameters = WebsiteDeleteParameters::create([
            'domain' => $hostingParameters->getDomain(),
        ]);

        $this->logger->info(sprintf(
            'Start deleting domain %s',
            $hostingParameters->getDomain(),
        ));
        $this->deleteWebsite($deleteWebsiteParameters);

        $this->logger->info(sprintf(
            'Hosting for domain %s has been deleted, start creating new hosting',
            $hostingParameters->getDomain(),
        ));

        //Create new mailonly hosting
        $hostingParameters->setMailOnlyHosting(true);
        $hostingParameters->setPackage($servicePlanGuuid);
        $this->createHosting($hostingParameters);

        $this->logger->info(sprintf(
            'New hosting for domain %s has been created, start creating email forwards',
            $hostingParameters->getDomain(),
        ));
        //Add the email accounts

        //Catchall
        if ($emailResult->getCatchAllForward() !== null) {
            $emailCatchAllParameters = EmailSetCatchAllParameters::create([
                'domain' => $hostingParameters->getDomain(),
                'destinationEmailAddress' => $emailResult->getCatchAllForward(),
            ]);

            $this->setEmailCatchAll($emailCatchAllParameters);

            $this->logger->info(sprintf(
                'Imported catchAll account with forward to : %s',
                $emailResult->getCatchAllForward(),
            ));
        } else {
            $this->logger->info(sprintf(
                'There was no catchAll set for domain %s',
                $hostingParameters->getDomain(),
            ));
        }

        //Forwarding accounts
        $foundEmailAccounts = count($emailAccounts);
        if ($foundEmailAccounts > 0) {
            $this->logger->info(sprintf(
                'Importing %d forwarding email accounts',
                $foundEmailAccounts,
            ));
        }

        foreach ($emailAccounts as $emailAccount) {
            if ($emailAccount->isForwarding()) {
                $emailForwardParameters = EmailForwardingCreateParameters::create([
                    'domain' => $hostingParameters->getDomain(),
                    'sourceEmailAddressUsername' => $emailAccount->getMailName(),
                    'destinationEmailAddresses' => $emailAccount->getForwardDestinationAddresses(),
                ]);

                $this->createEmailForward($emailForwardParameters);
                $this->logger->info(sprintf(
                    'Emailaccount %s with forwading to %s is imported',
                    $emailAccount->getMailName(),
                    json_encode($emailAccount->getForwardDestinationAddresses(), JSON_THROW_ON_ERROR),
                ));
            }
        }

        $result = new Result();
        $result->setStatus(Result::STATUS_OK);

        return $result;
    }

    /**
     * @throws PleskClientException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function getExistingEmailAccounts(EmailGetAccountSettingsParameters $parameters): EmailGetAccountSettingsResult
    {
        $request = new EmailGetAccountSettingsRequest($parameters);
        $httpResponse = $this->send($request);
        $response = new EmailGetAccountSettingsResponse($httpResponse);

        if ($response->getStatusCode() === Response::HTTP_OK && $response->getStatus() === BaseResponse::STATUS_ERROR) {
            throw PleskClientException::pleskApiException(
                errorCode: (int) $response->getResult()->getErrorCode(),
                ErrorMessage: (string) $response->getResult()->getErrorMessage(),
            );
        }

        $catchAllResponseResult = $this->getPreferencesEmail($parameters);

        $responseResult = $response->getResult();

        if ($catchAllResponseResult->isCatchAllSet()) {
            $responseResult->addCatchAllForward($catchAllResponseResult->getCatchAllForward());
        }

        return $responseResult;
    }

    /**
     * @throws GuzzleException
     * @throws PleskClientException
     * @throws JsonException
     */
    public function getPreferencesEmail(EmailGetAccountSettingsParameters $parameters): EmailGetPreferencesResult
    {
        $request = new EmailGetPreferencesRequest($parameters);
        $httpResponse = $this->send($request);
        $response = new EmailGetPreferencesResponse($httpResponse);

        if ($response->getStatusCode() === Response::HTTP_OK && $response->getStatus() === BaseResponse::STATUS_ERROR) {
            throw PleskClientException::pleskApiException(
                errorCode: (int) $response->getResult()->getErrorCode(),
                ErrorMessage: (string) $response->getResult()->getErrorMessage(),
            );
        }

        return $response->getResult();
    }

    /**
     * @throws GuzzleException
     * @throws PleskClientException
     * @throws JsonException
     */
    public function getDnsRecords(string $domain): DnsRecordsResult
    {
        $siteId = $this->getSiteIdByDomain($domain);

        $request = new DnsRecordsRequest($siteId);
        $httpResponse = $this->send($request);
        $response = new DnsRecordsResponse($httpResponse);

        return $response->getResult();
    }

    /**
     * @throws GuzzleException
     * @throws PleskClientException
     * @throws JsonException
     */
    public function getDkimRecord(string $domain): ?DnsRecord
    {
        $records = $this->getDnsRecords($domain)->records;

        $dkimRecords = array_filter(
            $records,
            fn (DnsRecord $record) => $record->type === 'TXT' && str_contains($record->value, 'v=DKIM1'),
        );

        if (count($dkimRecords) === 0) {
            return null;
        }

        if (count($dkimRecords) > 1) {
            $this->logger->warning('Multiple DKIM records found for {domain.name}, returning first.', [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::META => [
                    'dkim_records' => $dkimRecords,
                    'all_records' => $records,
                ],
            ]);
        }

        return $dkimRecords[0];
    }

    /**
     * @throws GuzzleException
     * @throws PleskClientException
     * @throws JsonException
     */
    public function getServicePlanGuid(string $servicePlanGuuid): string
    {
        $hostingParameters = new HostingParameters();
        $hostingParameters->setPackage($servicePlanGuuid);

        $request = new ServicePlanGetRequest($hostingParameters);
        $httpResponse = $this->send($request);
        $response = new ServicePlanGetResponse($httpResponse);

        if ($response->getStatusCode() === Response::HTTP_OK && $response->getStatus() === BaseResponse::STATUS_ERROR) {
            throw PleskClientException::ServicePlanNotExistsException($servicePlanGuuid);
        }

        return $response->getServicePlanGuid();
    }

    /**
     * @throws PleskClientException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function createEmailAccount(
        string $domain,
        string $emailAccount,
        string $password,
    ): EmailAccountCreateResponse {
        $httpResponse = $this->send(new EmailAccountCreateRequest(
            siteId: $this->getSiteIdByDomain($domain),
            emailAccount: $emailAccount,
            password: $password,
        ));

        return new EmailAccountCreateResponse($httpResponse);
    }

    /**
     * @throws PleskClientException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function deleteEmailAccount(string $domain, string $emailAccount): EmailAccountDeleteResponse
    {
        $httpResponse = $this->send(new EmailAccountDeleteRequest(
            siteId: $this->getSiteIdByDomain($domain),
            emailAccount: $emailAccount,
        ));

        return new EmailAccountDeleteResponse($httpResponse);
    }

    /**
     * @throws PleskClientException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function resetEmailPassword(
        string $domain,
        string $emailAccount,
        string $password,
    ): EmailPasswordResetResponse {
        $httpResponse = $this->send(new EmailPasswordResetRequest(
            siteId: $this->getSiteIdByDomain($domain),
            emailAccount: $emailAccount,
            password: $password,
        ));

        return new EmailPasswordResetResponse($httpResponse);
    }

    /**
     * @throws GuzzleException
     * @throws PleskClientException
     * @throws JsonException
     */
    public function createEmailForward(EmailForwardingCreateParameters $parameters): Result
    {
        $siteId = $this->getSiteIdByDomain($parameters->getDomain());

        $request = new EmailForwardCreateRequest(
            $siteId,
            $parameters->getSourceEmailAddressUsername(),
            $parameters->getDestinationEmailAddresses(),
        );
        $httpResponse = $this->send($request);
        $response = new EmailForwardCreateResponse($httpResponse);

        return $response->getResult();
    }

    /**
     * @throws GuzzleException
     * @throws PleskClientException
     * @throws JsonException
     */
    public function setEmailCatchAll(EmailSetCatchAllParameters $parameters): Result
    {
        $siteId = $this->getSiteIdByDomain($parameters->getDomain());

        $request = new EmailSetCatchAllRequest($siteId, $parameters->getDestinationEmailAddress());
        $httpResponse = $this->send($request);
        $response = new EmailSetCatchAllResponse($httpResponse);

        return $response->getResult();
    }

    public function isDkimEnabled(string $domain): bool
    {
        $siteId = $this->getSiteIdByDomain($domain);
        $parameters = EmailGetAccountSettingsParameters::create(['siteId' => $siteId]);
        $result = $this->getPreferencesEmail($parameters);

        return $result->spamProtectSignEnabled;
    }

    /**
     * @throws GuzzleException
     * @throws PleskClientException
     * @throws JsonException
     */
    public function setDkim(bool $enable, string $domain): Result
    {
        $siteId = $this->getSiteIdByDomain($domain);

        $request = new EmailSetDkimRequest($siteId, $enable);
        $httpResponse = $this->send($request);
        $response = new EmailSetDkimResponse($httpResponse);

        return $response->getResult();
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     */
    public function enable(ChangeHostingPackageStatusParameters $parameters): Result
    {
        $request = new ChangeHostingPackageStatusRequest($parameters->getDomain(), HostingPackageStatus::ENABLED);
        $httpResponse = $this->send($request);
        $response = new ChangeHostingPackageStatusResponse($httpResponse);

        return $response->getResult();
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     */
    public function disable(ChangeHostingPackageStatusParameters $parameters): Result
    {
        $request = new ChangeHostingPackageStatusRequest(
            $parameters->getDomain(),
            HostingPackageStatus::DISABLED_BY_PLESK_ADMIN,
        );
        $httpResponse = $this->send($request);
        $response = new ChangeHostingPackageStatusResponse($httpResponse);

        return $response->getResult();
    }

    /**
     * @throws GuzzleException
     * @throws PleskClientException
     * @throws JsonException
     */
    public function disableDnsZone(string $domain): Result
    {
        $siteId = $this->getSiteIdByDomain($domain);

        $request = new DnsDisableZoneRequest($siteId);
        $httpResponse = $this->send($request);
        $response = new DnsDisableZoneResponse($httpResponse);

        return $response->getResult();
    }

    /**
     * @return array<mixed>
     */
    public function getServicePlan(HostingParameters $parameters): array
    {
        $request = new ServicePlanGetRequest($parameters);
        $httpResponse = $this->send($request);
        $response = new ServicePlanGetResponse($httpResponse);

        if ($response->getStatusCode() === Response::HTTP_OK && $response->getStatus() === BaseResponse::STATUS_ERROR) {
            throw PleskClientException::ServicePlanNotExistsException($parameters->getPackage() ?? 'unknown');
        }

        return $response->plan;
    }

    /**
     * @throws PleskClientException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function syncSubscription(string $domain): Result
    {
        $request = new SyncSubscriptionRequest($domain);
        $httpResponse = $this->send($request);
        $response = new SyncSubscriptionResponse($httpResponse);

        return $response->getResult();
    }

    /**
     * @throws GuzzleException
     * @throws PleskClientException
     * @throws JsonException
     */
    public function setFtpPassword(string $domain, string $user, string $password): Result
    {
        $request = new FtpSetPasswordRequest($domain, $user, $password);
        $httpResponse = $this->send($request);
        $response = new FtpSetPasswordResponse($httpResponse);

        return $response->getResult();
    }

    /**
     *
     * @throws PleskClientException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function getSiteIdByDomain(string $domain): int
    {
        $hostingParameters = HostingParameters::create([
            'domain' => $domain,
            'contactPersonName' => '',
            'emailAddress' => '',
            'ipv4Address' => '',
        ]);
        $siteResult = $this->getHostingSite($hostingParameters);
        if ($siteResult->getStatus() !== BaseResponse::STATUS_OK) {
            throw PleskClientException::pleskApiException(
                $siteResult->getErrorCode() ?? 0,
                $siteResult->getErrorMessage() ?? '',
            );
        }

        $responseBody = $siteResult->getResponseBody();
        assert(is_array($responseBody['site']));
        $id = $responseBody['site']['get']['result']['id'];
        assert(is_int($id) || is_string($id) || is_float($id) || is_bool($id) || is_null($id));

        return intval($id);
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     */
    public function createSite(string $domain, int $webspaceId): CreateSiteResult
    {
        $request = new CreateSiteRequest($domain, $webspaceId);
        $httpResponse = $this->send($request);
        $response = new CreateSiteResponse($httpResponse);

        return $response->getResult();
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     */
    public function removeSite(string $domain): RemoveSiteResult
    {
        $request = new RemoveSiteRequest($domain);
        $httpResponse = $this->send($request);
        $response = new RemoveSiteResponse($httpResponse);

        return $response->getResult();
    }
}
