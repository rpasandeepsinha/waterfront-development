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
use Waterfront\Infra\DirectAdminClient\Commands\Users\DeleteUsers;
use Waterfront\Infra\DirectAdminClient\DirectAdminApi;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminConnectionException;

#[CoversClass(DeleteUsers::class)]
class DeleteUsersTest extends DirectAdminTestCase
{
    private DeleteUsers $deleteUsers;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup config
        $this->deleteUsers = new DeleteUsers();
    }

    /**
     * @throws DirectAdminCommandException|DirectAdminConnectionException|ReflectionException|GuzzleException
     */
    #[Test]
    public function a_user_can_be_deleted_through_the_api(): void
    {
        $this->createTestUser();

        $response = '{"extended":"true","result":"User tester Removed User removed from SSH test-domain.nls config files have been removed Userss domains directory removed. Unix User removed from the server Users config files deleted Users data directory removed. Removed user from admins list ","success":"Users deleted"}';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $api = new DirectAdminApi($this->getTestServer(), $client);

        $command = $this->deleteUsers->addUser('tester');

        $deleted = $api->call($command);

        self::assertStringContainsString('User tester Removed', $deleted->getResult());
    }

    #[Test]
    public function multiple_users_can_be_set_to_be_deleted(): void
    {
        $delete = new DeleteUsers();
        $delete->setUsernames([
            'test1',
            'test2',
            'test3',
        ]);

        $delete->addUser('test4')->addUser('test5');

        $expect = [
            'test1',
            'test2',
            'test3',
            'test4',
            'test5',
        ];

        self::assertSame($expect, $delete->getUsers());
    }
}
