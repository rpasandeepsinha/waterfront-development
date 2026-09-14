<?php

declare(strict_types=1);

namespace Waterfront\Domain\Lighthouse\Helper;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;
use Waterfront\Domain\Lighthouse\Exceptions\LighthouseException;
use Waterfront\Domain\Lighthouse\Exceptions\ResourceNotFoundException;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Support\Enums\Environment;

class RequestHelper
{
    public function __construct(
        private readonly ConfigurationInterface $configuration,
        private readonly Environment $environment,
    ) {
    }

    /**
     * @param array<string,mixed> $data
     *
     * @throws LighthouseException
     * @throws JsonException
     */
    public function request(string $endpoint, array $data, string $method): Response
    {
        $http = $this->buildRequest();

        $response = $this->execute($http, $endpoint, $data, $method);

        if (! $response->successful()) {
            Log::error(
                sprintf(
                    "%s::createLighthouseIdentity - Unable to store identity '%s' at Lighthouse with response: '%s'",
                    self::class,
                    '',
                    $response->body(),
                ),
            );
        }

        return $response;
    }

    private function buildRequest(): PendingRequest
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        $clientId = $this->configuration->getAsString('app.hydra.client_id');
        $clientSecret = $this->configuration->getAsString('app.hydra.secret');

        $headers['Authorization'] = 'Basic ' . base64_encode(sprintf('%s:%s', $clientId, $clientSecret));

        return Http::withHeaders($headers)->withOptions(
            [
                'verify' => $this->environment !== Environment::DEV && $this->environment !== Environment::TST,
            ],
        );
    }

    /**
     * @param array<string,mixed> $data
     *
     * @throws LighthouseException
     */
    private function execute(PendingRequest $http, string $endpoint, array $data, string $method): Response
    {
        $response = match (strtolower($method)) {
            'post' => $http->post($endpoint, $data),
            'put' => $http->put($endpoint, $data),
            'get' => $http->get($endpoint, $data),
            default => throw new LighthouseException('http method not supported'),
        };

        if ($response->status() === \Symfony\Component\HttpFoundation\Response::HTTP_NOT_FOUND) {
            throw new ResourceNotFoundException();
        }

        if (! $response->successful()) {
            throw new LighthouseException(
                sprintf(
                    'Error in request %s: %s',
                    $endpoint,
                    $response->body(),
                ),
            );
        }

        return $response;
    }
}
