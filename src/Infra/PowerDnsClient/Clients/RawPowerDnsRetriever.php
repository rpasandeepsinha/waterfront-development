<?php

declare(strict_types=1);

namespace Waterfront\Infra\PowerDnsClient\Clients;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Waterfront\Infra\Configuration\ConfigurationInterface;

class RawPowerDnsRetriever
{
    private readonly string $apiBaseUrl;

    private readonly string $apiKey;

    public function __construct(
        private readonly ConfigurationInterface $configuration
    ) {
        $this->apiBaseUrl = $this->configuration->getAsString('powerdnsclient.connection.api_url');
        $this->apiKey = $this->configuration->getAsString('powerdnsclient.connection.api_key');
    }

    public function getPowerDnsVersion(): string
    {
        $serversResponse = $this->powerDnsRequest()
            ->get('api/v1/servers');

        if ($serversResponse->header('Content-Type') !== 'application/json') {
            return $serversResponse->body();
        }

        /** @var array<int, array<string, string>> $serversResponseArray */
        $serversResponseArray = $serversResponse->json();

        /** @var array<string, string>|null $localhostServer */
        $localhostServer = Arr::first($serversResponseArray, fn (array $server): bool => $server['id'] === 'localhost');

        /** @var string $version */
        $version = $localhostServer !== null ? $localhostServer['version'] : 'unknown';

        return $version;
    }

    /**
     * @return array<string, string>|string
     */
    public function getPowerDnsZoneResponseBody(string $domain): array|string
    {
        $zoneResponse = $this->powerDnsRequest()
            ->get('api/v1/servers/localhost/zones/' . urlencode($domain));

        // If it's json we want some nice formatting, else show the raw body
        /** @var array<string, string>|string $zoneResponseBody */
        $zoneResponseBody = $zoneResponse->header('Content-Type') === 'application/json' ?
            $zoneResponse->json() : $zoneResponse->body();

        return $zoneResponseBody;
    }

    private function powerDnsRequest(): PendingRequest
    {
        return Http::baseUrl($this->apiBaseUrl)
            ->withHeader('X-API-Key', $this->apiKey);
    }
}
