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
use Waterfront\Infra\DirectAdminClient\Commands\Users\ModifyUser;
use Waterfront\Infra\DirectAdminClient\DirectAdminApi;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;

#[CoversClass(ModifyUser::class)]
class ModifyUserTest extends DirectAdminTestCase
{
    private ModifyUser $modifyUser;

    private DirectAdminApi $api;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup config
        $this->createTestUser('modifyuser');
        $this->modifyUser = new ModifyUser();
    }

    #[Test]
    public function check_command_name_and_method(): void
    {
        self::assertSame('CMD_API_MODIFY_USER', $this->modifyUser->getCommand());
        self::assertSame('POST', $this->modifyUser->getMethod());
    }

    /**
     * @throws DirectAdminCommandException|ReflectionException|GuzzleException
     */
    #[Test]
    public function a_user_can_be_updated(): void
    {
        $response = '{"result": "", "success": "User Modified"}';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $this->api = new DirectAdminApi($this->getTestServer(), $client);

        $this->modifyUser
            ->setUser('modifyuser')
            ->setMysql('200')
            ->setCron('OFF')
            ->setSsl('OFF')
            ->setSysinfo('OFF');

        $modifyUser = $this->api->call($this->modifyUser);

        self::assertStringContainsString('User Modified', $modifyUser->getResult());
        self::assertTrue($modifyUser->hasSucceeded());
    }

    /**
     * @throws DirectAdminCommandException|ReflectionException|GuzzleException
     */
    #[Test]
    public function a_user_package_be_updated(): void
    {
        $response = '{"result": "", "success": "User Modified"}';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $this->api = new DirectAdminApi($this->getTestServer(), $client);

        $this->modifyUser
            ->setUser('modifyuser')->setPackage('groot');

        $modifyUser = $this->api->call($this->modifyUser);

        self::assertStringContainsString('User Modified', $modifyUser->getResult());
        self::assertTrue($modifyUser->hasSucceeded());
    }
}
