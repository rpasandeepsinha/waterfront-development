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
use Waterfront\Infra\PleskClient\Messages\RemoveSite\RemoveSiteRequest;
use Waterfront\Infra\PleskClient\Messages\RemoveSite\RemoveSiteResponse;
use Waterfront\Infra\PleskClient\Messages\RemoveSite\RemoveSiteResult;
use Waterfront\Infra\PleskClient\Services\HostingPackageClient;

#[CoversClass(RemoveSiteRequest::class)]
#[CoversClass(RemoveSiteResponse::class)]
#[CoversClass(RemoveSiteResult::class)]
class RemoveSiteTest extends IntegrationTestCase
{
    private LoggerInterface&Stub $loggerMock;

    public function setUp(): void
    {
        parent::setUp();

        $this->loggerMock = self::createStub(LoggerInterface::class);
    }

    #[Test]
    public function removeSite(): void
    {
        $mock = new MockHandler([
            function (RequestInterface $request, $options) {
                self::assertSame((string) file_get_contents(__DIR__ . '/data/plesk_remove_site_request.xml'), (string) $request->getBody());
                return new Response(200, [], (string) file_get_contents(__DIR__ . '/data/plesk_remove_site_response.xml'));
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

        $result = $hostingPackageClient->removeSite('sandwave.io');

        self::assertSame(Result::STATUS_OK, $result->getStatus());
    }

    #[Test]
    public function invalidResponseMessage(): void
    {
        $xml = '<site></site>';
        $response = new RemoveSiteResponse(new Response(200, [], $xml));

        self::assertSame(0, $response->getErrorCode());
        self::assertSame('No result element found in response', $response->getErrorText());
    }

    #[Test]
    public function errorResponseSiteDoesNotExist(): void
    {
        $xml = '<packet>
                  <site>
                    <del>
                      <result>
                        <status>mockerror</status>
                        <errcode>1013</errcode>
                        <errtext>Site does not exist</errtext>
                        <filter-id>sandwave.io</filter-id>
                      </result>
                    </del>
                  </site>
                </packet>';

        $response = new RemoveSiteResponse(new Response(200, [], $xml));

        self::assertSame(1013, $response->getErrorCode());
        self::assertSame('Site does not exist', $response->getErrorText());
    }
}
