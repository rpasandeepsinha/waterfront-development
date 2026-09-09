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
use Waterfront\Infra\PleskClient\Messages\Connection;
use Waterfront\Infra\PleskClient\Messages\SyncSubscription\SyncSubscriptionRequest;
use Waterfront\Infra\PleskClient\Messages\SyncSubscription\SyncSubscriptionResponse;
use Waterfront\Infra\PleskClient\Services\HostingPackageClient;

#[CoversClass(SyncSubscriptionRequest::class)]
#[CoversClass(SyncSubscriptionResponse::class)]
class SyncSubscriptionTest extends IntegrationTestCase
{
    #[Test]
    public function syncSubscriptionSendsCorrectRequestAndReturnsStatusOk(): void
    {
        $mock = new MockHandler([
            function (RequestInterface $request) {
                self::assertXmlStringEqualsXmlString(
                    (string) file_get_contents(__DIR__ . '/data/plesk_sync_subscription_request.xml'),
                    (string) $request->getBody()
                );
                return new Response(
                    200,
                    [],
                    (string) file_get_contents(__DIR__ . '/data/plesk_sync_subscription_response_success.xml')
                );
            },
        ]);
        $handlerStack = HandlerStack::create($mock);

        $client = new Client(['handler' => $handlerStack]);
        $connection = new Connection();
        $connection->setApiUrl('https://test');
        $pleskClient = new HostingPackageClient(
            client: $client,
            configuration: self::resolve(ConfigurationInterface::class),
            logger: self::resolve(LoggerInterface::class),
            connection: $connection
        );

        $data = require __DIR__ . '/data/pleskSyncSubscriptionData.php';
        $domain = $data['domain'];

        $result = $pleskClient->syncSubscription($domain);

        self::assertSame(Result::STATUS_OK, $result->getStatus());
    }

    #[Test]
    public function syncSubscriptionFailureThrowsException(): void
    {
        $mock = new MockHandler([
            fn (RequestInterface $request) => new Response(
                200,
                [],
                (string) file_get_contents(__DIR__ . '/data/plesk_sync_subscription_response_failure.xml')
            ),
        ]);
        $handlerStack = HandlerStack::create($mock);

        $client = new Client(['handler' => $handlerStack]);
        $connection = new Connection();
        $connection->setApiUrl('https://test');
        $pleskClient = new HostingPackageClient(
            client: $client,
            configuration: self::resolve(ConfigurationInterface::class),
            logger: self::resolve(LoggerInterface::class),
            connection: $connection
        );

        $data = require __DIR__ . '/data/pleskSyncSubscriptionData.php';
        $domain = $data['domain'];
        $result = $pleskClient->syncSubscription($domain);

        self::assertSame(Result::STATUS_ERROR, $result->getStatus());
        self::assertSame('Webspace does not exist', $result->getErrorMessage());
    }
}
