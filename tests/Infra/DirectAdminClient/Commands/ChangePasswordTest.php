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
use Waterfront\Infra\DirectAdminClient\Commands\Users\ChangePassword;
use Waterfront\Infra\DirectAdminClient\DirectAdminApi;

#[CoversClass(ChangePassword::class)]
class ChangePasswordTest extends DirectAdminTestCase
{
    private ChangePassword $changePassword;

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
        self::assertSame('CMD_API_USER_PASSWD', $this->changePassword->getCommand());
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

        $this->changePassword
            ->setPasswd('test123')
            ->setUsername('changedName');

        $modifyUser = $api->call($this->changePassword);

        self::assertStringContainsString('', $modifyUser->getResult());
        self::assertTrue($modifyUser->hasSucceeded());
    }
}
