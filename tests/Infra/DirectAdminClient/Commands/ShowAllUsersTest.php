<?php

declare(strict_types=1);

namespace Tests\Infra\DirectAdminClient\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionException;
use Tests\Infra\DirectAdminClient\DirectAdminTestCase;
use Waterfront\Infra\DirectAdminClient\Commands\Users\ShowAllUsers;
use Waterfront\Infra\DirectAdminClient\DirectAdminApi;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminConnectionException;

#[CoversClass(ShowAllUsers::class)]
class ShowAllUsersTest extends DirectAdminTestCase
{
    /**
     * @throws DirectAdminCommandException|DirectAdminConnectionException|ReflectionException|GuzzleException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTestUser('test1');
        $this->createTestUser('test2');
        $this->createTestUser('test3');
    }

    /**
     * @throws DirectAdminCommandException|DirectAdminConnectionException|ReflectionException|GuzzleException
     */
    protected function tearDown(): void
    {
        $this->deleteTestUser('test1');
        $this->deleteTestUser('test2');
        $this->deleteTestUser('test3');

        parent::tearDown();
    }

    /**
     * @throws DirectAdminCommandException|DirectAdminConnectionException|ReflectionException|GuzzleException
     */
    #[Test]
    public function it_should_show_users_from_the_server(): void
    {
        $cmd = new ShowAllUsers();

        $response = 'list[]=modifyuser&list[]=test1&list[]=test2&list[]=test3';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $api = new DirectAdminApi($this->getTestServer(), $client);

        $users = $api->call($cmd)->getUserList();

        self::assertContains('test1', $users);
        self::assertContains('test2', $users);
        self::assertContains('test3', $users);
    }
}
