<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenproviderClient\Fakers;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response as HttpResponse;
use Illuminate\Support\Arr;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Waterfront\Domain\Domains\DTO\DestroyContactResult;
use Waterfront\Domain\Domains\DTO\HandleParameters;
use Waterfront\Domain\Domains\DTO\RetrieveCustomerResponse;
use Waterfront\Domain\Domains\Interfaces\HandleInterface;
use Waterfront\Infra\OpenproviderClient\Messages\DomainCheckRequest;
use Waterfront\Infra\OpenproviderClient\Messages\DomainCheckResponse;
use Waterfront\Infra\OpenproviderClient\Messages\DomainModifyRequest;
use Waterfront\Infra\OpenproviderClient\Messages\DomainRegistrationRequest;
use Waterfront\Infra\OpenproviderClient\Messages\DomainRegistrationResponse;
use Waterfront\Infra\OpenproviderClient\Messages\DomainRetrieveRequest;
use Waterfront\Infra\OpenproviderClient\Messages\DomainRetrieveResponse;
use Waterfront\Infra\OpenproviderClient\Messages\DomainTransferRequest;
use Waterfront\Infra\OpenproviderClient\Messages\DomainTransferResponse;
use Waterfront\Infra\OpenproviderClient\Messages\ExtensionRetrieveRequest;
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
use Waterfront\Infra\OpenproviderClient\OpenproviderClient;
use Waterfront\Infra\PleskClient\Traits\DesiredResponseCodeTrait;

class OpenproviderClientFaker extends OpenproviderClient
{
    use DesiredResponseCodeTrait;

