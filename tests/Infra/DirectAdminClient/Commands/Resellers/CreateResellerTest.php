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
use Waterfront\Infra\DirectAdminClient\Commands\Resellers\CreateReseller;
use Waterfront\Infra\DirectAdminClient\Commands\Resellers\ShowResellers;
use Waterfront\Infra\DirectAdminClient\DirectAdminApi;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;

#[CoversClass(CreateReseller::class)]
class CreateResellerTest extends DirectAdminTestCase
{
    /**
     * The directadmin.conf has a setting 'max_username_length' which defaults to 10.
     */
    public const string TEST_USER = 'reselltest';

    private CreateReseller $createReseller;

    private DirectAdminApi $api;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup config
        $this->createReseller = new CreateReseller();
    }

    protected function tearDown(): void
    {
        $this->deleteTestReseller();
        parent::tearDown();
    }

    #[Test]
    public function checkCommandNameAndMethod(): void
    {
        Assert::assertSame('CMD_API_ACCOUNT_RESELLER', $this->createReseller->getCommand());
        Assert::assertSame('POST', $this->createReseller->getMethod());
    }

    /**
     * @throws DirectAdminCommandException|ReflectionException|GuzzleException
     */
    #[Test]
    public function aResellerCanBeCreated(): void
    {
        $response = '{"extended": "true","result": "<b>Reseller only got 0 of their 2 ips.</b> Unix User created successfully User\'s System Quotas set User\'s data directory created successfully Domains directory created successfully Domains directory created successfully in user\'s home Domain Created Successfully User added to ssh config file. Reseller\'s package directory created successfully Reseller created ","success": "User reselltest created"}';
        $responseShow = 'list[]=reselltest';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
            new Response(200, $this->getDefaultResponseHeaders(), $responseShow),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $this->api = new DirectAdminApi($this->getTestServer(), $client);

        $this->createReseller
            ->setDomain('test-domain.nl')
            ->setEmail('test@user.nl')
            ->setPasswd('secret')
            ->setUsername(self::TEST_USER)
            ->setIp('shared')
            ->setNotify('no')
            ->setUbandwidth('ON')
            ->setQuota('5000')
            ->setUquota('OFF')
            ->setUvdomains('ON')
            ->setUnsubdomains('ON')
            ->setIps('2')
            ->setUnemails('ON')
            ->setNemailf('200')
            ->setUnemailml('ON')
            ->setUnemailr('ON')
            ->setUmysql('ON')
            ->setUdomainptr('ON')
            ->setUftp('ON')
            ->setAftp('OFF')
            ->setPhp('ON')
            ->setCgi('ON')
            ->setSsl('ON')
            ->setSsh('ON')
            ->setUserssh('ON')
            ->setDnscontrol('ON')
            ->setDns('OFF')
            ->setServerip('ON');

        $createUser = $this->api->call($this->createReseller);

        Assert::assertStringContainsString('Unix User created successfully', $createUser->getResult());

        // See the user when retrieving user list?
        $resellerCmd = new ShowResellers();

        $resellerList = $this->api->call($resellerCmd);

        Assert::assertContains(self::TEST_USER, $resellerList->getResellerList());
    }

    /**
     * @throws DirectAdminCommandException|ReflectionException|GuzzleException
     */
    #[Test]
    public function aResellerCanOnlyBeCreatedOnce(): void
    {
        $responseError = '{"error": "Cannot Create Account", "result": "That username already exists on the system"}';
        $response = '{"extended": "true","result": "<b>Reseller only got 0 of their 2 ips.</b> Unix User created successfully User\'s System Quotas set User\'s data directory created successfully Domains directory created successfully Domains directory created successfully in user\'s home Domain Created Successfully User added to ssh config file. Reseller\'s package directory created successfully Reseller created ","success": "User reselltest created"}';
        $error = 'Failed [CreateReseller]: Cannot Create Account - That username already exists on the system';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
            new Response(200, $this->getDefaultResponseHeaders(), $responseError),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $this->api = new DirectAdminApi($this->getTestServer(), $client);

        $this->createReseller
            ->setDomain('test-domain.nl')
            ->setEmail('test@user.nl')
            ->setPasswd('secret')
            ->setUsername(self::TEST_USER)
            ->setIp('shared')
            ->setNotify('no')
            ->setUbandwidth('ON')
            ->setQuota('5000')
            ->setUquota('OFF')
            ->setUvdomains('ON')
            ->setUnsubdomains('ON')
            ->setIps('2')
            ->setUnemails('ON')
            ->setNemailf('200')
            ->setUnemailml('ON')
            ->setUnemailr('ON')
            ->setUmysql('ON')
            ->setUdomainptr('ON')
            ->setUftp('ON')
            ->setAftp('OFF')
            ->setPhp('ON')
            ->setCgi('ON')
            ->setSsl('ON')
            ->setSsh('ON')
            ->setUserssh('ON')
            ->setDnscontrol('ON')
            ->setDns('OFF')
            ->setServerip('ON');

        $createUser = $this->api->call($this->createReseller);

        Assert::assertStringContainsString('Unix User created successfully', $createUser->getResult());

        $this->expectException(DirectAdminCommandException::class);
        $this->expectExceptionMessageIs($error);

        $this->api->call($this->createReseller);
    }
}
