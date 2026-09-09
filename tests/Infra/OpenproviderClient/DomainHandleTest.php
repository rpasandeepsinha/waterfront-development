<?php

declare(strict_types=1);

namespace Tests\Infra\OpenproviderClient;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Waterfront\Domain\Domains\DTO\HandleParameters;
use Waterfront\Infra\OpenproviderClient\Messages\Connection;
use Waterfront\Infra\OpenproviderClient\Messages\DomainHandleRequest;
use Waterfront\Infra\OpenproviderClient\Messages\DomainHandleResponse;

#[CoversClass(DomainHandleRequest::class)]
#[CoversClass(DomainHandleResponse::class)]
class DomainHandleTest extends TestCase
{
    #[Test]
    public function createHandle(): void
    {
        $parameters = $this->getParameters();

        $request = new DomainHandleRequest(new Client(), new Connection('https://test.nl', 'test-user', 'password'));
        $request->setParameters($parameters);

        $requestXml = file_get_contents(__DIR__ . '/data/openprovider_handle_request.xml');

        self::assertSame($requestXml, $request->getXml(), ' - domain handle xml created correctly');
    }

    #[Test]
    public function processResponseXml(): void
    {
        $response = new Response(
            200,
            ['Content-Type' => 'text/xml'],
            (string) file_get_contents(__DIR__ . '/data/openprovider_handle_response.xml')
        );

        $response = new DomainHandleResponse($response);
        $handleId = $response->getResult();

        self::assertTrue($response->isSuccess());
        self::assertSame('PW901812-NL', $handleId);
    }

    #[Test]
    public function processFailedResponseXml(): void
    {
        $response = new Response(
            200,
            ['Content-Type' => 'text/xml'],
            (string) file_get_contents(__DIR__ . '/data/openprovider_handle_failed_response.xml')
        );

        $response = new DomainHandleResponse($response);

        self::assertFalse($response->isSuccess());
        self::assertSame(141, $response->getResponseCode());
        self::assertSame('Controleer het faxnummer, en verbeter de landcode.', $response->getReason());
    }

    /**
     * Prepare a parameters object for the domain registration.
     */
    private function getParameters(): HandleParameters
    {
        $parameters = include 'data/handle.php';

        return HandleParameters::create($parameters);
    }
}
