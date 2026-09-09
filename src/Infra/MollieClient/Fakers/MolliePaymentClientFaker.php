<?php

declare(strict_types=1);

namespace Waterfront\Infra\MollieClient\Fakers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Waterfront\Domain\Payments\Models\CreatePayment\PaymentParameters;
use Waterfront\Infra\MollieClient\MolliePaymentClient;

class MolliePaymentClientFaker extends MolliePaymentClient
{
    private ?int $desiredResponseCode = null;

    public function __construct()
    {
        $clientMock = new Client([
            'handler' => new MockHandler(),
        ]);
        parent::__construct($clientMock);
    }

    public function setDesiredResponseCode(int $desiredResponseCode): void
    {
        $this->desiredResponseCode = $desiredResponseCode;
    }

    /**
     * @inheritDoc
     */
    public function sendCreatePaymentRequest(PaymentParameters $parameters): ResponseInterface
    {
        $desiredResponseCode = $this->getDesiredResponseCode();
        $desiredResponseCode ??= 201;

        $body = include __DIR__ . '/../../../../tests/Infra/MollieClient/data/create_payment_response.php';

        return new Response($desiredResponseCode, [], json_encode($body, JSON_THROW_ON_ERROR));
    }

    /**
     * @inheritDoc
     */
    public function sendFetchPaymentRequest(string $externalId): ResponseInterface
    {
        $desiredResponseCode = $this->getDesiredResponseCode();
        $desiredResponseCode ??= 200;

        $body = include __DIR__ . '/../../../../tests/Infra/MollieClient/data/fetch_payment_response.php';

        return new Response($desiredResponseCode, [], json_encode($body, JSON_THROW_ON_ERROR));
    }

    private function getDesiredResponseCode(): ?int
    {
        return $this->desiredResponseCode;
    }
}
