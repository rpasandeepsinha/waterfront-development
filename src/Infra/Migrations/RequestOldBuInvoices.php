<?php

declare(strict_types=1);

namespace Waterfront\Infra\Migrations;

use Carbon\CarbonImmutable;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Config;
use Waterfront\Infra\Configuration\ConfigurationException;

class RequestOldBuInvoices
{
    public function __construct(
        private readonly Client $client,
    ) {
    }

    /**
     * @throws GuzzleException
     */
    public function handle(
        string $referenceNumber,
        string $buName,
        CarbonImmutable $fromDate,
        CarbonImmutable $toDate,
    ): void {
        $url = $this->getEndpoint($buName);
        $apikey = $this->getApiKey($buName);

        $this->client->post($url, [
            'headers' => [
                'x-apikey' => $apikey,
                'content-type' => 'application/json',
            ],
            'body' => json_encode(['customer' => $referenceNumber, 'start_date' => $fromDate, 'end_date' => $toDate]),
        ]);
    }

    private function getEndpoint(string $value): string
    {
        $endpoints = [
            'versio' => 'https://versio.nl/api/v1/invoice/generate-bulk-invoice',
            'yourhosting' => 'https://crmv4.yhkantoor.nl/api/v1/invoice/generate-bulk-invoice',
        ];

        return $endpoints[strtolower($value)] ?? throw new Exception("bad value received: $value");
    }

    private function getApiKey(string $buName): string
    {
        $lowerString = strtolower($buName);

        $key = match ($lowerString) {
            'versio' => Config::get('old-bu.versio1_0_api_key'),
            'yourhosting' => Config::get('old-bu.yourhosting1_0_api_key'),
            default => throw new Exception("bad value received: $lowerString"),
        };

        if ($key === null) {
            throw new ConfigurationException("Api key not set for $lowerString");
        }

        assert(is_string($key));

        return $key;
    }
}
