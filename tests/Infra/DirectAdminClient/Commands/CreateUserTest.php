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
use Waterfront\Infra\DirectAdminClient\Commands\Users\CreateUser;
use Waterfront\Infra\DirectAdminClient\Commands\Users\ShowAllUsers;
use Waterfront\Infra\DirectAdminClient\DirectAdminApi;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminConnectionException;

#[CoversClass(CreateUser::class)]
class CreateUserTest extends DirectAdminTestCase
{
    private CreateUser $createUser;

    private DirectAdminApi $api;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup config
        $this->createUser = new CreateUser();
    }

    /**
     * Override tear down method for these tests to delete a test user.
     *
     * @throws DirectAdminCommandException|DirectAdminConnectionException|ReflectionException|GuzzleException
     */
    protected function tearDown(): void
    {
        $this->deleteTestUser();
        parent::tearDown();
    }

    #[Test]
    public function check_command_name_and_method(): void
    {
        self::assertSame('CMD_API_ACCOUNT_USER', $this->createUser->getCommand());
        self::assertSame('POST', $this->createUser->getMethod());
    }

    /**
     * @throws DirectAdminCommandException|DirectAdminConnectionException|ReflectionException|GuzzleException
     */
    #[Test]
    public function a_user_can_be_created(): void
    {
        $response = '{"extended":"true","result":"Unix User created successfully Users System Quotas set Users data directory created successfully Domains directory created successfully Domains directory created successfully in users home Domain Created Successfully ","success":"User tester created"}';
        $responseShow = 'list[]=modifyuser&list[]=tester';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
            new Response(200, $this->getDefaultResponseHeaders(), $responseShow),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $this->api = new DirectAdminApi($this->getTestServer(), $client);

        $this->createUser
            ->setDomain('test-domain.nl')
            ->setEmail('test@user.nl')
            ->setPasswd('secret')
            ->setUsername('tester')
            ->setIp($this->getTestIp());

        $createUser = $this->api->call($this->createUser);

        self::assertStringContainsString('Unix User created successfully', $createUser->getResult());

        // See the user when retrieving user list?
        $usersCmd = new ShowAllUsers();
        $userList = $this->api->call($usersCmd);

        self::assertContains('tester', $userList->getUserList());
    }

    /**
     * @throws DirectAdminCommandException|ReflectionException|GuzzleException
     */
    #[Test]
    public function a_user_can_only_be_created_once(): void
    {
        $response = '{"extended":"true","result":"Unix User created successfully Users System Quotas set Users data directory created successfully Domains directory created successfully Domains directory created successfully in users home Domain Created Successfully ","success":"User tester created"}';
        $responseError = '{"error": "Cannot Create Account", "result": "That username already exists on the system"}';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
            new Response(200, $this->getDefaultResponseHeaders(), $responseError),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $this->api = new DirectAdminApi($this->getTestServer(), $client);

        $error = 'Failed [CreateUser]: Cannot Create Account - That username already exists on the system';

        $this->createUser
            ->setDomain('test-domain.nl')
            ->setEmail('test@user.nl')
            ->setPasswd('secret')
            ->setUsername('tester')
            ->setIp($this->getTestIp());

        $createUser = $this->api->call($this->createUser);

        self::assertStringContainsString('Unix User created successfully', $createUser->getResult());

        $this->expectException(DirectAdminCommandException::class);
        $this->expectExceptionMessageIs($error);

        // Create same user again should throw exception
        $this->api->call($this->createUser);
    }
}
