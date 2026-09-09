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
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\PleskClient\Fakers\PleskClientFaker;
use Waterfront\Infra\PleskClient\Messages\Connection;
use Waterfront\Infra\PleskClient\Services\HostingPackageClient;

#[CoversClass(HostingPackageClient::class)]
class SessionTokenGetTest extends IntegrationTestCase
{
    #[Test]
    public function sessionTokenGetGeneratesCorrectXmlMessageAndReturnsStatusOk(): void
    {
        $mock = new MockHandler([
            new Response(200, [], (string) file_get_contents(__DIR__ . '/data/plesk_session_token_get_response.xml')),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(fn (callable $handler) => function (RequestInterface $request, $options) use ($handler) {
            self::assertSame((string) file_get_contents(__DIR__ . '/data/plesk_session_token_get_request.xml'), (string) $request->getBody());
            return $handler($request, $options);
        });

        $client = new Client(['handler' => $handlerStack]);
        $connection = new Connection();
        $connection->setApiUrl('https://test');
        $customerClient = new HostingPackageClient(
            client: $client,
            configuration: self::resolve(ConfigurationInterface::class),
            logger: self::resolve(LoggerInterface::class),
            connection: $connection
        );

        $data = require __DIR__ . '/data/pleskSessionTokenGetData.php';
        $result = $customerClient->getSessionToken($data['username'], $data['ipAddress']);

        self::assertSame('64b6f51df8b3e33875744dc1d194526f', $result);
    }

    #[Test]
    public function fakeSessionTokenGetWillReturnSuccess(): void
    {
        $pleskClient = self::resolve(PleskClientFaker::class);
        $pleskClient->setDesiredResponseCode(200);

        $data = require __DIR__ . '/data/pleskSessionTokenGetData.php';
        $result = $pleskClient->getSessionToken($data['username'], $data['ipAddress']);

        self::assertMatchesRegularExpression('/^[0-9a-f]+$/', $result);
    }
}
