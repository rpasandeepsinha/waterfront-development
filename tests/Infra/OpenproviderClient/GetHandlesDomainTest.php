<?php

declare(strict_types=1);

namespace Tests\Infra\OpenproviderClient;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as HttpResponse;
use Illuminate\Contracts\Bus\Dispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Domain\Domains\DTO\RetrieveCustomerResponse;
use Waterfront\Infra\OpenproviderClient\Messages\Connection;
use Waterfront\Infra\OpenproviderClient\OpenproviderClient;

#[CoversClass(OpenproviderClient::class)]
class GetHandlesDomainTest extends TestCase
{
    #[Test]
    public function getCustomerHandle(): void
    {
        $response = new HttpResponse(
            200,
            [],
            (string) file_get_contents(__DIR__ . '/data/openprovider_retrieve_handle_response.xml')
        );

        $handlerStack = HandlerStack::create(new MockHandler([
            new HttpResponse(
                200,
                [],
                (string) file_get_contents(__DIR__ . '/data/openprovider_retrieve_handle_response.xml')
            ),
        ]));
        $guzzleClient = new Client(['handler' => $handlerStack]);

        $client = new OpenproviderClient(
            $guzzleClient,
            new Connection('https://test.nl', 'test-user', 'password'),
            self::createStub(Dispatcher::class),
        );

        $actual = $client->getCustomerHandle('42-Dopefish2');
        $expected = RetrieveCustomerResponse::fromXMLResponse($response);
        self::assertEquals($expected, $actual);
    }
}
