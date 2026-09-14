<?php

declare(strict_types=1);

namespace Tests\Infra\OpenproviderClient;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Bus\Dispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Waterfront\Domain\Ssl\Interfaces\Models\Parameters;
use Waterfront\Infra\OpenproviderClient\Fakers\OpenproviderClientFaker;
use Waterfront\Infra\OpenproviderClient\Messages\Connection;
use Waterfront\Infra\OpenproviderClient\Messages\SslCreateRequest;
use Waterfront\Infra\OpenproviderClient\Messages\SslCreateResponse;

#[CoversClass(SslCreateRequest::class)]
class SslCreationTest extends TestCase
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

        $parameters = $this->getParameters();
        $handles = $client->createHandles($parameters->getCustomer());

        $request = $client->getCreateSslRequest($parameters, $handles);

        $requestXml = (string) file_get_contents(__DIR__ . '/data/openprovider_ssl_create_request.xml');

        self::assertXmlStringEqualsXmlString(
            str_replace(['  ', "\n"], '', $requestXml),
            str_replace(['  ', "\n"], '', $request->getXml()),
            'The xml of the request does not match the expected values',
        );
    }

    #[Test]
    public function processResponseXml(): void
    {
        $response = new Response(
            200,
            ['Content-Type' => 'text/xml'],
            (string) file_get_contents(__DIR__ . '/data/openprovider_ssl_create_response.xml'),
        );

        $sslResponse = new SslCreateResponse($response);

        self::assertTrue($sslResponse->isSuccess());
        self::assertSame(2480, $sslResponse->getCertificateId(), 'The certificate id should be 2480, but it is not.');
    }

    /**
     * Prepare a parameters object for the ssl.
     */
    private function getParameters(): Parameters
    {
        $parameters = include 'data/ssl.php';

        return Parameters::create($parameters);
    }
}
