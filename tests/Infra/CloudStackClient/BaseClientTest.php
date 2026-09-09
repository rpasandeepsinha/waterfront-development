<?php

declare(strict_types=1);

namespace Tests\Infra\CloudStackClient;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Infra\CloudStackClient\CloudStackBaseClient;
use Waterfront\Infra\CloudStackClient\Exceptions\ClientException;

#[CoversClass(CloudStackBaseClient::class)]
class BaseClientTest extends TestCase
{
    #[Test]
    public function execute(): void
    {
        $stack = HandlerStack::create(new MockHandler([
            new Response(
                200,
                ['Content-type' => 'application/json;charset=utf-8'],
                '{"listvirtualmachinesresponse":[]}'
            ),
        ]));

        $container = [];
        $stack->push(Middleware::history($container));

        $client = new CloudStackBaseClient(
            'http://url',
            'API-KEY',
            'SECRET-KEY',
            new GuzzleClient(['handler' => $stack])
        );
        $response = $client->execute('listVirtualMachines', ['listall' => 'true']);

        self::assertSame(
            'http://url?apikey=API-KEY&command=listVirtualMachines&listall=true&response=json&signature=nNdd9m%2FModwHmUh9Y0SM4VFZIus%3D',
            (string) $container[0]['request']->getUri()
        );
        self::assertSame('GET', $container[0]['request']->getMethod());
        self::assertCount(0, $response);
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('exceptionRequests')]
    #[Test]
    public function exception(int $statusCode, array $headers, string $body, string $exceptionMessage, int $exceptionCode): void
    {
        $mockHandler = new MockHandler(
            [new Response($statusCode, $headers, $body)]
        );
        $stack = HandlerStack::create($mockHandler);
        $client = new CloudStackBaseClient('', '', '', new GuzzleClient(['handler' => $stack]));

        $this->expectException(ClientException::class);
        $this->expectExceptionMessageIsOrContains($exceptionMessage);
        $this->expectExceptionCode($exceptionCode);

        $client->execute('listVirtualMachines');
    }

    /**
     * @return array<mixed>
     */
    public static function exceptionRequests(): array
    {
        return [
            [
                431,
                ['Content-type' => 'application/json;charset=utf-8'],
                '"Invalid request"',
                'Invalid request',
                431,
            ],
            [
                200,
                ['Content-type' => 'text/plain'],
                'test',
                'Invalid response content type',
                0,
            ],
            [
                200,
                ['Content-type' => 'application/json;charset=utf-8'],
                '{"foo',
                'Invalid response content type',
                0,
            ],
            [
                200,
                ['Content-type' => 'application/json;charset=utf-8'],
                '{}',
                'Invalid response data',
                0,
            ],
        ];
    }
}
