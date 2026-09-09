<?php

declare(strict_types=1);

namespace Tests\Infra\DirectAdminClient\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionException;
use Tests\Infra\DirectAdminClient\DirectAdminTestCase;
use Waterfront\Infra\DirectAdminClient\Commands\Users\SuspendUser;
use Waterfront\Infra\DirectAdminClient\DirectAdminApi;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;

#[CoversClass(SuspendUser::class)]
class SuspendUserTest extends DirectAdminTestCase
{
    private DirectAdminApi $api;

    private string $username = 'testuser';

    /**
     * @throws DirectAdminCommandException|ReflectionException|GuzzleException|JsonException
     */
    #[Test]
    public function suspendUnsuspendedUser(): void
    {
        $response = '{"success": "Users suspended"}';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $this->api = new DirectAdminApi($this->getTestServer(), $client);

        $result = $this->api->call(new SuspendUser($this->username));

        self::assertNotNull($result->getResponseBody());
        $json = (array) json_decode($result->getResponseBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('success', $json);
        self::assertTrue($result->hasSucceeded());
    }

    /**
     * @throws DirectAdminCommandException|ReflectionException|GuzzleException|JsonException
     */
    #[Test]
    public function suspendAlreadySuspendedUser(): void
    {
        $response = '{"success": "Users suspended"}';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
            new Response(200, $this->getDefaultResponseHeaders(), $response),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $this->api = new DirectAdminApi($this->getTestServer(), $client);

        $this->api->call(new SuspendUser($this->username));
        $result = $this->api->call(new SuspendUser($this->username));

        self::assertNotNull($result->getResponseBody());
        $json = (array) json_decode($result->getResponseBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('success', $json);
        self::assertTrue($result->hasSucceeded());
    }

    /**
     * @throws DirectAdminCommandException|ReflectionException|GuzzleException
     */
    #[Test]
    public function suspendUnknownUser(): void
    {
        $responseError = '{"error": "An error has occurred", "result": "Unable to read unknownuse\'s user files"}';
        $exceptionMessage = "Failed [SuspendUser]: An error has occurred - Unable to read unknownuse's user files";

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $responseError),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $this->api = new DirectAdminApi($this->getTestServer(), $client);

        self::expectException(DirectAdminCommandException::class);
        self::expectExceptionMessageIs($exceptionMessage);
        $this->api->call(new SuspendUser('unknownuser'));
    }
}
