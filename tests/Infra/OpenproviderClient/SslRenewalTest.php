<?php

declare(strict_types=1);

namespace Tests\Infra\OpenproviderClient;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Bus\Dispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Waterfront\Infra\OpenproviderClient\Fakers\OpenproviderClientFaker;
use Waterfront\Infra\OpenproviderClient\Messages\Connection;
use Waterfront\Infra\OpenproviderClient\Messages\SslRenewRequest;
use Waterfront\Infra\OpenproviderClient\Messages\SslRenewResponse;

#[CoversClass(SslRenewRequest::class)]
class SslRenewalTest extends TestCase
{
    #[Test]
    public function createXml(): void
    {
        $connection = new Connection('https://test.nl', 'test-user', 'password');

        $client = new OpenproviderClientFaker(
            new Client(),
            $connection,
            $this->createStub(Dispatcher::class),
        );

        $request = $client->getRenewSslRequest(2480);

        $requestXml = (string) file_get_contents(__DIR__ . '/data/openprovider_ssl_renew_request.xml');

        self::assertXmlStringEqualsXmlString(
            $requestXml,
            $request->getXml(),
            'The xml of the request does not match the expected values'
        );
    }

    #[Test]
    public function processResponseXml(): void
    {
        $response = new Response(
            200,
            ['Content-Type' => 'text/xml'],
            (string) file_get_contents(__DIR__ . '/data/openprovider_ssl_renew_response.xml')
        );

        $sslResponse = new SslRenewResponse($response);

        self::assertTrue($sslResponse->isSuccess());
        self::assertSame(2480, $sslResponse->getCertificateId(), 'The certificate id should be 2480, but it is not.');
    }
}
