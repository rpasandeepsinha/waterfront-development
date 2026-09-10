<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenSrsClient;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\Domains\DTO\CheckResult;
use Waterfront\Domain\Domains\DTO\ModifyParameters;
use Waterfront\Domain\Domains\DTO\RegistrationParameters;
use Waterfront\Domain\Domains\DTO\RegistrationResult;
use Waterfront\Domain\Domains\DTO\RetrieveResult;
use Waterfront\Domain\Domains\DTO\TransferParameters;
use Waterfront\Domain\Domains\DTO\TransferResult;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Infra\OpenSrsClient\Exceptions\OpenSrsResultException;
use Waterfront\Infra\OpenSrsClient\Interfaces\OpenSrsConnectionInterface;
use Waterfront\Infra\OpenSrsClient\Messages\DomainGetRequest;
use Waterfront\Infra\OpenSrsClient\Messages\DomainGetResponse;
use Waterfront\Infra\OpenSrsClient\Messages\DomainLookupRequest;
use Waterfront\Infra\OpenSrsClient\Messages\DomainLookupResponse;
use Waterfront\Infra\OpenSrsClient\Messages\DomainModifyRequest;
use Waterfront\Infra\OpenSrsClient\Messages\DomainModifyResponse;
use Waterfront\Infra\OpenSrsClient\Messages\DomainNameserverUpdateRequest;
use Waterfront\Infra\OpenSrsClient\Messages\DomainRegistrationRequest;
use Waterfront\Infra\OpenSrsClient\Messages\DomainRegistrationResponse;
use Waterfront\Infra\OpenSrsClient\Messages\DomainTransferRequest;
use Waterfront\Infra\OpenSrsClient\Messages\DomainTransferResponse;

class OpenSrsClient
{
    public function __construct(
        protected Client $httpClient,
        protected OpenSrsConnectionInterface $connection,
    ) {
    }

    /**
     * @throws Exception
     * @throws GuzzleException
     */
    public function checkDomain(string $domain): CheckResult
    {
        $request = new DomainLookupRequest($this->httpClient, $this->connection, $domain);

        $response = $this->sendLookupRequest($request);
        $this->assertHttpOk($response->getStatusCode(), $response->getStatusMessage());

        return $response->getResult($domain);
    }

    /**
     * @throws Exception
     * @throws GuzzleException
     */
    public function registerDomain(RegistrationParameters $parameters): RegistrationResult
    {
        $request = new DomainRegistrationRequest($this->httpClient, $this->connection, $parameters->getDomain());
        $request->setParameters($parameters);

        $response = $this->sendRegistrationRequest($request);
        $this->assertHttpOk($response->getStatusCode(), $response->getStatusMessage());

        $result = $response->getResult();
        if ($result->getStatus() === DomainStatus::FAILED) {
            throw new OpenSrsResultException($result->getReason(), $response->getResponseCode());
        }

        return $result;
    }

    /**
     * @throws Exception
     * @throws GuzzleException
     */
    public function transferDomain(TransferParameters $parameters): TransferResult
    {
        $request = new DomainTransferRequest($this->httpClient, $this->connection, $parameters->getDomain());
        $request->setParameters($parameters);

        $response = $this->sendTransferRequest($request);
        $this->assertHttpOk($response->getStatusCode(), $response->getStatusMessage());

        $result = $response->getResult();
        if ($result->getStatus() === DomainStatus::FAILED->value) {
            throw new OpenSrsResultException((string) $result->getReason(), $response->getResponseCode());
        }

        return $result;
    }

    /**
     * @throws Exception
     * @throws GuzzleException
     */
    public function retrieveDomain(string $domain): RetrieveResult
    {
        $request = new DomainGetRequest($this->httpClient, $this->connection, $domain, 'all_info');

        $response = $this->sendGetRequest($request);
        $this->assertHttpOk($response->getStatusCode(), $response->getStatusMessage());

        if (! $response->isSuccess()) {
            throw new OpenSrsResultException($response->getResponseText(), $response->getResponseCode());
        }

        return $response->getResult($domain);
    }

