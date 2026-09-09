<?php

declare(strict_types=1);

namespace Tests\Infra\DirectAdminClient\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionException;
use Tests\Infra\DirectAdminClient\DirectAdminTestCase;
use Waterfront\Infra\DirectAdminClient\Commands\Packages\PackagesUser;
use Waterfront\Infra\DirectAdminClient\DirectAdminApi;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminConnectionException;

#[CoversClass(PackagesUser::class)]
class PackageUserTest extends DirectAdminTestCase
{
    private PackagesUser $packageUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup config
        $this->packageUser = new PackagesUser();
    }

    protected function tearDown(): void
    {
        $this->deleteTestPackages('My-package-name');
        parent::tearDown();
    }

    #[Test]
    public function check_command_name_and_method(): void
    {
        self::assertSame('CMD_API_PACKAGES_USER', $this->packageUser->getCommand());
        self::assertSame('GET', $this->packageUser->getMethod());
    }

    /**
     *
     * @throws DirectAdminConnectionException
     * @throws DirectAdminCommandException
     * @throws ReflectionException
     */
    #[Test]
    public function packages_can_be_retrieved(): void
    {
        $this->createTestPackage('My-package-name');

        $response = '["My-package-name","default","deleteme","deletemeaswell","dontkeepme","test-domain-com","test-package"]';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $api = new DirectAdminApi($this->getTestServer(), $client);

        $packages = $api->call($this->packageUser);

        self::assertContains('My-package-name', $packages->getPackages());
    }
}
