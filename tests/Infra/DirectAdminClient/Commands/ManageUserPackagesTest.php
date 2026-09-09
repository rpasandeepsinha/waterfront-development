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
use Waterfront\Infra\DirectAdminClient\Commands\Packages\ManageUserPackages;
use Waterfront\Infra\DirectAdminClient\DirectAdminApi;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;

#[CoversClass(ManageUserPackages::class)]
class ManageUserPackagesTest extends DirectAdminTestCase
{
    private ManageUserPackages $manageUserPackages;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup config
        $this->manageUserPackages = new ManageUserPackages();
    }

    protected function tearDown(): void
    {
        $this->deleteTestPackages('test-package');
        parent::tearDown();
    }

    #[Test]
    public function check_command_name_and_method(): void
    {
        self::assertSame('CMD_API_MANAGE_USER_PACKAGES', $this->manageUserPackages->getCommand());
        self::assertSame('POST', $this->manageUserPackages->getMethod());
    }

    /**
     * @throws DirectAdminCommandException
     * @throws ReflectionException|GuzzleException
     */
    #[Test]
    public function a_user_package_can_be_created(): void
    {
        $response = '{ "result": "", "success": "Saved"}';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $api = new DirectAdminApi($this->getTestServer(), $client);

        $this->manageUserPackages
            ->setPackagename('test-package')
            ->setBandwidth('12345')
            ->setDnscontrol('OFF');

        $createUserPackage = $api->call($this->manageUserPackages);
        self::assertArrayHasKey('success', $createUserPackage->getFormValues());
    }
}