    /**
     * @throws Exception
     * @throws GuzzleException
     */
    public function retrieveAuthCode(string $domain): ?string
    {
        $request = new DomainGetRequest($this->httpClient, $this->connection, $domain, 'domain_auth_info');

        $response = $this->sendGetRequest($request);
        $this->assertHttpOk($response->getStatusCode(), $response->getStatusMessage());

        if (! $response->isSuccess()) {
            throw new OpenSrsResultException($response->getResponseText(), $response->getResponseCode());
        }

        return $response->getAuthCode();
    }

    /**
     * @throws Exception
     * @throws GuzzleException
     */
    public function modifyDomain(ModifyParameters $parameters): void
    {
        foreach ($this->modifications($parameters) as [$type, $data]) {
            $request = new DomainModifyRequest(
                $this->httpClient,
                $this->connection,
                $parameters->getDomain(),
                $type,
                $data,
            );

            $response = $this->sendModifyRequest($request);
            $this->assertHttpOk($response->getStatusCode(), $response->getStatusMessage());

            if (! $response->isSuccess()) {
                throw new OpenSrsResultException($response->getResponseText(), $response->getResponseCode());
            }
        }
    }

    /**
     * @param Nameserver[] $nameServers
     *
     * @throws Exception
     * @throws GuzzleException
     */
    public function updateNameServers(string $domain, array $nameServers): void
    {
        $request = new DomainNameserverUpdateRequest($this->httpClient, $this->connection, $domain, $nameServers);

        $response = $this->sendNameserverUpdateRequest($request);
        $this->assertHttpOk($response->getStatusCode(), $response->getStatusMessage());

        if (! $response->isSuccess()) {
            throw new OpenSrsResultException($response->getResponseText(), $response->getResponseCode());
        }
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    protected function sendLookupRequest(DomainLookupRequest $request): DomainLookupResponse
    {
        return new DomainLookupResponse($request->send());
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    protected function sendRegistrationRequest(DomainRegistrationRequest $request): DomainRegistrationResponse
    {
        return new DomainRegistrationResponse($request->send());
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    protected function sendTransferRequest(DomainTransferRequest $request): DomainTransferResponse
    {
        return new DomainTransferResponse($request->send());
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    protected function sendGetRequest(DomainGetRequest $request): DomainGetResponse
    {
        return new DomainGetResponse($request->send());
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    protected function sendModifyRequest(DomainModifyRequest $request): DomainModifyResponse
    {
        return new DomainModifyResponse($request->send());
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    protected function sendNameserverUpdateRequest(DomainNameserverUpdateRequest $request): DomainModifyResponse
    {
        return new DomainModifyResponse($request->send());
    }

    /**
     * Translate a single Waterfront modify payload into the per-`data` OpenSRS modify calls.
     *
     * @return list<array{0: string, 1: array<string, string>}>
     */
    private function modifications(ModifyParameters $parameters): array
    {
        $modifications = [];

        if ($parameters->getAutoRenew() !== null) {
            $modifications[] = ['expire_action', [
                'auto_renew'  => $parameters->getAutoRenew() ? '1' : '0',
                'let_expire'  => $parameters->getAutoRenew() ? '0' : '1',
            ]];
        }

        if ($parameters->getIsLocked() !== null) {
            $modifications[] = ['status', [
                'lock_state' => $parameters->getIsLocked() ? '1' : '0',
            ]];
        }

        if ($parameters->getIsPrivateWhoisEnabled() !== null) {
            $modifications[] = ['whois_privacy_state', [
                'state' => $parameters->getIsPrivateWhoisEnabled() ? 'enable' : 'disable',
            ]];
        }

        return $modifications;
    }

    private function assertHttpOk(int $statusCode, string $statusMessage): void
    {
        if ($statusCode !== 200) {
            throw new RuntimeException($statusMessage, $statusCode);
        }
    }
}
