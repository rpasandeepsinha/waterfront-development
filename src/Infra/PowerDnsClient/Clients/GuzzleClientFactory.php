<?php

declare(strict_types=1);

namespace Waterfront\Infra\PowerDnsClient\Clients;

use GuzzleHttp\Client;
use Waterfront\Infra\Configuration\ConfigurationInterface;

class GuzzleClientFactory
{
    public function __construct(
        private readonly ConfigurationInterface $configuration,
    ) {
    }

    public function create(): Client
    {
        return new Client(
            [
                'base_uri' => $this->configuration->getAsString('powerdnsclient.connection.api_url'),
                'headers' => [
                    'X-API-Key' => $this->configuration->getAsString('powerdnsclient.connection.api_key'),
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'connect_timeout ' => 5,
                'timeout' => 15,
                'http_errors' => false,
                'verify' => false,
            ],
        );
    }
}
