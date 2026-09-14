<?php

declare(strict_types=1);

namespace Waterfront\Infra\SaloonClient\Faking;

use Saloon\Http\Faking\Fixture;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\OAuth2\GetClientCredentialsTokenRequest;

/**
 * A simple extension of the default Saloon MockClient fake
 * that proxies all given request but adds an oAuth token
 * request to the array that can be customized if needed.
 *
 * @phpstan-ignore-next-line
 */
class OAuthMockClient extends MockClient
{
    /**
     * @param array<MockResponse|Fixture|callable> $mockData
     * @param ?array<mixed>                        $oAuthBody
     */
    public function __construct(array $mockData = [], ?array $oAuthBody = null, int $oAuthStatus = 200)
    {
        $oAuthBody ??= ['access_token' => '123', 'expires_in' => 300];

        $oAuthMock = [
            GetClientCredentialsTokenRequest::class => MockResponse::make(body: $oAuthBody, status: $oAuthStatus),
        ];

        parent::__construct($oAuthMock + $mockData);
    }
}
