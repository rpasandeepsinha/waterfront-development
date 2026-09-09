<?php

declare(strict_types=1);

namespace Waterfront\Infra\MollieClient;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use SensitiveParameter;
use Symfony\Component\Serializer\SerializerInterface;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\MollieClient\DTO\Customers\MollieCustomerRequestDTO;
use Waterfront\Infra\MollieClient\DTO\Customers\MollieCustomerResponseDTO;
use Waterfront\Infra\MollieClient\Exceptions\MollieCustomerApiException;

class MollieCustomerClient
{
    private readonly string $uri;

    public function __construct(
        ConfigurationInterface $config,
        private readonly SerializerInterface $serializer,
        #[SensitiveParameter]private readonly string $apiKey,
    ) {
        $this->uri = $config->getAsString('mollieclient.credentials.api_url');
    }

    /**
     * @throws MollieCustomerApiException
     */
    public function getCustomerById(string $mollieCustomerId): MollieCustomerResponseDTO
    {
        $response = $this->request()->get(sprintf('customers/%s', $mollieCustomerId));

        return $this->serializer->deserialize($response->body(), MollieCustomerResponseDTO::class, 'json');
    }

    /**
     * @throws MollieCustomerApiException
     */
    public function createCustomer(MollieCustomerRequestDTO $mollieCustomer): MollieCustomerResponseDTO
    {
        $payload = $this->serializer->serialize($mollieCustomer, 'json');

        $response = $this->request()
            ->withBody($payload)
            ->post('customers');

        return $this->serializer->deserialize($response->body(), MollieCustomerResponseDTO::class, 'json');
    }

    /**
     * @throws MollieCustomerApiException
     */
    public function updateCustomer(string $mollieCustomerId, MollieCustomerRequestDTO $mollieCustomer): MollieCustomerResponseDTO
    {
        $payload = $this->serializer->serialize($mollieCustomer, 'json');

        $response = $this->request()
            ->withBody($payload)
            ->patch(sprintf('customers/%s', $mollieCustomerId));

        return $this->serializer->deserialize($response->body(), MollieCustomerResponseDTO::class, 'json');
    }

    /**
     * @throws MollieCustomerApiException
     */
    private function request(): PendingRequest
    {
        return Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])
            ->baseUrl($this->uri)
            ->throw(function (Response $response, RequestException $exception): never {
                /** @var array<string, string|null> $payload */
                $payload = $response->json();

                throw new MollieCustomerApiException(
                    status: $response->status(),
                    title: $payload['title'] ?? '',
                    detail: $payload['detail'] ?? '',
                    field: $payload['field'] ?? null,
                    previous: $exception
                );
            });
    }
}
