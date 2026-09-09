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
use Waterfront\Infra\DirectAdminClient\Commands\Packages\DeleteUserPackage;
use Waterfront\Infra\DirectAdminClient\DirectAdminApi;

#[CoversClass(DeleteUserPackage::class)]
class DeleteUserPackagesTest extends DirectAdminTestCase
{
    private DeleteUserPackage $deleteUserPackage;

    private DirectAdminApi $api;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup config
        $this->deleteUserPackage = new DeleteUserPackage();
    }

    #[Test]
    public function check_command_name_and_method(): void
    {
        self::assertSame('CMD_API_MANAGE_USER_PACKAGES', $this->deleteUserPackage->getCommand());
        self::assertSame('POST', $this->deleteUserPackage->getMethod());
    }

    #[Test]
    public function multiple_user_packages_should_be_able_to_be_deleted(): void
    {
        $this->createTestPackage('deleteme');
        $this->createTestPackage('deletemeaswell');
        $this->createTestPackage('dontkeepme');

        $response = 'error=0&text=Deleted&details=';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $this->api = new DirectAdminApi($this->getTestServer(), $client);

        $this->deleteUserPackage
            ->addPackage('deleteme')
            ->addPackage('deletemeaswell')
            ->addPackage('dontkeepme');

        $responseCalled = $this->api->call($this->deleteUserPackage);

        self::assertTrue($responseCalled->hasSucceeded());
    }

    #[Test]
    public function multiple_user_packages_can_be_deleted_by_array(): void
    {
        $this->createTestPackage('deleteone');
        $this->createTestPackage('deletetwo');
        $this->createTestPackage('deletethree');

        $response = 'error=0&text=Deleted&details=';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $this->api = new DirectAdminApi($this->getTestServer(), $client);

        $this->deleteUserPackage
            ->setPackages([
                'deleteone',
                'deletetwo',
                'deletethree',
            ]);

        $responseCalled = $this->api->call($this->deleteUserPackage);

        self::assertTrue($responseCalled->hasSucceeded());
    }
}
