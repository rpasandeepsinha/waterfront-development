<?php

declare(strict_types=1);

namespace Tests\Infra\PleskClient;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\CreateCustomer\Result;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\PleskClient\Messages\Connection;
use Waterfront\Infra\PleskClient\Messages\CreateSite\CreateSiteRequest;
use Waterfront\Infra\PleskClient\Messages\CreateSite\CreateSiteResponse;
use Waterfront\Infra\PleskClient\Messages\CreateSite\CreateSiteResult;
use Waterfront\Infra\PleskClient\Services\HostingPackageClient;

#[CoversClass(CreateSiteRequest::class)]
#[CoversClass(CreateSiteResponse::class)]
#[CoversClass(CreateSiteResult::class)]
class CreateSiteTest extends IntegrationTestCase
{
    private LoggerInterface&Stub $loggerMock;

    public function setUp(): void
    {
        parent::setUp();

        $this->loggerMock = self::createStub(LoggerInterface::class);
    }

    #[Test]
    public function createSite(): void
    {
        $mock = new MockHandler([
            function (RequestInterface $request, $options) {
                self::assertSame((string) file_get_contents(__DIR__ . '/data/plesk_create_site_request.xml'), (string) $request->getBody());
                return new Response(200, [], (string) file_get_contents(__DIR__ . '/data/plesk_create_site_response.xml'));
            },
        ]);
        $handlerStack = HandlerStack::create($mock);

        $client = new Client(['handler' => $handlerStack]);
        $connection = new Connection();
        $connection->setApiUrl('https://test');
        $hostingPackageClient = new HostingPackageClient(
            client: $client,
            configuration: self::resolve(ConfigurationInterface::class),
            logger: $this->loggerMock,
            connection: $connection
        );

        $result = $hostingPackageClient->createSite('sandwave.io', 333);

        self::assertSame(Result::STATUS_OK, $result->getStatus());
        self::assertSame('488', $result->getDomainId());
        self::assertSame('6b1ecc2a-99b9-471f-9e5e-49932a6e9da0', $result->getDomainGuid());
    }

    #[Test]
    public function invalidResponseMessage(): void
    {
        $xml = '<site></site>';
        $response = new CreateSiteResponse(new Response(200, [], $xml));

        self::assertSame(0, $response->getErrorCode());
        self::assertSame('No result element found in response', $response->getErrorText());
    }

    #[Test]
    public function errorResponseFull(): void
    {
        $xml = '<packet>
                  <site>
                    <add>
                      <result>
                        <status>mockerror</status>
                        <errcode>1024</errcode>
                        <errtext>There are no available resources of this type (sites) left. Requested: 1; available: 0.</errtext>
                      </result>
                    </add>
                  </site>
                </packet>';

        $response = new CreateSiteResponse(new Response(200, [], $xml));

        self::assertSame(1024, $response->getErrorCode());
        self::assertSame('There are no available resources of this type (sites) left. Requested: 1; available: 0.', $response->getErrorText());
    }
}
