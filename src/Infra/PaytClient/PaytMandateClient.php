<?php

declare(strict_types=1);

namespace Waterfront\Infra\PaytClient;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Psr\Log\LoggerInterface;
use SensitiveParameter;
use Symfony\Component\Serializer\Serializer;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\PaytClient\DTO\PaytMandateCreateDTO;
use Waterfront\Infra\PaytClient\DTO\PaytMandateCreateRequestDTO;
use Waterfront\Infra\PaytClient\DTO\PaytMandateResponseDTO;
use Waterfront\Infra\PaytClient\Exceptions\PaytMandateApiException;
use Waterfront\Infra\PaytClient\Exceptions\PaytMandateIdNotNumericException;
use Waterfront\Support\Enums\LoggingContextKeys;

class PaytMandateClient
{
    private readonly string $uri;

    private readonly string $administrationId;

    public function __construct(
        ConfigurationInterface $config,
        private readonly Serializer $serializer,
        private readonly LoggerInterface $logger,
        #[SensitiveParameter]
        private readonly string $apiKey,
    ) {
        $this->uri = $config->getAsString('paytclient.api_url');
        $this->administrationId = $config->getAsString('paytclient.administration_id');
    }

    /**
     * @throws PaytMandateApiException
     */
    public function createPspMandate(PaytMandateCreateDTO $pspMandate): PaytMandateResponseDTO
    {
        $pspMandateCreateRequestDTO = new PaytMandateCreateRequestDTO(
            administrationId: $this->administrationId,
            pspMandates: [
                $pspMandate,
            ],
            fields: [
                'only' => [
                    'id',
                    'mandate_identifier',
                ],
            ],
        );

        $payload = $this->serializer->serialize($pspMandateCreateRequestDTO, 'json');

        $response = $this->request()->withBody($payload)->post('psp_mandates');

        try {
            /** @var PaytMandateResponseDTO $pspMandate */
            $pspMandate = $this->serializer->denormalize(
                $response->json('0'),
                PaytMandateResponseDTO::class,
            );
        } finally {
            $this->logger->debug(
                'Create Payt mandate response',
                [
                    LoggingContextKeys::RESPONSE_CODE => $response->status(),
                    LoggingContextKeys::RESPONSE_DATA => $response->body(),
                ],
            );
        }

        return $pspMandate;
    }

    /**
     * @throws PaytMandateApiException
     *
     * @return array<int, PaytMandateResponseDTO>
     */
    public function getPspMandatesByDebtorNumber(string $debtorNumber): array
    {
        $response = $this->request()
            ->withQueryParameters([
                'administration_id' => $this->administrationId,
                'debtor_numbers' => $debtorNumber,
            ])
            ->get('psp_mandates');

        /** @var array<int, PaytMandateResponseDTO> $pspMandates */
        $pspMandates = $this->serializer->denormalize(
            $response->json('data'),
            PaytMandateResponseDTO::class . '[]',
        );

        return $pspMandates;
    }

    /**
     * @throws PaytMandateIdNotNumericException
     * @throws PaytMandateApiException
     */
    public function getPspMandatesByPaytId(string $paytMandateId): ?PaytMandateResponseDTO
    {
        if (! is_numeric($paytMandateId)) {
            throw new PaytMandateIdNotNumericException($paytMandateId);
        }

        $response = $this->request()
            ->withQueryParameters([
                'administration_id' => $this->administrationId,
                'ids' => $paytMandateId,
            ])
            ->get('psp_mandates');

        /** @var array<int, PaytMandateResponseDTO> $pspMandates */
        $pspMandates = $this->serializer->denormalize(
            $response->json('data'),
            PaytMandateResponseDTO::class . '[]',
        );

        return count($pspMandates) === 1 ? $pspMandates[0] : null;
    }

    /**
     * @throws PaytMandateApiException
     */
    private function request(): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
        ])->baseUrl($this->uri)->throw(function (Response $response, RequestException $exception): never {
            /** @var string $message */
            $message = $response->json('error.message');

            throw new PaytMandateApiException(
                $message,
                $response->status(),
                $exception,
            );
        });
    }
}
