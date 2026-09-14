<?php

declare(strict_types=1);

namespace Tests\Infra\DirectAdminClient\Commands\Resellers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionException;
use Tests\Infra\DirectAdminClient\DirectAdminTestCase;
use Waterfront\Infra\DirectAdminClient\Commands\Resellers\ModifyReseller;
use Waterfront\Infra\DirectAdminClient\DirectAdminApi;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;

#[CoversClass(ModifyReseller::class)]
class ModifyResellerTest extends DirectAdminTestCase
{
    private ModifyReseller $modifyReseller;

    private DirectAdminApi $api;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup config
        $this->createTestReseller('modifyreseller');
        $this->modifyReseller = new ModifyReseller();
    }

    #[Test]
    public function checkCommandNameAndMethod(): void
    {
        Assert::assertSame('CMD_API_MODIFY_RESELLER', $this->modifyReseller->getCommand());
        Assert::assertSame('POST', $this->modifyReseller->getMethod());
        Assert::assertTrue($this->modifyReseller->usesJsonResponse());
    }

    /**
     * @throws DirectAdminCommandException|ReflectionException|GuzzleException
     */
    #[Test]
    public function aResellerCanBeUpdated(): void
    {
        $response = '{"result": "", "success": "Options changed successfully"}';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $this->api = new DirectAdminApi($this->getTestServer(), $client);

        $this->modifyReseller
            ->setReseller('modifyreseller')
            ->setMysql('200')
            ->setDnscontrol('OFF')
            ->setSsl('OFF')
            ->setUserssh('OFF');

        $modifyReseller = $this->api->call($this->modifyReseller);

        Assert::assertStringContainsString('Options changed successfully', $modifyReseller->getResult());
        Assert::assertTrue($modifyReseller->hasSucceeded());
    }

    /**
     * @throws DirectAdminCommandException|ReflectionException|GuzzleException
     */
    #[Test]
    public function aResellerPackageBeUpdated(): void
    {
        $response = '{"result": "", "success": "Options changed successfully"}';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $this->api = new DirectAdminApi($this->getTestServer(), $client);

        $this->modifyReseller->setReseller('modifyreseller')->setPackage('groot');

        $modifyReseller = $this->api->call($this->modifyReseller);

        Assert::assertStringContainsString('Options changed successfully', $modifyReseller->getResult());
        Assert::assertTrue($modifyReseller->hasSucceeded());
    }
}
