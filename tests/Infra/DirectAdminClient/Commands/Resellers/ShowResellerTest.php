<?php

declare(strict_types=1);

namespace Tests\Infra\DirectAdminClient\Commands\Resellers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Testing\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionException;
use Tests\Infra\DirectAdminClient\DirectAdminTestCase;
use Waterfront\Infra\DirectAdminClient\Commands\Users\ShowAllUsers;
use Waterfront\Infra\DirectAdminClient\DirectAdminApi;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;

#[CoversClass(ShowAllUsers::class)]
class ShowResellerTest extends DirectAdminTestCase
{
    /**
     * @throws DirectAdminCommandException|ReflectionException|GuzzleException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTestReseller('retest1');
        $this->createTestReseller('retest2');
        $this->createTestReseller('retest3');
    }

    /**
     * @throws DirectAdminCommandException|ReflectionException|GuzzleException
     */
    protected function tearDown(): void
    {
        $this->deleteTestReseller('retest1');
        $this->deleteTestReseller('retest2');
        $this->deleteTestReseller('retest3');

        parent::tearDown();
    }

    /**
     * @throws DirectAdminCommandException|ReflectionException|GuzzleException
     */
    #[Test]
    public function itShouldShowResellersFromTheServer(): void
    {
        $cmd = new ShowAllUsers();

        $response = 'list[]=retest1&list[]=retest2&list[]=retest3';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $api = new DirectAdminApi($this->getTestServer(), $client);

        $users = $api->call($cmd);

        $userList = $users->getUserList();

        Assert::assertContains('retest1', $userList);
        Assert::assertContains('retest2', $userList);
        Assert::assertContains('retest3', $userList);
    }
}
