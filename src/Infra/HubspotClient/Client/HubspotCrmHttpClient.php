<?php

declare(strict_types=1);

namespace Waterfront\Infra\HubspotClient\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\RequestOptions;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Sentry\Tracing\GuzzleTracingMiddleware;
use Waterfront\Infra\HubspotClient\DTO\HubspotConfigDTO;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotAuthenticationException;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotConflictException;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotJsonException;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotThrottledException;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotUnexpectedResponseException;

class HubspotCrmHttpClient
{
    private readonly Client $client;

    public function __construct(
        private readonly HubspotConfigDTO $config,
        Client|null $client = null
    ) {
        $stack = HandlerStack::create();
        $stack->setHandler(new CurlHandler());
        $stack->push(GuzzleTracingMiddleware::trace());

        $this->client = $client ?? new Client([
            'handler' => $stack,
            'base_uri' => $this->config->baseUrl,
        ]);
    }

    /**
     * @throws HubspotConflictException|HubspotThrottledException|HubspotUnexpectedResponseException|HubspotAuthenticationException|HubspotJsonException
     * @throws JsonException
     * @throws GuzzleException
     *
     * @return array<mixed>
     */
    public function get(string $uri): array
    {
        self::assertAuthenticated();
        $response = $this->client->get($uri, $this->getOptions());
        return $this->parseResponse($response);
    }

    /**
     * @param array<mixed> $body
     *
     * @throws JsonException
     * @throws HubspotConflictException|HubspotThrottledException|HubspotUnexpectedResponseException|HubspotAuthenticationException|HubspotJsonException
     * @throws GuzzleException
     *
     * @return array<mixed>
     */
    public function post(string $uri, array $body): array
    {
        self::assertAuthenticated();
        $response = $this->client->post($uri, $this->getOptions(body: $body));
        return $this->parseResponse($response);
    }

    /**
     * @param array<mixed> $body
     *
     * @throws JsonException
     * @throws HubspotConflictException|HubspotThrottledException|HubspotUnexpectedResponseException|HubspotAuthenticationException|HubspotJsonException
     * @throws GuzzleException
     *
     * @return array<mixed>
     */
    public function patch(string $uri, array $body): array
    {
        self::assertAuthenticated();
        $response = $this->client->patch($uri, $this->getOptions(body: $body));
        return $this->parseResponse($response);
    }

    /**
     * @param array<mixed> $body
     *
     * @throws HubspotConflictException|HubspotThrottledException|HubspotUnexpectedResponseException|HubspotAuthenticationException|HubspotJsonException|JsonException
     * @throws GuzzleException
     *
     * @return array<mixed>
     */
    public function put(string $uri, array $body): array
    {
        self::assertAuthenticated();
        $response = $this->client->put($uri, $this->getOptions(body: $body));
        return $this->parseResponse($response);
    }

    /**
     * @throws HubspotConflictException|HubspotThrottledException|HubspotUnexpectedResponseException|HubspotJsonException
     *
     * @return array<mixed>
     */
    private function parseResponse(ResponseInterface $response): array
    {
        if ($response->getStatusCode() >= 400) {
            throw match ($response->getStatusCode()) {
                401     => new HubspotConflictException('Invalid API credentials'),
                403     => new HubspotConflictException('Forbidden, cannot access this part of the API with the given credentials'),
                409     => new HubspotConflictException(sprintf('Hubspot conflict, reason: %s', $response->getBody()->getContents())),
                429     => new HubspotThrottledException('Too many requests, was the API request quotum reached?'),
                default => new HubspotUnexpectedResponseException(sprintf('Unexpected hubspot response (status=%s): %s', $response->getStatusCode(), $response->getBody()->getContents())),
            };
        }
        $body = $response->getBody()->getContents();
        try {
            $json = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
            assert(is_array($json));
            return $json;
        } catch (JsonException $exception) {
            throw new HubspotJsonException('Decoding data from HubSpot failed', $exception->getCode(), $exception);
        }
    }

    /**
     * @param array<mixed>|null $body
     *
     * @throws JsonException
     *
     * @return array<string,mixed>
     */
    private function getOptions(array|null $body = null): array
    {
        $options = [
            RequestOptions::HEADERS => $this->getHeaders(),
            RequestOptions::HTTP_ERRORS => false,
        ];

        if ($body !== null) {
            $options[RequestOptions::BODY] = json_encode($body, JSON_THROW_ON_ERROR);
        }
        return $options;
    }

    /** @return array<string,string> */
    private function getHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => sprintf('Bearer %s', $this->config->accessToken),
            'Content-Type' => 'application/json',
        ];
    }

    private function assertAuthenticated(): void
    {
        if ($this->config->accessToken === '') {
            throw new HubspotAuthenticationException('Hubspot credentials not configured');
        }
    }
}
