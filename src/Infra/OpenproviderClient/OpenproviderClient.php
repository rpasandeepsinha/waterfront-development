<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenproviderClient;

use Carbon\CarbonImmutable;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use JsonException;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use SimpleXMLElement;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Waterfront\Domain\Domains\DTO\CheckResult;
use Waterfront\Domain\Domains\DTO\DestroyContactResult;
use Waterfront\Domain\Domains\DTO\Domain;
use Waterfront\Domain\Domains\DTO\Extension;
use Waterfront\Domain\Domains\DTO\HandleParameters;
use Waterfront\Domain\Domains\DTO\Handles;
use Waterfront\Domain\Domains\DTO\ModifyHandles;
use Waterfront\Domain\Domains\DTO\ModifyParameters;
use Waterfront\Domain\Domains\DTO\RegistrationParameters;
use Waterfront\Domain\Domains\DTO\RegistrationResult;
use Waterfront\Domain\Domains\DTO\RetrieveCustomerResponse;
use Waterfront\Domain\Domains\DTO\RetrieveResult;
use Waterfront\Domain\Domains\DTO\TransferParameters;
use Waterfront\Domain\Domains\DTO\TransferResult;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Domains\Interfaces\HandleInterface;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Ssl\Interfaces\Models\Approver\Parameters as ApproverParameters;
use Waterfront\Domain\Ssl\Interfaces\Models\Parameters as SslParameters;
use Waterfront\Domain\Ssl\Interfaces\Models\Result;
use Waterfront\Domain\Ssl\Jobs\UpdateDns;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\OpenproviderClient\Exceptions\OpenProviderResultException;
use Waterfront\Infra\OpenproviderClient\Interfaces\OpenProviderConnectionInterface;
use Waterfront\Infra\OpenproviderClient\Messages\CustomerDeleteRequest;
use Waterfront\Infra\OpenproviderClient\Messages\CustomerDeleteResponse;
use Waterfront\Infra\OpenproviderClient\Messages\DomainCheckRequest;
use Waterfront\Infra\OpenproviderClient\Messages\DomainCheckResponse;
use Waterfront\Infra\OpenproviderClient\Messages\DomainHandleRequest;
use Waterfront\Infra\OpenproviderClient\Messages\DomainHandleResponse;
use Waterfront\Infra\OpenproviderClient\Messages\DomainModifyRequest;
use Waterfront\Infra\OpenproviderClient\Messages\DomainRegistrationRequest;
use Waterfront\Infra\OpenproviderClient\Messages\DomainRegistrationResponse;
use Waterfront\Infra\OpenproviderClient\Messages\DomainRetrieveRequest;
use Waterfront\Infra\OpenproviderClient\Messages\DomainRetrieveResponse;
use Waterfront\Infra\OpenproviderClient\Messages\DomainTransferRequest;
use Waterfront\Infra\OpenproviderClient\Messages\DomainTransferResponse;
use Waterfront\Infra\OpenproviderClient\Messages\ExtensionRetrieveRequest;
use Waterfront\Infra\OpenproviderClient\Messages\RetrieveCustomerRequest;
use Waterfront\Infra\OpenproviderClient\Messages\SearchDomainRequest;
use Waterfront\Infra\OpenproviderClient\Messages\SearchDomainResponse;
use Waterfront\Infra\OpenproviderClient\Messages\SslApproverRequest;
use Waterfront\Infra\OpenproviderClient\Messages\SslApproverResponse;
use Waterfront\Infra\OpenproviderClient\Messages\SslCreateRequest;
use Waterfront\Infra\OpenproviderClient\Messages\SslCreateResponse;
use Waterfront\Infra\OpenproviderClient\Messages\SslReissueRequest;
use Waterfront\Infra\OpenproviderClient\Messages\SslReissueResponse;
use Waterfront\Infra\OpenproviderClient\Messages\SslRenewRequest;
use Waterfront\Infra\OpenproviderClient\Messages\SslRenewResponse;
use Waterfront\Infra\OpenproviderClient\Messages\SslRetrieveRequest;
use Waterfront\Infra\OpenproviderClient\Messages\SslRetrieveResponse;
use Waterfront\Support\Enums\LoggingContextKeys;

class OpenproviderClient
{
    public function __construct(
        protected Client $httpClient,
        protected OpenProviderConnectionInterface $connection,
        private readonly Dispatcher $jobDispatcher,
    ) {
    }

