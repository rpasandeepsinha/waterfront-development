<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenproviderClient\Factories;

use GuzzleHttp\Client;
use Illuminate\Contracts\Bus\Dispatcher;
use Waterfront\Domain\Domains\Models\OpenproviderProviderCredentials;
use Waterfront\Infra\OpenproviderClient\Messages\Connection;
use Waterfront\Infra\OpenproviderClient\OpenproviderClient;

class OpenproviderClientFactory
{
    public function __construct(
        private readonly OpenproviderClient $openproviderClient,
        private readonly Client $httpClient,
        private readonly Dispatcher $jobDispatcher,
    ) {
    }

    public function create(?OpenproviderProviderCredentials $credentials = null): OpenproviderClient
    {
        if ($credentials === null) {
            return $this->openproviderClient;
        }

        $connection = new Connection(
            $credentials->api_url,
            $credentials->username,
            $credentials->password
        );

        return new OpenproviderClient(
            $this->httpClient,
            $connection,
            $this->jobDispatcher
        );
    }
}
