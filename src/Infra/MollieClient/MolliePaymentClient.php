<?php

declare(strict_types=1);

namespace Waterfront\Infra\MollieClient;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Waterfront\Domain\Payments\Interfaces\PaymentInterface;
use Waterfront\Domain\Payments\Models\CreatePayment\PaymentParameters;
use Waterfront\Domain\Payments\Models\Result;

class MolliePaymentClient implements PaymentInterface
{
    public function __construct(
        protected Client $client,
    ) {
    }

    /**
     * @throws JsonException
     * @throws GuzzleException
     */
    public function createPayment(PaymentParameters $parameters): Result
    {
        $response = $this->sendCreatePaymentRequest($parameters);
        $code = $response->getStatusCode();

        if (201 !== $code) {
            return Result::create([
                'status' => Result::STATUS_ERROR,
                'errorCode' => $response->getStatusCode(),
                'errorMessage' => $response->getReasonPhrase(),
            ]);
        }

        $responseBodyJson = (string) $response->getBody();
        $responseBody = json_decode($responseBodyJson, true, 512, JSON_THROW_ON_ERROR);

        Log::info(self::class . '::createPayment - Payment was successfully created.');

        return Result::create([
            'status' => Result::STATUS_OK,
            'paymentData' => $responseBody,
        ]);
    }

    /**
     * @throws JsonException
     * @throws GuzzleException
     */
    public function fetchPayment(string $externalId): Result
    {
        $response = $this->sendFetchPaymentRequest($externalId);
        $code = $response->getStatusCode();

        if (200 !== $code) {
            return Result::create([
                'status' => Result::STATUS_ERROR,
                'errorCode' => $response->getStatusCode(),
                'errorMessage' => $response->getReasonPhrase(),
            ]);
        }

        $responseBodyJson = (string) $response->getBody();
        $responseBody = json_decode($responseBodyJson, true, 512, JSON_THROW_ON_ERROR);

        return Result::create([
            'status' => Result::STATUS_OK,
            'paymentData' => $responseBody,
        ]);
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     */
    protected function sendCreatePaymentRequest(PaymentParameters $parameters): ResponseInterface
    {
        return $this->client->request('POST', 'payments', [
            'body' => json_encode($parameters->toArray(), JSON_THROW_ON_ERROR),
            'http_errors' => false,
        ]);
    }

    /**
     * @throws GuzzleException
     */
    protected function sendFetchPaymentRequest(string $externalId): ResponseInterface
    {
        return $this->client->request('GET', 'payments/' . $externalId, [
            'http_errors' => false,
        ]);
    }
}