    /**
     * @throws GuzzleException
     */
    public function getCustomerHandle(string $handle): RetrieveCustomerResponse
    {
        $request = new RetrieveCustomerRequest($this->httpClient, $this->connection, $handle);

        return RetrieveCustomerResponse::fromXMLResponse($request->send());
    }

    /**
     * Create a handles object, consisting of:
     * owner, admin, tech and billing.
     *
     * @param array<mixed> $customer
     *
     * @throws Exception
     * @throws GuzzleException
     */
    public function createHandles(array $customer, bool $modify = false): HandleInterface
    {
        $billingHandleId = Config::get('openproviderclient.handles.billing');
        assert(is_string($billingHandleId));

        $adminHandleId = $this->createHandle(
            HandleParameters::createFromCustomerArray($customer),
        );

        $class = Handles::class;
        if ($modify) {
            $class = ModifyHandles::class;
        }

        return new $class($adminHandleId, $adminHandleId, $adminHandleId, $billingHandleId);
    }

    /**
     * Get extension data for extension name.
     *
     * @throws RuntimeException
     * @throws GuzzleException
     * @throws Exception
     */
    public function retrieveExtension(string $extension): Extension
    {
        $request = $this->getRetrieveExtensionRequest($extension);

        $response = $this->sendRetrieveExtensionRequest($request);

        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException($response->getReasonPhrase(), $response->getStatusCode());
        }

        $xmlRepsonse = new SimpleXMLElement((string) $response->getBody());
        $reply = $xmlRepsonse->reply;

        if ((int) $reply->code !== 0) {
            throw new OpenProviderResultException((string) $reply->desc, (int) $reply->code);
        }

