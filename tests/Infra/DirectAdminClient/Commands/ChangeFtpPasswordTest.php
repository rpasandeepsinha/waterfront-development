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
use Waterfront\Infra\DirectAdminClient\Commands\Ftp\ChangePassword;
use Waterfront\Infra\DirectAdminClient\DirectAdminApi;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminConnectionException;

#[CoversClass(ChangePassword::class)]
class ChangeFtpPasswordTest extends DirectAdminTestCase
{
    private ChangePassword $changePassword;

    /**
     * Override setup method to have a valid api and command instance for every test.
     *
     * @throws DirectAdminCommandException|DirectAdminConnectionException|ReflectionException|GuzzleException
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Setup config
        $this->changePassword = new ChangePassword();
        $this->createTestUser('test1');
    }

    #[Test]
    public function check_command_name_and_method(): void
    {
        self::assertSame('CMD_API_FTP', $this->changePassword->getCommand());
        self::assertSame('POST', $this->changePassword->getMethod());
    }

    #[Test]
    public function change_password_for_user(): void
    {
        $response = '{"result": "", "success": "Password Changed"}';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $api = new DirectAdminApi($this->getTestServer(), $client);

        $this->changePassword->setPasswd('test123')->setUsername('test1')->setDomain('example.com');

        $modifyUser = $api->call($this->changePassword);

        self::assertStringContainsString('', $modifyUser->getResult());
        self::assertTrue($modifyUser->hasSucceeded());
    }
}
