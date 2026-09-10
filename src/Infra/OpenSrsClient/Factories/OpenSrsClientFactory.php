<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenSrsClient\Factories;

use GuzzleHttp\Client;
use Waterfront\Domain\Domains\Models\OpenSrsProviderCredentials;
use Waterfront\Infra\OpenSrsClient\Messages\Connection;
use Waterfront\Infra\OpenSrsClient\OpenSrsClient;

class OpenSrsClientFactory
{
    public function __construct(
        private readonly OpenSrsClient $openSrsClient,
        private readonly Client $httpClient,
    ) {
    }

    /**
     * Without credentials the client bound in the service provider (environment
     * configuration, or the fake when APP_FAKE_DOMAIN_CLIENT is set) is returned.
     * With credentials a fresh account-bound client is built so long-lived queue
     * jobs never mutate a shared singleton across business units.
     */
    public function create(?OpenSrsProviderCredentials $credentials = null): OpenSrsClient
    {
        if ($credentials === null) {
            return $this->openSrsClient;
        }

        $connection = new Connection(
            $credentials->api_url,
            $credentials->username,
            $credentials->api_key,
        );

        return new OpenSrsClient($this->httpClient, $connection);
    }
}