        return Extension::create((array) $reply->data);
    }

    public function getRetrieveExtensionRequest(string $extension): ExtensionRetrieveRequest
    {
        return new ExtensionRetrieveRequest($this->httpClient, $this->connection, $extension);
    }

    /**
     * {@inheritDoc}
     *
     * @throws RuntimeException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function retrieveSsl(int $certificateId): Result
    {
        $request = $this->getRetrieveSslRequest($certificateId);
        $response = $this->sendRetrieveSslRequest($request);

        if (! $response->isSuccess()) {
            throw new RuntimeException($response->getReason());
        }

        $sslCertificate = $response->getResult();

        Log::info(self::class . '::retrieveSsl - SSL retrieved', [
            LoggingContextKeys::META => [
                'ssl_certificate' => $sslCertificate->toArray(),
            ],
        ]);

        return $sslCertificate;
    }

    public function getRenewSslRequest(int $certificateId): SslRenewRequest
    {
        $request = new SslRenewRequest($this->httpClient, $this->connection);
        $request->setCertificateId($certificateId);

        return $request;
    }

    /**
     * @throws RuntimeException
     * @throws GuzzleException
     */
    public function checkDomain(string $domain): CheckResult
    {
        $request = $this->getCheckDomainRequest($domain);

        $response = $this->sendCheckDomainRequest($request);

        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException($response->getStatusMessage(), $response->getStatusCode());
        }

        return $response->getResult();
    }

    /**
     * @throws Exception
     */
    public function getRegisterDomainRequest(
        RegistrationParameters $parameters,
        HandleInterface $handles,
    ): DomainRegistrationRequest {
        $request = new DomainRegistrationRequest($this->httpClient, $this->connection, $parameters->getDomain());
        $request->setHandles($handles);
        $request->setParameters($parameters);

        return $request;
    }

    /**
     * {@inheritDoc}
     *
     * @throws RuntimeException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function renewSsl(int $certificateId): Result
    {
        $request = $this->getRenewSslRequest($certificateId);
        $response = $this->sendRenewSslRequest($request);
        if (! $response->isSuccess()) {
            throw new RuntimeException($response->getReason());
        }

        $certificateId = $response->getCertificateId();
        assert($certificateId !== null);

        Log::info(
            self::class . '::createSsl - SSL renewed',
            [
                LoggingContextKeys::META => [
                    'ssl_certificate_id' => $certificateId,
                ],
            ],
        );

        return $this->retrieveSsl($certificateId);
    }

    public function getCreateSslRequest(SslParameters $parameters, HandleInterface $handles): SslCreateRequest
    {
        $request = new SslCreateRequest($this->httpClient, $this->connection);
        $request->setParameters($parameters);
        $request->setHandles($handles);

        return $request;
    }

    /**
     * @throws RuntimeException
     * @throws GuzzleException
     * @throws Exception
     */
    public function registerDomain(RegistrationParameters $parameters): RegistrationResult
    {
        $handles = $parameters->getHandles();
        $billingHandle = Config::get('openproviderclient.handles.billing');
        assert(is_string($billingHandle));
        $handles->setBillingHandle($billingHandle);

        Log::info(
            self::class . '::registerDomain - Using handles',
            [
                LoggingContextKeys::META => [
                    'handles' => $handles->toArray(),
                ],
            ],
        );

        $request = $this->getRegisterDomainRequest($parameters, $handles);

        $response = $this->sendRegisterDomainRequest($request);

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            throw new RuntimeException($response->getStatusMessage(), $response->getStatusCode());
        }

        $result = $response->getResult();
        $status = $result->getStatus();
        if ($status === DomainStatus::FAILED) {
            throw new RuntimeException($status->value . ': ' . $result->getReason());
        }

        return $result;
    }

    /**
     * @throws Exception
     */
    public function getTransferDomainRequest(
        TransferParameters $parameters,
        HandleInterface $handles,
    ): DomainTransferRequest {
        $request = new DomainTransferRequest($this->httpClient, $this->connection, $parameters->getDomain());
        $request->setHandles($handles);
        $request->setParameters($parameters);

        return $request;
    }

    /**
     * {@inheritDoc}
     *
     * @throws RuntimeException
     * @throws GuzzleException
     * @throws JsonException
     * @throws Exception
     */
    public function createSsl(SslParameters $parameters): Result
    {
        $handles = $this->createHandles($parameters->getCustomer());

        $approverParameters = ApproverParameters::create([
            'domain' => $parameters->getDomain(),
            'productId' => $parameters->getProductId(),
        ]);
        $approverEmail = $this->sslApproverEmail($approverParameters);

        $parameters->setApproverEmail($approverEmail);
        $parameters->setDomainValidationMethods([
            [
                'hostName' => $parameters->getDomain(),
                'method' => 'dns',
            ],
        ]);

        $request = $this->getCreateSslRequest($parameters, $handles);
        $response = $this->sendCreateSslRequest($request);
        if (! $response->isSuccess()) {
            throw new RuntimeException($response->getReason());
        }

        $certificateId = $response->getCertificateId();
        assert($certificateId !== null);

        Log::info(
            self::class . '::createSsl - SSL requested',
            [
                LoggingContextKeys::META => [
                    'ssl_certificate_id' => $certificateId,
                ],
            ],
        );

        return $this->retrieveSsl($certificateId);
    }

    public function getReissueSslRequest(
        int $certificateId,
        SslParameters $parameters,
        HandleInterface $handles,
    ): SslReissueRequest {
        $request = new SslReissueRequest($this->httpClient, $this->connection);
        $request->setCertificateId($certificateId);
        $request->setParameters($parameters);
        $request->setHandles($handles);

        return $request;
    }

    /**
     * @throws RuntimeException
     * @throws GuzzleException
     * @throws Exception
     */
    public function transferDomain(TransferParameters $parameters): TransferResult
    {
        $handles = $this->createHandles($parameters->getCustomer());
        $request = $this->getTransferDomainRequest($parameters, $handles);
        $response = $this->sendTransferDomainRequest($request);

        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException($response->getStatusMessage(), $response->getStatusCode());
        }

        $result = $response->getResult();
        $status = $result->getStatus();
        if ($status === DomainStatus::FAILED->value) {
            throw new RuntimeException($status . ': ' . $result->getReason());
        }

        return $result;
    }

    /**
     * @throws OpenProviderResultException
     * @throws JsonException
     * @throws GuzzleException
     * @throws Exception
     */
    public function retrieveDomain(string $domain): RetrieveResult
    {
        $data = $this->retrieveDomainInformation($domain);

        assert(is_string($data['orderDate']));

        $this->storeDomainInformation($data, $domain);

        $result = new RetrieveResult();

        $domain = $data['domain'];
        assert(is_array($domain));
        assert(is_string($domain['name']));
        assert(is_string($domain['extension']));
        $result->setDomain(new Domain($domain['name'] . '.' . $domain['extension']));
        $result->setOrderDate($data['orderDate']);
        if (array_key_exists('activeDate', $data) && $data['activeDate'] !== '' && is_string($data['activeDate'])) {
            $result->setActiveDate($data['activeDate']);
        }

        $result->setExpirationDate($data['expirationDate']);
        $result->setExpirationDateOpenprovider($data['expirationDateOpenprovider']);
        $result->setHandles(
            new Handles(
                $this->castHandle($data['ownerHandle']),
                $this->castHandle($data['adminHandle']),
                $this->castHandle($data['techHandle']),
                $this->castHandle($data['billingHandle']),
            ),
        );
        $result->setNsGroup($data['nsGroup'] ?? null);

        $nameServers = Arr::get($data, 'nameServers.array.item');
        if (is_array($nameServers)) {
            if (
                array_key_exists('id', $nameServers)
                || array_key_exists('seqNr', $nameServers)
                || array_key_exists('name', $nameServers)
            ) {
                $nameServers = [$nameServers];
            }

            foreach ($nameServers as $key => $nameServer) {
                if (is_array($nameServer)) {
                    // Fix for when OP returns an empty array for ipv4
                    if (array_key_exists('ip', $nameServer) && $nameServer['ip'] === []) {
                        $nameServer['ip'] = null;
                        $nameServers[$key] = $nameServer;
                    }

                    // Fix for when OP returns an empty array for ipv6
                    if (array_key_exists('ip6', $nameServer) && $nameServer['ip6'] === []) {
                        $nameServer['ip6'] = null;
                        $nameServers[$key] = $nameServer;
                    }
                }
            }

            $result->setNameServers($nameServers);
        }

        if (array_key_exists('authCode', $data) && $data['authCode'] !== '' && is_string($data['authCode'])) {
            $result->setAuthCode($data['authCode']);
        }

        $result->setStatus($data['status']);
        $result->setAutoRenewAsString($data['autorenew']);
        $result->setIsLocked((bool) $data['isLocked']);

        if (
            array_key_exists('isDnssecEnabled', $data)
            && $data['isDnssecEnabled'] !== ''
            && $data['isDnssecEnabled'] !== '0'
            && is_string($data['isDnssecEnabled'])
        ) {
            $result->setIsDnssecEnabled(true);
        } else {
            $result->setIsDnssecEnabled(false);
        }

        if (
            array_key_exists('isPrivateWhoisEnabled', $data)
            && $data['isPrivateWhoisEnabled'] !== ''
            && $data['isPrivateWhoisEnabled'] !== '0'
            && is_string($data['isPrivateWhoisEnabled'])
        ) {
            $result->setIsPrivateWhoisEnabled(true);
        } else {
            $result->setIsPrivateWhoisEnabled(false);
        }

        if (array_key_exists('dnssecKeys', $data) && $data['dnssecKeys'] !== '') {
            $result->setDnssecKeys($data['dnssecKeys']);
        }

        return $result;
    }

    /**
     * @throws Exception
     */
    public function getModifyDomainRequest(
        ModifyParameters $parameters,
        ?HandleInterface $handles = null,
    ): DomainModifyRequest {
        $request = new DomainModifyRequest($this->httpClient, $this->connection, $parameters->getDomain());
        $request->setParameters($parameters);
        $request->setHandles($handles);

        return $request;
    }

    /**
     * {@inheritDoc}
     *
     * @throws RuntimeException
     * @throws GuzzleException
     * @throws Exception
     */
    public function reissueSsl(int $certificateId, SslParameters $parameters): Result
    {
        $handles = $this->getSslReissueHandles($parameters);

        $approverParameters = ApproverParameters::create([
            'domain' => $parameters->getDomain(),
            'productId' => $parameters->getProductId(),
        ]);
        $approverEmail = $this->sslApproverEmail($approverParameters);

        $parameters->setApproverEmail($approverEmail);
        $parameters->setDomainValidationMethods([
            [
                'hostName' => $parameters->getDomain(),
                'method' => 'dns',
            ],
        ]);

        $request = $this->getReissueSslRequest($certificateId, $parameters, $handles);
        $response = $this->sendReissueSslRequest($request);
        if (! $response->isSuccess()) {
            throw new RuntimeException($response->getReason());
        }

        $certificateId = $response->getCertificateId();
        assert($certificateId !== null);

        Log::info(
            self::class . '::reissueSsl - SSL reissued',
            [
                LoggingContextKeys::META => [
                    'ssl_certificate_id' => $certificateId,
                ],
            ],
        );

        $result = $this->retrieveSsl($certificateId);

        $job = new UpdateDns($parameters->getDomain(), false);
        $job->onConnection('sync');

        $this->jobDispatcher->dispatch($job);

        return $result;
    }

    public function getSslApproverRequest(ApproverParameters $parameters): SslApproverRequest
    {
        $request = new SslApproverRequest($this->httpClient, $this->connection);
        $request->setParameters($parameters);

        return $request;
    }

    /**
     * @throws RuntimeException
     * @throws GuzzleException
     * @throws Exception
     */
    public function modifyDomain(ModifyParameters $parameters): void
    {
        $handles = $parameters->getHandles();
        if ($handles !== null && $parameters->getCustomer() !== null) {
            $handles = $this->createHandles($parameters->getCustomer(), true);
        }

        $request = $this->getModifyDomainRequest($parameters, $handles);
        $response = $this->sendModifyDomainRequest($request);

        if ($response->getStatusCode() !== 200) {
            throw new OpenProviderResultException($response->getReasonPhrase(), $response->getStatusCode());
        }

        $xmlResponse = new SimpleXMLElement((string) $response->getBody());
        $statusCode = (int) (string) $xmlResponse->reply->code;
        if ($statusCode !== 0) {
            throw new OpenProviderResultException((string) $xmlResponse->reply->desc, $statusCode);
        }
    }

    /**
     * @throws LogicException
     */
    public function destroyContact(string $externalId): DestroyContactResult
    {
        try {
            $request = new CustomerDeleteRequest($this->httpClient, $this->connection, $externalId);
            $response = new CustomerDeleteResponse($request->send());

            return new DestroyContactResult($response->isSuccess());
        } catch (Throwable $exception) {
            Log::error(
                'Failed to delete a contact: ' . $exception->getMessage() . "\nStack trace:\n"
                    . $exception->getTraceAsString(),
            );

            throw new LogicException(
                $exception->getMessage(),
                $exception->getCode(),
                $exception,
            );
        }
    }

    /**
     * @throws Exception
     * @throws GuzzleException
     */
    public function createHandle(HandleParameters $handleParameters): string
    {
        $request = new DomainHandleRequest($this->httpClient, $this->connection);
        $request->setParameters($handleParameters);

        $response = new DomainHandleResponse($request->send());

        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException($response->getStatusMessage(), $response->getStatusCode());
        }

        if (! $response->isSuccess()) {
            throw new RuntimeException($response->getReason());
        }

        $handleId = $response->getResult();

        Log::info(
            self::class . '::createHandle - Handle created',
            [
                LoggingContextKeys::META => [
                    'handle_id' => $handleId,
                ],
            ],
        );

        return $handleId;
    }

    /**
     * @throws GuzzleException
     */
    protected function sendRetrieveExtensionRequest(ExtensionRetrieveRequest $request): ResponseInterface
    {
        return $request->send();
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    protected function sendCheckDomainRequest(DomainCheckRequest $request): DomainCheckResponse
    {
        return new DomainCheckResponse($request->send(), (string) $request->getDomain());
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    protected function sendSearchDomainRequest(SearchDomainRequest $request): SearchDomainResponse
    {
        return new SearchDomainResponse($request->send());
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    protected function sendRegisterDomainRequest(DomainRegistrationRequest $request): DomainRegistrationResponse
    {
        return new DomainRegistrationResponse($request->send());
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    protected function sendTransferDomainRequest(DomainTransferRequest $request): DomainTransferResponse
    {
        return new DomainTransferResponse($request->send());
    }

    /**
     * @throws GuzzleException
     */
    protected function sendRetrieveDomainRequest(DomainRetrieveRequest $request): ResponseInterface
    {
        return $request->send();
    }

    /**
     * @throws GuzzleException
     */
    protected function sendModifyDomainRequest(DomainModifyRequest $request): ResponseInterface
    {
        return $request->send();
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     */
    protected function sendRetrieveSslRequest(SslRetrieveRequest $request): SslRetrieveResponse
    {
        return new SslRetrieveResponse($request->send());
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    protected function sendRenewSslRequest(SslRenewRequest $request): SslRenewResponse
    {
        return new SslRenewResponse($request->send());
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    protected function sendReissueSslRequest(SslReissueRequest $request): SslReissueResponse
    {
        return new SslReissueResponse($request->send());
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    protected function sendCreateSslRequest(SslCreateRequest $request): SslCreateResponse
    {
        return new SslCreateResponse($request->send());
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    protected function sendSslApproverRequest(SslApproverRequest $request): SslApproverResponse
    {
        return new SslApproverResponse($request->send());
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    protected function sendRetrieveDomainRequestWithResponse(DomainRetrieveRequest $request): DomainRetrieveResponse
    {
        return new DomainRetrieveResponse($request->send());
    }

    private function getCheckDomainRequest(string $domain): DomainCheckRequest
    {
        return new DomainCheckRequest($this->httpClient, $this->connection, $domain);
    }

    private function getRetrieveSslRequest(int $certificateId): SslRetrieveRequest
    {
        $request = new SslRetrieveRequest($this->httpClient, $this->connection);
        $request->setCertificateId($certificateId);

        return $request;
    }

    /**
     * @throws Exception
     */
    private function getRetrieveDomainRequest(string $domain): DomainRetrieveRequest
    {
        return new DomainRetrieveRequest($this->httpClient, $this->connection, $domain);
    }

    /**
     * {@inheritDoc}
     *
     * @throws RuntimeException
     * @throws GuzzleException
     */
    private function sslApproverEmail(ApproverParameters $parameters): string
    {
        $request = $this->getSslApproverRequest($parameters);
        $response = $this->sendSslApproverRequest($request);

        if (! $response->isSuccess()) {
            throw new RuntimeException($response->getReason());
        }

        $approver = $response->getResult();

        Log::info(
            self::class . '::sslApproverEmail - SSL approver emails',
            [
                LoggingContextKeys::META => [
                    'approver_emails' => $approver->getEmails(),
                ],
            ],
        );

        $approverEmails = $approver->getEmails();

        if (! array_key_exists(0, $approverEmails) || $approverEmails[0] === '') {
            throw new RuntimeException('No SSL approver emails found.');
        }

        return $approverEmails[0];
    }

    /**
     * @throws JsonException
     * @throws Exception
     * @throws GuzzleException
     *
     * @return mixed[]
     *
     */
    private function retrieveDomainInformation(string $domain): array
    {
        $request = $this->getRetrieveDomainRequest($domain);
        $response = $this->sendRetrieveDomainRequest($request);

        if ($response->getStatusCode() !== 200) {
            throw new OpenProviderResultException($response->getReasonPhrase(), $response->getStatusCode());
        }

        $xmlResponse = new SimpleXMLElement((string) $response->getBody());
        $statusCode = (int) (string) $xmlResponse->reply->code;
        if ($statusCode !== 0) {
            throw new OpenProviderResultException((string) $xmlResponse->reply->desc, $statusCode);
        }

        $decoded = json_decode(
            json_encode((array) $xmlResponse->reply->data, JSON_THROW_ON_ERROR),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        assert(is_array($decoded));

        return $decoded;
    }

    /**
     * Make sure that the handle is a string, otherwise return null to handle weird cases.
     */
    private function castHandle(mixed $handle): string
    {
        if (is_string($handle)) {
            return $handle;
        }

        return '';
    }

    /**
     * @throws Exception
     * @throws GuzzleException
     */
    private function getSslReissueHandles(SslParameters $parameters): HandleInterface
    {
        $domainRequest = $this->getRetrieveDomainRequest($parameters->getDomain());
        $domainResponse = $this->sendRetrieveDomainRequestWithResponse($domainRequest);

        return match ($domainResponse->getResponseCode()) {
            0 => new Handles(
                $domainResponse->getOwnerHandle(),
                $domainResponse->getAdminHandle(),
                $domainResponse->getTechHandle(),
                $domainResponse->getBillingHandle(),
            ),
            320 => $this->createHandles($parameters->getCustomer()),
            default => throw new Exception(sprintf(
                'Unable to find or create handles for %s',
                $parameters->getDomain(),
            )),
        };
    }

    /**
     * @param mixed[] $data
     *
     * @throws JsonException
     */
    private function storeDomainInformation(array $data, string $domain): void
    {
        $subscription = Subscription::query()
            ->whereProductGroupType(ProductGroupType::EXTENSION)
            ->where('domain', $domain)
            ->first();

        if ($subscription === null) {
            return;
        }

        $domainDeployment = $subscription->domainDeployment;
        if ($domainDeployment instanceof DomainDeployment) {
            $domainDeployment->last_result = json_encode($data, JSON_THROW_ON_ERROR);
            $domainDeployment->last_result_received = CarbonImmutable::now();
            $domainDeployment->save();
        }
    }
}
