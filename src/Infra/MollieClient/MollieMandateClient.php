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
use Waterfront\Infra\MollieClient\DTO\Mandates\MollieMandateCreateInterface;
use Waterfront\Infra\MollieClient\DTO\Mandates\MollieMandateResponseDTO;
use Waterfront\Infra\MollieClient\Exceptions\MollieMandateApiException;

class MollieMandateClient
{
    private readonly string $uri;

    public function __construct(
        ConfigurationInterface $config,
        private readonly SerializerInterface $serializer,
        #[SensitiveParameter]private readonly string $apiKey
    ) {
        $this->uri = $config->getAsString('mollieclient.credentials.api_url');
    }

    /**
     * @throws MollieMandateApiException
     */
    public function createMandate(string $mollieCustomerId, MollieMandateCreateInterface $mollieMandateCreateDTO): MollieMandateResponseDTO
    {
        $payload = $this->serializer->serialize($mollieMandateCreateDTO, 'json');

        $response = $this->request()
            ->withBody($payload)
            ->post("customers/$mollieCustomerId/mandates");

        return $this->serializer->deserialize($response, MollieMandateResponseDTO::class, 'json');
    }

    /**
     * @throws MollieMandateApiException
     */
    public function getMandate(string $mollieCustomerId, string $mollieMandateId): MollieMandateResponseDTO
    {
        $response = $this->request()->get("customers/$mollieCustomerId/mandates/$mollieMandateId");

        return $this->serializer->deserialize($response->body(), MollieMandateResponseDTO::class, 'json');
    }

    /**
     * @throws MollieMandateApiException
     */
    public function revokeMandate(string $mollieCustomerId, string $mollieMandateId): void
    {
        $this->request()->delete("customers/$mollieCustomerId/mandates/$mollieMandateId");
    }

    /**
     * @throws MollieMandateApiException
     *
     * @return array<int, MollieMandateResponseDTO>
     */
    public function listMandates(string $mollieCustomerId): array
    {
        $response = $this->request()->get("customers/$mollieCustomerId/mandates");

        $payload = $response->json('_embedded.mandates');
        assert(is_array($payload));

        $mandates = json_encode($payload, JSON_THROW_ON_ERROR);

        $mandatesList = $this->serializer->deserialize($mandates, MollieMandateResponseDTO::class . '[]', 'json');
        assert(is_array($mandatesList));

        return $mandatesList;
    }

    /**
     * @throws MollieMandateApiException
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

                throw new MollieMandateApiException(
                    status: $response->status(),
                    title: $payload['title'] ?? '',
                    detail: $payload['detail'] ?? '',
                    field: $payload['field'] ?? null,
                    previous: $exception
                );
            });
    }
}
