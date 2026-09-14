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
use Waterfront\Infra\DirectAdminClient\Commands\LoginKeys\CreateLoginKey;
use Waterfront\Infra\DirectAdminClient\Commands\LoginKeys\ShowLoginKeys;
use Waterfront\Infra\DirectAdminClient\DirectAdminApi;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;

#[CoversClass(CreateLoginKey::class)]
class CreateLoginKeyTest extends DirectAdminTestCase
{
    private CreateLoginKey $loginKeys;

    private DirectAdminApi $api;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup config
        $this->loginKeys = new CreateLoginKey();
    }

    /**
     * Override tear down method for these tests to delete a test user.
     *
     * @throws DirectAdminCommandException|ReflectionException|GuzzleException
     */
    protected function tearDown(): void
    {
        $this->deleteTestKeys('mytestkey');
        $this->deleteTestKeys('modifyme');
        parent::tearDown();
    }

    #[Test]
    public function check_command_name_and_method(): void
    {
        self::assertSame('CMD_API_LOGIN_KEYS', $this->loginKeys->getCommand());
        self::assertSame('POST', $this->loginKeys->getMethod());
    }

    #[Test]
    public function a_default_key_should_be_set_by_default(): void
    {
        $loginKeys = new CreateLoginKey();
        self::assertGreaterThanOrEqual(
            40,
            strlen($loginKeys->getKey()),
            'Default key should be at least 40 characters',
        );
    }

    #[Test]
    public function a_login_key_can_be_created(): void
    {
        $response = 'error=0&text=Key%20Created%2E%20Take%20note%20of%20it%27s%20value%20and%20keep%20it%20safe%2E&details=XvDZAcrcrucEdLNgC%26%23%34%39%3BPY%26%23%34%38%3BeEI%26%23%34%38%3B%26%23%35%30%3BRArgWcM%26%23%34%38%3BsABXoARLCRTJPx%26%23%35%30%3B%26%23%35%37%3BomN%26%23%35%32%3BXTMDFfFuiO';
        $showResponse = 'HASHURL%32mz%39PjGu=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dyes%26created%25%35Fby%3D%25%33%31%25%33%30%25%33%39%25%32E%25%33%37%25%33%32%25%32E%25%33%38%25%33%37%25%32E%25%33%32%25%33%35%25%33%34%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%34%25%33%37%25%33%38%25%33%39%25%33%36%25%33%35%26expiry%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%37%25%33%33%25%33%38%25%33%31%25%33%36%25%33%35%26key%3Dhash%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&HASHURL%32nFDV%32%30%37=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dyes%26created%25%35Fby%3D%25%33%31%25%33%30%25%33%39%25%32E%25%33%37%25%33%32%25%32E%25%33%38%25%33%37%25%32E%25%33%32%25%33%35%25%33%34%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%35%25%33%35%25%33%39%25%33%37%25%33%32%25%33%32%26expiry%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%38%25%33%31%25%33%38%25%33%39%25%33%32%25%33%32%26key%3Dhash%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&HASHURL%34xpCxbDs=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dyes%26created%25%35Fby%3D%25%33%31%25%33%30%25%33%39%25%32E%25%33%37%25%33%32%25%32E%25%33%38%25%33%37%25%32E%25%33%32%25%33%35%25%33%34%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%35%25%33%35%25%33%34%25%33%39%25%33%37%25%33%35%26expiry%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%38%25%33%31%25%33%34%25%33%31%25%33%37%25%33%35%26key%3Dhash%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&HASHURL%39%33pqcYz%32=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dyes%26created%25%35Fby%3D%25%33%31%25%33%30%25%33%39%25%32E%25%33%37%25%33%32%25%32E%25%33%38%25%33%37%25%32E%25%33%32%25%33%35%25%33%34%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%35%25%33%35%25%33%39%25%33%36%25%33%33%25%33%39%26expiry%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%38%25%33%31%25%33%38%25%33%38%25%33%33%25%33%39%26key%3Dhash%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&HASHURLUa%33xdbvw=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dyes%26created%25%35Fby%3D%25%33%35%25%33%32%25%32E%25%33%32%25%33%35%25%33%31%25%32E%25%33%34%25%33%38%25%32E%25%33%31%25%33%30%25%33%31%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%34%25%33%39%25%33%34%25%33%39%25%33%31%25%33%33%26expiry%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%37%25%33%35%25%33%34%25%33%31%25%33%31%25%33%33%26key%3Dhash%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&HASHURLdcQShHr%37=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dyes%26created%25%35Fby%3D%25%33%31%25%33%30%25%33%39%25%32E%25%33%37%25%33%32%25%32E%25%33%38%25%33%37%25%32E%25%33%32%25%33%35%25%33%34%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%35%25%33%36%25%33%33%25%33%34%25%33%30%25%33%31%26expiry%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%38%25%33%32%25%33%32%25%33%36%25%33%30%25%33%31%26key%3Dhash%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&HASHURLlrILs%37%32P=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dyes%26created%25%35Fby%3D%25%33%34%25%33%30%25%32E%25%33%37%25%33%30%25%32E%25%33%36%25%33%38%25%32E%25%33%31%25%33%35%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%34%25%33%39%25%33%34%25%33%39%25%33%35%25%33%30%26expiry%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%37%25%33%35%25%33%34%25%33%31%25%33%35%25%33%30%26key%3Dhash%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&HASHURLmtNhfirX=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dyes%26created%25%35Fby%3D%25%33%34%25%33%30%25%32E%25%33%37%25%33%39%25%32E%25%33%32%25%33%35%25%33%35%25%32E%25%33%31%25%33%34%25%33%34%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%34%25%33%39%25%33%34%25%33%39%25%33%36%25%33%30%26expiry%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%37%25%33%35%25%33%34%25%33%31%25%33%36%25%33%30%26key%3Dhash%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&HASHURLswFOtzc%39=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dyes%26created%25%35Fby%3D%25%33%31%25%33%30%25%33%39%25%32E%25%33%37%25%33%32%25%32E%25%33%38%25%33%37%25%32E%25%33%32%25%33%35%25%33%34%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%34%25%33%37%25%33%38%25%33%39%25%33%31%25%33%34%26expiry%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%37%25%33%33%25%33%38%25%33%31%25%33%31%25%33%34%26key%3Dhash%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&HASHURLvuY%38fUkW=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dyes%26created%25%35Fby%3D%25%33%31%25%33%30%25%33%39%25%32E%25%33%37%25%33%32%25%32E%25%33%38%25%33%37%25%32E%25%33%32%25%33%35%25%33%34%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%34%25%33%38%25%33%33%25%33%33%25%33%37%25%33%31%26expiry%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%37%25%33%34%25%33%32%25%33%35%25%33%37%25%33%31%26key%3Dhash%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&HASHURLypnfQ%30%36%35=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dyes%26created%25%35Fby%3D%25%33%31%25%33%30%25%33%39%25%32E%25%33%37%25%33%32%25%32E%25%33%38%25%33%37%25%32E%25%33%32%25%33%35%25%33%34%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%35%25%33%36%25%33%32%25%33%39%25%33%36%25%33%34%26expiry%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%38%25%33%32%25%33%32%25%33%31%25%33%36%25%33%34%26key%3Dhash%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&HASHURLzuRcW%34ff=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dyes%26created%25%35Fby%3D%25%33%34%25%33%30%25%32E%25%33%37%25%33%39%25%32E%25%33%32%25%33%35%25%33%35%25%32E%25%33%31%25%33%34%25%33%34%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%34%25%33%39%25%33%34%25%33%38%25%33%39%25%33%32%26expiry%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%37%25%33%35%25%33%34%25%33%30%25%33%39%25%33%32%26key%3Dhash%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&dontkethree=allow%25%35Fhtm%3Dno%26clear%25%35Fkey%3Dno%26created%25%35Fby%3D%25%33%31%25%33%30%25%33%34%25%32E%25%33%34%25%33%31%25%32E%25%33%31%25%33%34%25%33%30%25%32E%25%33%32%25%33%32%25%33%36%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%34%25%33%37%25%33%39%25%33%38%25%33%37%25%33%35%25%33%36%26expiry%3D%25%33%30%26key%3D%25%32%34%25%33%36%25%32%34%25%33%30jxJw%25%33%35QI%25%32%34BxPzuqruJs%25%33%31JVI%25%33%31CpIPVlyrYj%25%33%30L%25%33%34AVyuxo%25%33%34BCa%25%33%31tbzJO%25%33%32ieSo%25%33%35k%25%33%35HNWzXhsLqvM%25%33%32FEV%25%33%32kHDda%25%33%37%25%33%33xquQ%25%33%33FFsAs%25%33%30%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&mytestkey=allow%25%35Fhtm%3Dno%26clear%25%35Fkey%3Dno%26created%25%35Fby%3D%25%33%31%25%33%30%25%33%39%25%32E%25%33%37%25%33%32%25%32E%25%33%38%25%33%37%25%32E%25%33%32%25%33%35%25%33%34%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%35%25%33%36%25%33%33%25%33%36%25%33%34%25%33%31%26expiry%3D%25%33%30%26key%3D%25%32%34%25%33%36%25%32%34TNRSDjmu%25%32%34rvjQLw%25%33%30%25%33%36IAXVzcAfpq%25%33%37W%25%33%36ci%25%33%34PKbvVLtt%25%33%31o%25%33%33GwarT%25%33%33v%25%32F%25%33%30n%25%33%30y%25%32EOOcSWwRfql%25%33%39JP%25%33%35WdF%25%32E%25%33%31QibaCo%25%33%36oFJYvi%25%32FhD%25%33%31y%25%32E%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&test=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dno%26created%25%35Fby%3D%25%33%32%25%33%31%25%33%32%25%32E%25%33%32%25%33%30%25%33%33%25%32E%25%33%32%25%33%34%25%32E%25%33%31%25%33%31%25%33%30%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%38%25%33%32%25%33%35%25%33%34%25%33%35%25%33%31%25%33%30%25%33%39%26expiry%3D%25%33%30%26key%3D%25%32%34%25%33%36%25%32%34zo%25%33%39ldZPD%25%32%34XnMoPtFDgqSU%25%32FJH%25%32FbBn%25%33%36fVHQkTVdXIbBU%25%33%39wiGLJgiSbC%25%33%37MHCd%25%33%33g%25%33%34%25%33%37v%25%33%35eRFJ%25%33%34P%25%33%34QYjI%25%33%31L%25%33%32kU%25%33%34V%25%33%30qLjRa%25%33%38aFLw%25%33%30%25%33%30%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%33%25%33%31%25%33%36%25%33%31';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
            new Response(200, $this->getDefaultResponseHeaders(), $showResponse),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $this->api = new DirectAdminApi($this->getTestServer(), $client);

        $this->loginKeys
            ->setKeyName('mytestkey')
            ->setExpires(false)
            ->setCurrentPassword((string) $this->api->getConnection()->getServer()->getPassword());

        $this->api->call($this->loginKeys);

        //check if key exists on the server
        $showKeys = new ShowLoginKeys();

        $keys = $this->api->call($showKeys)->getLoginKeys();

        self::assertArrayHasKey('mytestkey', $keys);
    }

    #[Test]
    public function a_one_time_url_can_be_requested(): void
    {
        $response = 'error=0&text=One%2DTime%20Login%20URL%20Created&details=https%3A%2F%2Fskdfhgdskfghkdsjfgh%2Esite%3A%32%32%32%32%2FCMD%5FLOGIN%5FURL%3Fhash%3DoDvC%31BWTmAABMDMyMs%33LbwBYZGox%33kCpy%35%31xGVO%30dYBylVu%37NvQwqcVPqHLrzXgxagsq%39GonoxjXSbfqW%33mMdFkRW%35IWazRAfKyoYmZNjGuBrXzOZmiAzScWXu%30%37rPFUhDHGzq';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $this->api = new DirectAdminApi($this->getTestServer(), $client);

        $this->loginKeys
            ->setKeyName('onetimelogin')
            ->setOneTimeLogin(true)
            ->setCurrentPassword((string) $this->api->getConnection()->getServer()->getPassword());

        $loginKey = $this->api->call($this->loginKeys);

        self::assertTrue($loginKey->isOneTimeLogin());
        self::assertStringContainsString('http', $loginKey->getLoginUrl());
    }

    #[Test]
    public function a_existing_key_can_be_modified(): void
    {
        $response = 'error=0&text=Key%20Created%2E%20Take%20note%20of%20it%27s%20value%20and%20keep%20it%20safe%2E&details=%26%23%35%32%3BlsiEgR%26%23%35%31%3BYhOFxPZLCAxpt%26%23%35%36%3BrroJye%26%23%34%38%3BsXwniKJlJeoyBvg%26%23%35%32%3BOFWdZBCwhPH%26%23%35%35%3B%26%23%35%32%3BSztyMW';
        $responseShow = 'HASHURL%32mz%39PjGu=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dyes%26created%25%35Fby%3D%25%33%31%25%33%30%25%33%39%25%32E%25%33%37%25%33%32%25%32E%25%33%38%25%33%37%25%32E%25%33%32%25%33%35%25%33%34%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%34%25%33%37%25%33%38%25%33%39%25%33%36%25%33%35%26expiry%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%37%25%33%33%25%33%38%25%33%31%25%33%36%25%33%35%26key%3Dhash%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&HASHURL%32nFDV%32%30%37=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dyes%26created%25%35Fby%3D%25%33%31%25%33%30%25%33%39%25%32E%25%33%37%25%33%32%25%32E%25%33%38%25%33%37%25%32E%25%33%32%25%33%35%25%33%34%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%35%25%33%35%25%33%39%25%33%37%25%33%32%25%33%32%26expiry%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%38%25%33%31%25%33%38%25%33%39%25%33%32%25%33%32%26key%3Dhash%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&HASHURL%34xpCxbDs=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dyes%26created%25%35Fby%3D%25%33%31%25%33%30%25%33%39%25%32E%25%33%37%25%33%32%25%32E%25%33%38%25%33%37%25%32E%25%33%32%25%33%35%25%33%34%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%35%25%33%35%25%33%34%25%33%39%25%33%37%25%33%35%26expiry%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%38%25%33%31%25%33%34%25%33%31%25%33%37%25%33%35%26key%3Dhash%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&HASHURL%36aoleUQJ=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dyes%26created%25%35Fby%3D%25%33%31%25%33%30%25%33%39%25%32E%25%33%37%25%33%32%25%32E%25%33%38%25%33%37%25%32E%25%33%32%25%33%35%25%33%34%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%35%25%33%36%25%33%34%25%33%31%25%33%39%25%33%37%26expiry%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%38%25%33%32%25%33%33%25%33%33%25%33%39%25%33%37%26key%3Dhash%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&HASHURL%39%33pqcYz%32=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dyes%26created%25%35Fby%3D%25%33%31%25%33%30%25%33%39%25%32E%25%33%37%25%33%32%25%32E%25%33%38%25%33%37%25%32E%25%33%32%25%33%35%25%33%34%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%35%25%33%35%25%33%39%25%33%36%25%33%33%25%33%39%26expiry%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%38%25%33%31%25%33%38%25%33%38%25%33%33%25%33%39%26key%3Dhash%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&HASHURLUa%33xdbvw=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dyes%26created%25%35Fby%3D%25%33%35%25%33%32%25%32E%25%33%32%25%33%35%25%33%31%25%32E%25%33%34%25%33%38%25%32E%25%33%31%25%33%30%25%33%31%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%34%25%33%39%25%33%34%25%33%39%25%33%31%25%33%33%26expiry%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%37%25%33%35%25%33%34%25%33%31%25%33%31%25%33%33%26key%3Dhash%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&HASHURLdcQShHr%37=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dyes%26created%25%35Fby%3D%25%33%31%25%33%30%25%33%39%25%32E%25%33%37%25%33%32%25%32E%25%33%38%25%33%37%25%32E%25%33%32%25%33%35%25%33%34%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%35%25%33%36%25%33%33%25%33%34%25%33%30%25%33%31%26expiry%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%38%25%33%32%25%33%32%25%33%36%25%33%30%25%33%31%26key%3Dhash%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&HASHURLlrILs%37%32P=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dyes%26created%25%35Fby%3D%25%33%34%25%33%30%25%32E%25%33%37%25%33%30%25%32E%25%33%36%25%33%38%25%32E%25%33%31%25%33%35%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%34%25%33%39%25%33%34%25%33%39%25%33%35%25%33%30%26expiry%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%37%25%33%35%25%33%34%25%33%31%25%33%35%25%33%30%26key%3Dhash%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&HASHURLmtNhfirX=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dyes%26created%25%35Fby%3D%25%33%34%25%33%30%25%32E%25%33%37%25%33%39%25%32E%25%33%32%25%33%35%25%33%35%25%32E%25%33%31%25%33%34%25%33%34%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%34%25%33%39%25%33%34%25%33%39%25%33%36%25%33%30%26expiry%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%37%25%33%35%25%33%34%25%33%31%25%33%36%25%33%30%26key%3Dhash%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&HASHURLpRnGQ%30oz=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dyes%26created%25%35Fby%3D%25%33%31%25%33%30%25%33%39%25%32E%25%33%37%25%33%32%25%32E%25%33%38%25%33%37%25%32E%25%33%32%25%33%35%25%33%34%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%35%25%33%36%25%33%34%25%33%30%25%33%39%25%33%38%26expiry%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%38%25%33%32%25%33%33%25%33%32%25%33%39%25%33%38%26key%3Dhash%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&HASHURLsAnUdPfm=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dyes%26created%25%35Fby%3D%25%33%31%25%33%30%25%33%39%25%32E%25%33%37%25%33%32%25%32E%25%33%38%25%33%37%25%32E%25%33%32%25%33%35%25%33%34%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%35%25%33%36%25%33%33%25%33%38%25%33%36%25%33%39%26expiry%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%38%25%33%32%25%33%33%25%33%30%25%33%36%25%33%39%26key%3Dhash%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&HASHURLswFOtzc%39=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dyes%26created%25%35Fby%3D%25%33%31%25%33%30%25%33%39%25%32E%25%33%37%25%33%32%25%32E%25%33%38%25%33%37%25%32E%25%33%32%25%33%35%25%33%34%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%34%25%33%37%25%33%38%25%33%39%25%33%31%25%33%34%26expiry%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%37%25%33%33%25%33%38%25%33%31%25%33%31%25%33%34%26key%3Dhash%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&HASHURLvuY%38fUkW=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dyes%26created%25%35Fby%3D%25%33%31%25%33%30%25%33%39%25%32E%25%33%37%25%33%32%25%32E%25%33%38%25%33%37%25%32E%25%33%32%25%33%35%25%33%34%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%34%25%33%38%25%33%33%25%33%33%25%33%37%25%33%31%26expiry%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%37%25%33%34%25%33%32%25%33%35%25%33%37%25%33%31%26key%3Dhash%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&HASHURLypnfQ%30%36%35=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dyes%26created%25%35Fby%3D%25%33%31%25%33%30%25%33%39%25%32E%25%33%37%25%33%32%25%32E%25%33%38%25%33%37%25%32E%25%33%32%25%33%35%25%33%34%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%35%25%33%36%25%33%32%25%33%39%25%33%36%25%33%34%26expiry%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%38%25%33%32%25%33%32%25%33%31%25%33%36%25%33%34%26key%3Dhash%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&HASHURLzuRcW%34ff=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dyes%26created%25%35Fby%3D%25%33%34%25%33%30%25%32E%25%33%37%25%33%39%25%32E%25%33%32%25%33%35%25%33%35%25%32E%25%33%31%25%33%34%25%33%34%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%34%25%33%39%25%33%34%25%33%38%25%33%39%25%33%32%26expiry%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%37%25%33%35%25%33%34%25%33%30%25%33%39%25%33%32%26key%3Dhash%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&dontkethree=allow%25%35Fhtm%3Dno%26clear%25%35Fkey%3Dno%26created%25%35Fby%3D%25%33%31%25%33%30%25%33%34%25%32E%25%33%34%25%33%31%25%32E%25%33%31%25%33%34%25%33%30%25%32E%25%33%32%25%33%32%25%33%36%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%34%25%33%37%25%33%39%25%33%38%25%33%37%25%33%35%25%33%36%26expiry%3D%25%33%30%26key%3D%25%32%34%25%33%36%25%32%34%25%33%30jxJw%25%33%35QI%25%32%34BxPzuqruJs%25%33%31JVI%25%33%31CpIPVlyrYj%25%33%30L%25%33%34AVyuxo%25%33%34BCa%25%33%31tbzJO%25%33%32ieSo%25%33%35k%25%33%35HNWzXhsLqvM%25%33%32FEV%25%33%32kHDda%25%33%37%25%33%33xquQ%25%33%33FFsAs%25%33%30%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&modifyme=allow%25%35Fhtm%3Dno%26clear%25%35Fkey%3Dno%26created%25%35Fby%3D%25%33%31%25%33%30%25%33%39%25%32E%25%33%37%25%33%32%25%32E%25%33%38%25%33%37%25%32E%25%33%32%25%33%35%25%33%34%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%35%25%33%36%25%33%34%25%33%31%25%33%39%25%33%38%26expiry%3D%25%33%30%26key%3D%25%32%34%25%33%36%25%32%34%25%33%30WadYWuk%25%32%34w%25%32EkLUlh%25%33%31%25%33%33zdE%25%33%38McSIeM%25%33%30gSIJkm%25%32EwiMDg%25%33%32gOhW%25%33%36SRtM%25%33%32jwVkM%25%33%36jwU%25%33%38%25%33%35GAns%25%33%34Wwn%25%33%30MibtEtoMIDPCmix%25%33%33SIrhNa%25%33%30%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&test=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dno%26created%25%35Fby%3D%25%33%32%25%33%31%25%33%32%25%32E%25%33%32%25%33%30%25%33%33%25%32E%25%33%32%25%33%34%25%32E%25%33%31%25%33%31%25%33%30%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%38%25%33%32%25%33%35%25%33%34%25%33%35%25%33%31%25%33%30%25%33%39%26expiry%3D%25%33%30%26key%3D%25%32%34%25%33%36%25%32%34zo%25%33%39ldZPD%25%32%34XnMoPtFDgqSU%25%32FJH%25%32FbBn%25%33%36fVHQkTVdXIbBU%25%33%39wiGLJgiSbC%25%33%37MHCd%25%33%33g%25%33%34%25%33%37v%25%33%35eRFJ%25%33%34P%25%33%34QYjI%25%33%31L%25%33%32kU%25%33%34V%25%33%30qLjRa%25%33%38aFLw%25%33%30%25%33%30%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%33%25%33%32%25%33%30%25%33%33';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
            new Response(200, $this->getDefaultResponseHeaders(), $responseShow),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $this->api = new DirectAdminApi($this->getTestServer(), $client);

        // Create a new key 'modifyme'
        $this->loginKeys
            ->setKeyName('modifyme')
            ->setExpires(false)
            ->setAllowHtml(false)
            ->setCurrentPassword((string) $this->api->getConnection()->getServer()->getPassword());

        $this->api->call($this->loginKeys);

        // Modify allowHtml to true
        $this->loginKeys
            ->setKeyName('modifyme')
            ->setAllowHtml(true)
            ->setCurrentPassword((string) $this->api->getConnection()->getServer()->getPassword());

        $showKeys = new ShowLoginKeys();
        $keys = $this->api->call($showKeys);
        $modifyMeKey = $keys->getLoginKey('modifyme');

        self::assertArrayHasKey('allow_htm', $modifyMeKey);
        self::assertSame('no', $modifyMeKey['allow_htm']);
    }
}
