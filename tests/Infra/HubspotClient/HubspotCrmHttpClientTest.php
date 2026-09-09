<?php

declare(strict_types=1);

namespace Tests\Infra\HubspotClient;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Waterfront\Infra\HubspotClient\Client\HubspotCrmHttpClient;
use Waterfront\Infra\HubspotClient\DTO\HubspotConfigDTO;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotClientException;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotJsonException;

#[CoversClass(HubspotCrmHttpClient::class)]
class HubspotCrmHttpClientTest extends TestCase
{
    #[Test]
    public function parsesResponse(): void
    {
        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(status: 200, body: '{"foo":"bar"}'),
        ]));
        $guzzleClient = new Client(['handler' => $handlerStack]);
        $httpClient = new HubspotCrmHttpClient($this->getMockConfig(), $guzzleClient);

        $response = $httpClient->get('/test');
        self::assertSame(['foo' => 'bar'], $response);
    }

    #[Test]
    public function setsCorrectAuthorizationheader(): void
    {
        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(status: 200, body: '[]'),
        ]));
        $handlerStack->push(fn (callable $handler) => function (RequestInterface $request, $options) use ($handler) {
            self::assertSame('Bearer accessToken', $request->getHeader('Authorization')[0]);
            return $handler($request, $options);
        });
        $guzzleClient = new Client(['handler' => $handlerStack]);
        $httpClient = new HubspotCrmHttpClient($this->getMockConfig(), $guzzleClient);

        $httpClient->get('/test');
    }

    #[Test]
    public function handlesUnexpectedStatus(): void
    {
        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(status: 500, body: '{"error":"oh no!"}'),
        ]));
        $guzzleClient = new Client(['handler' => $handlerStack]);
        $httpClient = new HubspotCrmHttpClient($this->getMockConfig(), $guzzleClient);

        $this->expectException(HubspotClientException::class);
        $httpClient->get('/test');
    }

    #[Test]
    public function handlesInvalidJson(): void
    {
        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(status: 200, body: '{💩 this is not valid json]'),
        ]));
        $guzzleClient = new Client(['handler' => $handlerStack]);
        $httpClient = new HubspotCrmHttpClient($this->getMockConfig(), $guzzleClient);

        $this->expectException(HubspotJsonException::class);
        $httpClient->get('/test');
    }

    #[Test]
    public function getRequestMethodAndBody(): void
    {
        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(status: 200, body: '[]'),
        ]));
        $handlerStack->push(fn (callable $handler) => function (RequestInterface $request, $options) use ($handler) {
            self::assertSame('GET', $request->getMethod());
            self::assertSame('', $request->getBody()->getContents());
            return $handler($request, $options);
        });
        $guzzleClient = new Client(['handler' => $handlerStack]);
        $httpClient = new HubspotCrmHttpClient($this->getMockConfig(), $guzzleClient);

        $httpClient->get('/test');
    }

    #[Test]
    public function postRequestMethodAndBody(): void
    {
        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(status: 201, body: '[]'),
        ]));
        $handlerStack->push(fn (callable $handler) => function (RequestInterface $request, $options) use ($handler) {
            self::assertSame('POST', $request->getMethod());
            self::assertSame('{"foo":"bar"}', $request->getBody()->getContents());
            return $handler($request, $options);
        });
        $guzzleClient = new Client(['handler' => $handlerStack]);
        $httpClient = new HubspotCrmHttpClient($this->getMockConfig(), $guzzleClient);

        $httpClient->post('/test', ['foo' => 'bar']);
    }

    #[Test]
    public function patchRequestMethodAndBody(): void
    {
        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(status: 200, body: '[]'),
        ]));
        $handlerStack->push(fn (callable $handler) => function (RequestInterface $request, $options) use ($handler) {
            self::assertSame('PATCH', $request->getMethod());
            self::assertSame('{"foo":"bar"}', $request->getBody()->getContents());
            return $handler($request, $options);
        });
        $guzzleClient = new Client(['handler' => $handlerStack]);
        $httpClient = new HubspotCrmHttpClient($this->getMockConfig(), $guzzleClient);

        $httpClient->patch('/test', ['foo' => 'bar']);
    }

    private function getMockConfig(): HubspotConfigDTO
    {
        return new HubspotConfigDTO(
            'accessToken',
            'https://localhost',
            'subscriptionObjectTypeId',
            'contactObjectTypeId',
            'marketing_mail_actions',
            'marketing_mail_surveys',
            'marketing_mail_newsletter',
            1,
            1
        );
    }
}
