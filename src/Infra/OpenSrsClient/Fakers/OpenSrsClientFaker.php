<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenSrsClient\Fakers;

use GuzzleHttp\Psr7\Response as HttpResponse;
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
use Waterfront\Infra\OpenSrsClient\OpenSrsClient;

class OpenSrsClientFaker extends OpenSrsClient
{
    private const FIXTURES = __DIR__ . '/../../../../tests/Infra/OpenSrsClient/data';

    protected function sendLookupRequest(DomainLookupRequest $request): DomainLookupResponse
    {
        return new DomainLookupResponse($this->fixtureResponse('opensrs_lookup_response_available.xml'));
    }

    protected function sendRegistrationRequest(DomainRegistrationRequest $request): DomainRegistrationResponse
    {
        return new DomainRegistrationResponse($this->fixtureResponse('opensrs_register_response.xml'));
    }

    protected function sendTransferRequest(DomainTransferRequest $request): DomainTransferResponse
    {
        return new DomainTransferResponse($this->fixtureResponse('opensrs_transfer_response.xml'));
    }

    protected function sendGetRequest(DomainGetRequest $request): DomainGetResponse
    {
        $fixture = $request->getType() === 'domain_auth_info'
            ? 'opensrs_get_auth_info_response.xml'
            : 'opensrs_get_response.xml';

        return new DomainGetResponse($this->fixtureResponse($fixture));
    }

    protected function sendModifyRequest(DomainModifyRequest $request): DomainModifyResponse
    {
        return new DomainModifyResponse($this->fixtureResponse('opensrs_modify_response.xml'));
    }

    protected function sendNameserverUpdateRequest(DomainNameserverUpdateRequest $request): DomainModifyResponse
    {
        return new DomainModifyResponse($this->fixtureResponse('opensrs_modify_response.xml'));
    }

    private function fixtureResponse(string $file): HttpResponse
    {
        return new HttpResponse(
            200,
            ['Content-Type' => 'text/xml'],
            (string) file_get_contents(self::FIXTURES . '/' . $file),
        );
    }
}