    public function getCustomerHandle(string $handle): RetrieveCustomerResponse
    {
        $desiredResponseCode = $this->getDesiredResponseCode();
        if (! (bool) $desiredResponseCode) {
            $desiredResponseCode = 200;
        }

        fopen(
            __DIR__ . '/../../../../tests/Infra/OpenproviderClient/data/openprovider_retrieve_handle_response.xml',
            'r',
        );

        return RetrieveCustomerResponse::fromXMLResponse(
            new HttpResponse(
                $desiredResponseCode,
                [],
                (string) file_get_contents(__DIR__
                . '/../../../../tests/Infra/OpenproviderClient/data/openprovider_retrieve_handle_response.xml'),
            ),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function createHandles(array $customer, bool $modify = false): HandleInterface
    {
        if (Arr::get($customer, 'address.zip_code') === 0) {
            throw new RuntimeException('Openprovider test error');
        }

        return parent::createHandles($customer, $modify);
    }

    public function destroyContact(string $externalId): DestroyContactResult
    {
        return new DestroyContactResult(true);
    }

    public function getRetrieveExtensionRequest(string $extension): ExtensionRetrieveRequest
    {
        return new ExtensionRetrieveRequest(new Client(), $this->connection, $extension);
    }

    public function sendRetrieveExtensionRequest(ExtensionRetrieveRequest $request): ResponseInterface
    {
        $extension = $request->getExtension();

        $location =
            __DIR__
            . "/../../../../tests/Infra/OpenproviderClient/data/openprovider_retrieve_extension_response_$extension.xml";

        if (! file_exists($location)) {
            if ($extension === 'eennietbestaandetld') {
                // specifically for the unit test
                $location =
                    __DIR__
                    . '/../../../../tests/Infra/OpenproviderClient/data/openprovider_retrieve_extension_invalid.xml';
            } else {
                // this is to not show errors during development
                $location =
                    __DIR__
                    . '/../../../../tests/Infra/OpenproviderClient/data/openprovider_retrieve_extension_response_nl.xml';
            }
        }

        return new HttpResponse(
            200,
            ['Content-Type' => 'text/xml'],
            (string) file_get_contents($location),
        );
    }

    public function createHandle(HandleParameters $handleParameters): string
    {
        return 'BA904019-NL';
    }

    /**
     * @throws Exception
     */
    protected function sendCheckDomainRequest(DomainCheckRequest $request): DomainCheckResponse
    {
        $desiredResponseCode = $this->getDesiredResponseCode();
        if ($desiredResponseCode === 0) {
            $desiredResponseCode = 200;
        }

        $xmlResponse = '<?xml version="1.0" encoding="UTF-8"?>
            <openXML>
            <reply>
            <code>0</code>
            <desc></desc>
            <data>
                <array>
                    <item>
                        <domain>' . $request->getDomain() . '</domain>
                        <status>free</status>
                        <reason></reason>
                    </item>
                </array>
            </data>
            </reply>
            </openXML>';

        $httpResponse = new HttpResponse(
            $desiredResponseCode,
            [],
            $xmlResponse,
        );

        return new DomainCheckResponse($httpResponse, (string) $request->getDomain());
    }

    /**
     * @throws Exception
     */
    protected function sendSearchDomainRequest(SearchDomainRequest $request): SearchDomainResponse
    {
        $desiredResponseCode = $this->getDesiredResponseCode();
        if ($desiredResponseCode === 0) {
            $desiredResponseCode = 200;
        }

        $xmlResponse = (string) file_get_contents(__DIR__
        . '/../../../../tests/Infra/OpenproviderClient/data/openprovider_deleted_domains_response.xml');
        $httpResponse = new HttpResponse(
            $desiredResponseCode,
            [],
            $xmlResponse,
        );

        return new SearchDomainResponse($httpResponse);
    }

    /**
     * @throws Exception
     */
    protected function sendRegisterDomainRequest(DomainRegistrationRequest $request): DomainRegistrationResponse
    {
        $desiredResponseCode = $this->getDesiredResponseCode();
        if ($desiredResponseCode === 0) {
            $desiredResponseCode = 200;
        }

        $httpResponse = new HttpResponse(
            $desiredResponseCode,
            [],
            (string) file_get_contents(__DIR__
            . '/../../../../tests/Infra/OpenproviderClient/data/openprovider_register_response.xml'),
        );

        return new DomainRegistrationResponse($httpResponse);
    }

    /**
     * @throws Exception
     */
    protected function sendTransferDomainRequest(DomainTransferRequest $request): DomainTransferResponse
    {
        $desiredResponseCode = $this->getDesiredResponseCode();
        if ($desiredResponseCode === 0) {
            $desiredResponseCode = 200;
        }

        $httpResponse = new HttpResponse(
            $desiredResponseCode,
            [],
            (string) file_get_contents(__DIR__
            . '/../../../../tests/Infra/OpenproviderClient/data/openprovider_transfer_response.xml'),
        );

        return new DomainTransferResponse($httpResponse);
    }

    protected function sendRetrieveDomainRequest(DomainRetrieveRequest $request): ResponseInterface
    {
        $desiredResponseCode = $this->getDesiredResponseCode();
        if ($desiredResponseCode === 0) {
            $desiredResponseCode = 200;
        }

        return new HttpResponse(
            $desiredResponseCode,
            [],
            (string) file_get_contents(__DIR__
            . '/../../../../tests/Infra/OpenproviderClient/data/openprovider_retrieve_response_dnssec_external.xml'),
        );
    }

    protected function sendModifyDomainRequest(DomainModifyRequest $request): ResponseInterface
    {
        $desiredResponseCode = $this->getDesiredResponseCode();
        if ($desiredResponseCode === 0) {
            $desiredResponseCode = 200;
        }

        return new HttpResponse(
            $desiredResponseCode,
            [],
            (string) file_get_contents(__DIR__
            . '/../../../../tests/Infra/OpenproviderClient/data/openprovider_modify_response.xml'),
        );
    }

    /**
     * @throws Exception
     */
    protected function sendSslApproverRequest(SslApproverRequest $request): SslApproverResponse
    {
        $desiredResponseCode = $this->getDesiredResponseCode();
        if ($desiredResponseCode === 0) {
            $desiredResponseCode = 200;
        }

        $httpResponse = new HttpResponse(
            $desiredResponseCode,
            [],
            (string) file_get_contents(__DIR__
            . '/../../../../tests/Infra/OpenproviderClient/data/openprovider_ssl_approver_response.xml'),
        );

        return new SslApproverResponse($httpResponse);
    }

    /**
     * @throws Exception
     */
    protected function sendCreateSslRequest(SslCreateRequest $request): SslCreateResponse
    {
        $desiredResponseCode = $this->getDesiredResponseCode();
        if ($desiredResponseCode === 0) {
            $desiredResponseCode = 200;
        }

        $httpResponse = new HttpResponse(
            $desiredResponseCode,
            [],
            (string) file_get_contents(__DIR__
            . '/../../../../tests/Infra/OpenproviderClient/data/openprovider_ssl_create_response.xml'),
        );

        return new SslCreateResponse($httpResponse);
    }

    /**
     * @throws Exception
     */
    protected function sendReissueSslRequest(SslReissueRequest $request): SslReissueResponse
    {
        $desiredResponseCode = $this->getDesiredResponseCode();
        if ($desiredResponseCode === 0) {
            $desiredResponseCode = 200;
        }

        $httpResponse = new HttpResponse(
            $desiredResponseCode,
            [],
            (string) file_get_contents(__DIR__
            . '/../../../../tests/Infra/OpenproviderClient/data/openprovider_ssl_reissue_response.xml'),
        );

        return new SslReissueResponse($httpResponse);
    }

    /**
     * @throws Exception
     */
    protected function sendRenewSslRequest(SslRenewRequest $request): SslRenewResponse
    {
        $desiredResponseCode = $this->getDesiredResponseCode();
        if ($desiredResponseCode === 0) {
            $desiredResponseCode = 200;
        }

        $httpResponse = new HttpResponse(
            $desiredResponseCode,
            [],
            (string) file_get_contents(__DIR__
            . '/../../../../tests/Infra/OpenproviderClient/data/openprovider_ssl_renew_response.xml'),
        );

        return new SslRenewResponse($httpResponse);
    }

    /**
     * @throws JsonException
     */
    protected function sendRetrieveSslRequest(SslRetrieveRequest $request): SslRetrieveResponse
    {
        $desiredResponseCode = $this->getDesiredResponseCode();
        if ($desiredResponseCode === 0) {
            $desiredResponseCode = 200;
        }

        $httpResponse = new HttpResponse(
            $desiredResponseCode,
            [],
            (string) file_get_contents(__DIR__
            . '/../../../../tests/Infra/OpenproviderClient/data/openprovider_ssl_retrieve_response.xml'),
        );

        return new SslRetrieveResponse($httpResponse);
    }

    /**
     * @throws Exception
     */
    protected function sendRetrieveDomainRequestWithResponse(DomainRetrieveRequest $request): DomainRetrieveResponse
    {
        $desiredResponseCode = $this->getDesiredResponseCode();
        if ($desiredResponseCode === 0) {
            $desiredResponseCode = 200;
        }

        $httpResponse = new HttpResponse(
            $desiredResponseCode,
            [],
            (string) file_get_contents(__DIR__
            . '/../../../../tests/Infra/OpenproviderClient/data/openprovider_retrieve_response.xml'),
        );

        return new DomainRetrieveResponse($httpResponse);
    }
}
