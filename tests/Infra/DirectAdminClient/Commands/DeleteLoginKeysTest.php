<?php

declare(strict_types=1);

namespace Tests\Infra\DirectAdminClient\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Infra\DirectAdminClient\DirectAdminTestCase;
use Waterfront\Infra\DirectAdminClient\Commands\LoginKeys\DeleteLoginKeys;
use Waterfront\Infra\DirectAdminClient\DirectAdminApi;

#[CoversClass(DeleteLoginKeys::class)]
class DeleteLoginKeysTest extends DirectAdminTestCase
{
    private DeleteLoginKeys $deleteLoginKeys;

    private DirectAdminApi $api;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup config
        $this->deleteLoginKeys = new DeleteLoginKeys();
    }

    #[Test]
    public function check_command_name_and_method(): void
    {
        self::assertSame('CMD_API_LOGIN_KEYS', $this->deleteLoginKeys->getCommand());
        self::assertSame('POST', $this->deleteLoginKeys->getMethod());
    }

    #[Test]
    public function login_keys_should_be_able_to_be_deleted(): void
    {
        $this->createTestKey('deleteme');
        $this->createTestKey('deletemeaswell');
        $this->createTestKey('dontkeepme');

        $response = 'error=0&text=Key%28s%29%20Deleted&details=%3Ca%20href%3D%22CMD%5FLOGIN%5FKEYS%22%3EBack%3C%2Fa%3E';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $this->api = new DirectAdminApi($this->getTestServer(), $client);

        $this->deleteLoginKeys
            ->addKey('deleteme')
            ->addKey('deletemeaswell')
            ->addKey('dontkeepme');

        $responseCalled = $this->api->call($this->deleteLoginKeys);

        self::assertTrue($responseCalled->hasSucceeded());
    }

    #[Test]
    public function login_keys_should_be_able_to_be_deleted_ny_array(): void
    {
        $this->createTestKey('deleteone');
        $this->createTestKey('deletetwo');
        $this->createTestKey('dontkethree');

        $response = 'error=0&text=Key%28s%29%20Deleted&details=%3Ca%20href%3D%22CMD%5FLOGIN%5FKEYS%22%3EBack%3C%2Fa%3E';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $this->api = new DirectAdminApi($this->getTestServer(), $client);

        $this->deleteLoginKeys
            ->setKeys([
                'deleteone',
                'deletetwo',
                'deletethree',
            ]);

        $responseCalled = $this->api->call($this->deleteLoginKeys);

        self::assertTrue($responseCalled->hasSucceeded());
    }
}
