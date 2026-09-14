<?php

declare(strict_types=1);

namespace Waterfront\Infra\Microsoft\Graph\Factory;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use Microsoft\Graph\Core\Authentication\GraphPhpLeagueAccessTokenProvider;
use Microsoft\Graph\Core\Authentication\GraphPhpLeagueAuthenticationProvider;
use Microsoft\Graph\Core\NationalCloud;
use Microsoft\Kiota\Authentication\Oauth\ClientCredentialContext;
use Microsoft\Kiota\Http\GuzzleRequestAdapter;
use SandwaveIo\Microsoft\Graph\GraphServiceClient;
use Sentry\Tracing\GuzzleTracingMiddleware;
use Waterfront\Infra\Microsoft\Graph\Config\ConnectorConfig;

readonly class GraphServiceClientFactory
{
    public function __construct(
        private ConnectorConfig $config,
    ) {
    }

    public function createForCustomer(string $tenantId): GraphServiceClient
    {
        return $this->create($tenantId);
    }

    public function createForAdmin(): GraphServiceClient
    {
        return $this->create($this->config->tenantId);
    }

    private function create(string $tenantId): GraphServiceClient
    {
        $tokenRequestContext = new ClientCredentialContext(
            tenantId: $tenantId,
            clientId: $this->config->clientId,
            clientSecret: $this->config->clientSecret,
        );
        $scopes = [];

        $defaultRequestAdapter = new GuzzleRequestAdapter(
            authenticationProvider: GraphPhpLeagueAuthenticationProvider::createWithAccessTokenProvider(
                new GraphPhpLeagueAccessTokenProvider($tokenRequestContext, $scopes, NationalCloud::GLOBAL),
            ),
            guzzleClient: $this->getClient(),
        );
        $defaultRequestAdapter->setBaseUrl(NationalCloud::GLOBAL . '/v1.0');

        return new GraphServiceClient($defaultRequestAdapter);
    }

    private function getClient(): Client
    {
        $stack = new HandlerStack();
        $stack->setHandler(new CurlHandler());
        $stack->push(GuzzleTracingMiddleware::trace());

        return new Client(['handler' => $stack]);
    }
}
