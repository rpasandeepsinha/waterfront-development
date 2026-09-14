<?php

declare(strict_types=1);

namespace Tests\Infra\RtrClient\Helpers;

use Closure;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use RealtimeRegister\RealtimeRegister;
use RealtimeRegister\Support\AuthorizedClient;

class MockedClientFactory
{
    public const API_KEY = 'bigsecretdonttellanyone';

    public static function makeSdk(
        int $responseCode,
        string $responseBody,
        ?callable $assertClosure = null,
    ): RealtimeRegister {
        $sdk = new RealtimeRegister(self::API_KEY);
        $sdk->setClient(static::makeAuthorizedClient([new Response($responseCode, [], $responseBody)], $assertClosure));

        return $sdk;
    }

    /**
     * @param Response[] $responses
     */
    public static function makeSdkWithMultipleReponses(
        array $responses,
        ?callable $assertClosure = null,
    ): RealtimeRegister {
        $sdk = new RealtimeRegister(self::API_KEY);
        $sdk->setClient(static::makeAuthorizedClient($responses, $assertClosure));

        return $sdk;
    }

    /**
     * @param Response[] $responses
     */
    public static function makeAuthorizedClient(array $responses, ?callable $assertClosure = null): AuthorizedClient
    {
        $fakeClient = new AuthorizedClient('https://example.com/api/v2/', self::API_KEY);

        $handlerStack = HandlerStack::create(new MockHandler($responses));

        if ($assertClosure !== null) {
            $handlerStack->push(fn (callable $handler): Closure => function (RequestInterface $request, $options) use (
                $handler,
                $assertClosure,
            ) {
                $assertClosure($request);

                return $handler($request, $options);
            });
        }

        $fakeClient->setClient(new Client(['handler' => $handlerStack]));

        return $fakeClient;
    }
}
