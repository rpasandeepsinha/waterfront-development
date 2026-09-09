<?php

declare(strict_types=1);

namespace Tests\Infra\DirectAdminClient\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Infra\DirectAdminClient\DirectAdminTestCase;
use Waterfront\Infra\DirectAdminClient\Commands\Users\CreateUser;
use Waterfront\Infra\DirectAdminClient\Commands\Wordpress\WordpressInstall;
use Waterfront\Infra\DirectAdminClient\DirectAdminApi;

#[CoversClass(CreateUser::class)]
class InstallWordpressTest extends DirectAdminTestCase
{
    private WordpressInstall $wordpress;

    private CreateUser $createUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup config
        $this->wordpress  = new WordpressInstall();
        $this->createUser = new CreateUser();
    }

    #[Test]
    public function check_command_name_and_method(): void
    {
        Assert::assertSame(
            'CMD_PLUGINS/softaculous/index.raw?act=software&soft=26&jsnohf=1&soft=26',
            $this->wordpress->getCommand()
        );

        Assert::assertSame('POST', $this->wordpress->getMethod());
    }

    #[Test]
    public function create_wordpress_install(): void
    {
        $response = '{"extended":"true","result":"Unix User created successfully Users System Quotas set Users data directory created successfully Domains directory created successfully Domains directory created successfully in users home Domain Created Successfully ","success":"User tester created"}';
        $responseInstalled = '1';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
            new Response(200, $this->getDefaultResponseHeaders(), $responseInstalled),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $api = new DirectAdminApi($this->getTestServer(), $client);

        $this->createUser
            ->setDomain('test-domain.nl')
            ->setEmail('test@user.nl')
            ->setPasswd('secret')
            ->setUsername('tester')
            ->setPackage('brons')
            ->setIp($this->getTestIp());

        $createUser = $api->call($this->createUser);

        Assert::assertStringContainsString('Unix User created successfully', $createUser->getResult());

        $wordpress = $this->wordpress
            ->setDomain('test-domain.nl')
            ->setEmail('test@user.nl');

        $installedWordpress = $api->loginAs('tester')->call($wordpress);

        Assert::assertSame('1', $installedWordpress->getResponseBody());
    }
}
