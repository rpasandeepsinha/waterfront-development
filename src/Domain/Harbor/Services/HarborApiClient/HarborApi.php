<?php

declare(strict_types=1);

namespace Waterfront\Domain\Harbor\Services\HarborApiClient;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SandwaveIo\HarborMessages\Message\DebtorInvoiceLines;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Domain\Harbor\Exceptions\HarborApiConfigException;
use Waterfront\Domain\Harbor\Exceptions\HarborApiResponseException;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Support\Enums\LoggingContextKeys;

class HarborApi
{
    /**
     * @throws HarborApiConfigException
     */
    public function __construct(
        private readonly Client $client,
        private readonly ConfigurationInterface $config,
        private readonly LoggerInterface $logger,
    ) {
        $this->checkMandatoryConfigs();
    }

    /**
     * @throws GuzzleException
     * @throws HarborApiResponseException
     * @throws JsonException
     */
    public function sendCredit(DebtorInvoiceLines $creditInvoiceLinesMessage): void
    {
        $response = $this->client->post('invoice-line/credit', $this->getDefaultHeaders() + [RequestOptions::JSON => [
                'creditInvoiceLinesMessage' => $creditInvoiceLinesMessage->toArray(),
            ],
        ]);

        $this->validateResponse($response);
    }

    /**
     * @throws GuzzleException
     * @throws HarborApiResponseException
     * @throws JsonException
     */
    public function sendDowngrade(int $originalInvoiceId, DebtorInvoiceLines $creditInvoiceLineMessage, DebtorInvoiceLines $newInvoiceLineMessage): void
    {
        $response = $this->client->post('invoice-line/downgrade', $this->getDefaultHeaders() + [RequestOptions::JSON => [
                'originalInvoiceLineMessageId' => $originalInvoiceId,
                'creditInvoiceLinesMessage' => $creditInvoiceLineMessage->toArray(),
                'newInvoiceLinesMessage' => $newInvoiceLineMessage->toArray(),
            ],
        ]);

        $this->validateResponse($response);
    }

    /**
     * @throws HarborApiConfigException
     */
    private function checkMandatoryConfigs(): void
    {
        if (strlen($this->config->getAsString('harbor-api-client.connection.api_url')) === 0) {
            throw new HarborApiConfigException('harbor-api-client.connection.api_url');
        }

        if (strlen($this->config->getAsString('harbor-api-client.connection.api_endpoint_prefix')) === 0) {
            throw new HarborApiConfigException('harbor-api-client.connection.api_endpoint_prefix');
        }

        if (strlen($this->config->getAsString('harbor-api-client.connection.api_authorization_header')) === 0) {
            throw new HarborApiConfigException('harbor-api-client.connection.api_authorization_header');
        }
    }

    private function validateResponse(ResponseInterface $response): void
    {
        if ($response->getStatusCode() !== Response::HTTP_OK) {
            /** @var array<string, mixed> $data */
            $data = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
            $message = $data['message'];

            if (! is_string($message)) {
                $errorMessage = 'Credit response message from Harbor is not a string!';

                $this->logger->error($errorMessage, [
                    LoggingContextKeys::RESPONSE_DATA => $response->getBody()->getContents(),
                ]);

                throw new RuntimeException($errorMessage);
            }

            throw HarborApiResponseException::responseError(
                $response->getStatusCode(),
                $message,
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getDefaultHeaders(): array
    {
        return [
            'base_uri' => $this->config->getAsString('harbor-api-client.connection.api_url') .
                $this->config->getAsString('harbor-api-client.connection.api_endpoint_prefix'),
            'headers' => ['Authorization' => 'Basic ' . base64_encode($this->config->getAsString('harbor-api-client.connection.api_authorization_header'))],
            'verify' => $this->config->getAsBoolean('harbor-api-client.connection.verify_ssl'),
            'http_errors' => false,
            'debug' => $this->config->getAsBoolean('harbor-api-client.connection.debug_mode'),
        ];
    }
}
