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
use Waterfront\Infra\DirectAdminClient\Commands\Packages\PackageReseller;
use Waterfront\Infra\DirectAdminClient\DirectAdminApi;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;

#[CoversClass(PackageReseller::class)]
class PackageResellerTest extends DirectAdminTestCase
{
    public const string RESELLER_PACKAGE_NAME = 'reseller-package-test';

    private PackageReseller $packageReseller;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup config
        $this->packageReseller = new PackageReseller();
    }

    protected function tearDown(): void
    {
        $this->deleteTestPackages(self::RESELLER_PACKAGE_NAME);
        parent::tearDown();
    }

    #[Test]
    public function checkCommandNameAndMethod(): void
    {
        Assert::assertSame('CMD_API_PACKAGES_RESELLER', $this->packageReseller->getCommand());
        Assert::assertSame('GET', $this->packageReseller->getMethod());
    }

    /**
     * @throws DirectAdminCommandException|ReflectionException|GuzzleException
     */
    #[Test]
    public function packagesCanBeRetrieved(): void
    {
        $this->createTestPackage(self::RESELLER_PACKAGE_NAME);

        $response = '["defaultreseller","' . self::RESELLER_PACKAGE_NAME . '","test"]';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $api = new DirectAdminApi($this->getTestServer(), $client);

        $packages = $api->call($this->packageReseller);

        Assert::assertContains(self::RESELLER_PACKAGE_NAME, $packages->getPackages());
    }
}
