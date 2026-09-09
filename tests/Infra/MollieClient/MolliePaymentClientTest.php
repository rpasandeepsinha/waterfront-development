<?php

declare(strict_types=1);

namespace Tests\Infra\MollieClient;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Waterfront\Domain\Payments\Models\CreatePayment\PaymentParameters;
use Waterfront\Domain\Payments\Models\Result;
use Waterfront\Infra\MollieClient\Fakers\MolliePaymentClientFaker;
use Waterfront\Infra\MollieClient\MolliePaymentClient;

#[CoversClass(MolliePaymentClientFaker::class)]
#[CoversClass(MolliePaymentClient::class)]
class MolliePaymentClientTest extends TestCase
{
    #[Test]
    public function createPaymentSuccess(): void
    {
        $client = new MolliePaymentClientFaker();
        $client->setDesiredResponseCode(201);
        $parameters = new PaymentParameters(
            currency: 'EUR',
            amount: '0.01',
            description: 'Description',
            redirectUrl: 'https://test.com/redirect',
            webhookUrl: 'https://test.com/webhook',
        );
        $expectedData = include __DIR__ . '/data/create_payment_response.php';

        $result = $client->createPayment($parameters);

        self::assertSame(Result::STATUS_OK, $result->getStatus());
        self::assertSame($expectedData, $result->getPaymentData());
    }

    #[Test]
    public function createPaymentFailure(): void
    {
        $client = new MolliePaymentClientFaker();
        $client->setDesiredResponseCode(401);
        $parameters = new PaymentParameters(
            currency: 'EUR',
            amount: '0.01',
            description: 'Description',
            redirectUrl: 'https://test.com/redirect',
            webhookUrl: 'https://test.com/webhook',
        );

        $result = $client->createPayment($parameters);

        self::assertSame(Result::STATUS_ERROR, $result->getStatus());
    }

    #[Test]
    public function fetchPaymentSuccess(): void
    {
        $client = new MolliePaymentClientFaker();
        $client->setDesiredResponseCode(200);

        $result = $client->fetchPayment('my_id');

        self::assertSame(Result::STATUS_OK, $result->getStatus());

        $expectedData = include __DIR__ . '/data/fetch_payment_response.php';
        self::assertSame($expectedData, $result->getPaymentData());
    }

    #[Test]
    public function fetchPaymentFailure(): void
    {
        $client = new MolliePaymentClientFaker();
        $client->setDesiredResponseCode(404);

        $result = $client->fetchPayment('my_id');

        self::assertSame(Result::STATUS_ERROR, $result->getStatus());
    }
}
