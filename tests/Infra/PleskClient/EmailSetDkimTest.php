<?php

declare(strict_types=1);

namespace Tests\Infra\PleskClient;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\PleskClient\Fakers\HostingPackageClientFaker;
use Waterfront\Infra\PleskClient\Messages\Connection;
use Waterfront\Infra\PleskClient\Services\HostingPackageClient;

#[CoversClass(HostingPackageClient::class)]
class EmailSetDkimTest extends IntegrationTestCase
{
    private const string TEST_DOMAIN = 'test-domain.com';

    #[Test]
    public function getHostingSiteSendsCorrectXmlMessageAndReturnsStatusOk(): void
    {
        $mock = new MockHandler([
            function (RequestInterface $request) {
                self::assertSame((string) file_get_contents(__DIR__ . '/data/plesk_get_hosting_website_request.xml'), (string) $request->getBody());
                return new Response(200, [], (string) file_get_contents(__DIR__ . '/data/plesk_get_hosting_website_response.xml'));
            },
            function (RequestInterface $request, $options) {
                self::assertSame((string) file_get_contents(__DIR__ . '/data/plesk_email_set_dkim_request.xml'), (string) $request->getBody());
                return new Response(200, [], (string) file_get_contents(__DIR__ . '/data/plesk_email_set_dkim_response.xml'));
            },
        ]);
        $handlerStack = HandlerStack::create($mock);

        $client = new Client(['handler' => $handlerStack]);
        $connection = new Connection();
        $connection->setApiUrl('https://test');
        $customerClient = new HostingPackageClient(
            client: $client,
            configuration: self::resolve(ConfigurationInterface::class),
            logger: self::resolve(LoggerInterface::class),
            connection: $connection
        );

        $result = $customerClient->setDkim(true, self::TEST_DOMAIN);

        self::assertSame(Result::STATUS_OK, $result->getStatus());
    }

    #[Test]
    public function setDkimSuccess(): void
    {
        $hostingClient = self::resolve(HostingPackageClientFaker::class);
        $hostingClient->setDesiredResponseCode(200);

        $result = $hostingClient->setDkim(true, self::TEST_DOMAIN);

        self::assertSame(Result::STATUS_OK, $result->getStatus());
    }

    #[Test]
    public function setDkimFailure(): void
    {
        $hostingClient = self::resolve(HostingPackageClientFaker::class);
        $hostingClient->setDesiredResponseCode(500);

        $result = $hostingClient->setDkim(true, self::TEST_DOMAIN);

        self::assertSame(Result::STATUS_ERROR, $result->getStatus());
    }
}
