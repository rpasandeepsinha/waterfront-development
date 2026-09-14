<?php

declare(strict_types=1);

namespace Waterfront\Infra\PuzzelClient;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Waterfront\Infra\PuzzelClient\DTO\PuzzelCreateTicketRequest;
use Waterfront\Infra\PuzzelClient\DTO\PuzzelPublicCredentials;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class PuzzelPublicClient
{
    private const string CACHE_KEY = 'puzzel_public_api_access_token';
    private const int CACHE_TTL_SECONDS = 86400; // 1 day

    private ?string $bearerToken = null;

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly PuzzelPublicCredentials $credentials,
        private readonly CacheRepository $cache,
    ) {
    }

    public function createTicket(PuzzelCreateTicketRequest $request): void
    {
        if ($this->bearerToken === null) {
            $cached = $this->cache->get(self::CACHE_KEY);
            $this->bearerToken = is_string($cached) ? $cached : $this->authorize();
        }

        $payload = array_filter(
            [
                'subject' => $request->subject,
                'body' => $request->body,
                'priority' => $request->priority,
                'status' => $request->status,
                'team' => $request->team,
                'user' => $request->user,
                'tags' => $request->tags,
                'categories' => $request->categories,
                'customer' => array_filter(
                    [
                        'email' => $request->customer->email,
                        'first_name' => $request->customer->firstName,
                        'last_name' => $request->customer->lastName,
                        'phone_number' => $request->customer->phoneNumber,
                    ],
                    fn (mixed $value) => $value !== null,
                ),
            ],
            fn (mixed $value) => $value !== null,
        );

        try {
            $this->httpClient->request('POST', 'api/v1/tickets', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->bearerToken,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => $payload,
            ]);
        } catch (BadResponseException $e) {
            $responseBody = (string) $e->getResponse()->getBody();
            $statusCode = $e->getResponse()->getStatusCode();

            $this->logger->debug('Puzzel create ticket response', [
                LoggingContextKeys::RESPONSE_CODE => $statusCode,
                LoggingContextKeys::RESPONSE_DATA => $responseBody,
            ]);

            throw new RuntimeException(
                sprintf('Puzzel Public API request failed with status %d: %s', $statusCode, $responseBody),
                $statusCode,
                $e,
            );
        }
    }

    private function authorize(): string
    {
        try {
            $response = $this->httpClient->request('POST', 'api/v1/oauth_tokens', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => [
                    'client_id' => $this->credentials->clientId,
                    'client_secret' => $this->credentials->clientSecret,
                ],
            ]);

            $data = json_decode((string) $response->getBody(), true);
            Assert::isArray($data);
            Assert::string($data['access_token']);
            Assert::integer($data['expires_in']);

            $tokenTtl = max(0, $data['expires_in'] - time());
            $ttl = min(self::CACHE_TTL_SECONDS, $tokenTtl);

            $this->cache->put(self::CACHE_KEY, $data['access_token'], $ttl);

            return $this->bearerToken = $data['access_token'];
        } catch (BadResponseException $e) {
            $responseBody = (string) $e->getResponse()->getBody();
            $statusCode = $e->getResponse()->getStatusCode();

            $this->logger->debug('Puzzel authorize response', [
                LoggingContextKeys::RESPONSE_CODE => $statusCode,
                LoggingContextKeys::RESPONSE_DATA => $responseBody,
            ]);

            throw new RuntimeException(
                sprintf('Puzzel Public API authorize failed with status %d: %s', $statusCode, $responseBody),
                $statusCode,
                $e,
            );
        }
    }
}
