<?php

declare(strict_types=1);

namespace Tests\Infra\DirectAdminClient;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use ReflectionException;
use Tests\Infra\DirectAdminClient\Mock\DirectAdminTestServer;
use Tests\TestCase;
use Waterfront\Infra\DirectAdminClient\Commands\LoginKeys\CreateLoginKey;
use Waterfront\Infra\DirectAdminClient\Commands\LoginKeys\DeleteLoginKeys;
use Waterfront\Infra\DirectAdminClient\Commands\LoginKeys\ShowLoginKeys;
use Waterfront\Infra\DirectAdminClient\Commands\Packages\DeleteResellerPackage;
use Waterfront\Infra\DirectAdminClient\Commands\Packages\DeleteUserPackage;
use Waterfront\Infra\DirectAdminClient\Commands\Packages\ManageResellerPackages;
use Waterfront\Infra\DirectAdminClient\Commands\Packages\ManageUserPackages;
use Waterfront\Infra\DirectAdminClient\Commands\Resellers\CreateReseller;
use Waterfront\Infra\DirectAdminClient\Commands\Resellers\ShowResellers;
use Waterfront\Infra\DirectAdminClient\Commands\Users\CreateUser;
use Waterfront\Infra\DirectAdminClient\Commands\Users\DeleteUsers;
use Waterfront\Infra\DirectAdminClient\Commands\Users\ShowAllUsers;
use Waterfront\Infra\DirectAdminClient\DirectAdminApi;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminConnectionException;

abstract class DirectAdminTestCase extends TestCase
{
    protected static string $ip = '127.0.0.1';

    /**
     * Create a test user package on the directadmin test server.
     *
     * @throws DirectAdminCommandException|ReflectionException|GuzzleException
     */
    public function createTestPackage(string $name = 'testerpackage'): void
    {
        $response = '{ "result": "", "success": "Saved"}';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $api = new DirectAdminApi($this->getTestServer(), $client);

        $cmd = new ManageUserPackages();
        $cmd
            ->setPackagename($name)
            ->setBandwidth('1337')
            ->setDnscontrol('OFF');

        $api->call($cmd);
    }

    /**
     * Create a test user package on the directadmin test server.
     *
     * @throws DirectAdminCommandException|ReflectionException|GuzzleException
     */
    public function createResellerTestPackage(string $name = 'resellertestpackage'): void
    {
        $response = '{ "result": "", "success": "Saved"}';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $api = new DirectAdminApi($this->getTestServer(), $client);

        $cmd = new ManageResellerPackages();
        $cmd
            ->setPackagename($name)
            ->setBandwidth('1337')
            ->setDnscontrol('OFF')
            ->setUinode('ON')
            ->setLoginKeys('OFF');

        $api->call($cmd);
    }

    /**
     * @return string[]
     */
    protected function getDefaultResponseHeaders(): array
    {
        return ['Server' => 'DirectAdmin Daemon v1.61.3 Registered to AXC', 'Content-Type' => 'application/json'];
    }

    /**
     * Create a test user on the directadmin test server if necessary.
     *
     * @throws DirectAdminCommandException|DirectAdminConnectionException|ReflectionException|GuzzleException
     */
    protected function createTestUser(string $name = 'tester'): void
    {
        $response = 'list[]=modifyuser&list[]=tester';
        $responseCreated = '{ "result": "", "success": "Created"}';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
            new Response(200, $this->getDefaultResponseHeaders(), $responseCreated),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $api = new DirectAdminApi($this->getTestServer(), $client);

        // Check if tester needs to be created
        $usersCmd = new ShowAllUsers();
        $userList = $api->call($usersCmd);

        if (! in_array($name, $userList->getUserList(), true)) {
            $testUser = new CreateUser()
                ->setDomain($name . '-domain.nl')
                ->setEmail($name . '@user.nl')
                ->setPasswd('secret')
                ->setUsername($name)
                ->setIp($this->getTestIp());

            $api->call($testUser);
        }
    }

    /**
     * Create a test user on the directadmin test server if necessary.
     *
     * @throws DirectAdminCommandException|ReflectionException|GuzzleException
     */
    protected function createTestReseller(string $name = 'reselltest'): void
    {
        $response = 'list[]=' . $name;
        $responseCreated = '{"extended": "true","result": "<b>Reseller only got 0 of their 2 ips.</b> Unix User created successfully User\'s System Quotas set User\'s data directory created successfully Domains directory created successfully Domains directory created successfully in user\'s home Domain Created Successfully User added to ssh config file. Reseller\'s package directory created successfully Reseller created ","success": "User reselltest created"}';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
            new Response(200, $this->getDefaultResponseHeaders(), $responseCreated),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $api = new DirectAdminApi($this->getTestServer(), $client);

        // Check if tester needs to be created
        $resellersCmd = new ShowResellers();

        $resellerList = $api->call($resellersCmd);

        if (! in_array($name, $resellerList->getResellerList(), true)) {
            $testUser =  new CreateReseller()
                ->setDomain($name . '-domain.nl')
                ->setEmail($name . '@user.nl')
                ->setPasswd('secret')
                ->setUsername($name)
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

            $api->call($testUser);
        }
    }

    /**
     * Delete the test user from the server if necessary.
     *
     * @throws DirectAdminCommandException|DirectAdminConnectionException|ReflectionException|GuzzleException
     */
    protected function deleteTestUser(string $name = 'tester'): void
    {
        $response = '';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        // api
        $api = new DirectAdminApi($this->getTestServer(), $client);

        // Check if tester needs to be created
        $usersCmd = new ShowAllUsers();
        $userList = $api->call($usersCmd);

        if (in_array($name, $userList->getUserList(), true)) {
            // Cleanup user
            $delete = new DeleteUsers()->addUser($name);
            $api->call($delete);
        }
    }

    /**
     * Delete the test user from the server if necessary.
     *
     * @throws DirectAdminCommandException|ReflectionException|GuzzleException
     */
    protected function deleteTestReseller(string $name = 'reselltest'): void
    {
        $response = '';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        // api
        $api = new DirectAdminApi($this->getTestServer(), $client);

        // Check if tester needs to be created
        $resellerCmd = new ShowResellers();

        $resellerList = $api->call($resellerCmd);

        if (in_array($name, $resellerList->getResellerList(), true)) {
            // Cleanup user
            $delete = new DeleteUsers()->addUser($name);
            $api->call($delete);
        }
    }

    /**
     * Create a test login key on the directadmin test server.
     *
     * @throws DirectAdminCommandException|DirectAdminConnectionException|ReflectionException|GuzzleException
     */
    protected function createTestKey(string $name = 'testerkey'): void
    {
        $response = 'HASHURL%32mz%39PjGu=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dyes%26created%25%35Fby%3D%25%33%31%25%33%30%25%33%39%25%32E%25%33%37%25%33%32%25%32E%25%33%38%25%33%37%25%32E%25%33%32%25%33%35%25%33%34%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%34%25%33%37%25%33%38%25%33%39%25%33%36%25%33%35%26expiry%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%37%25%33%33%25%33%38%25%33%31%25%33%36%25%33%35%26key%3Dhash%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&HASHURL%32nFDV%32%30%37=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dyes%26created%25%35Fby%3D%25%33%31%25%33%30%25%33%39%25%32E%25%33%37%25%33%32%25%32E%25%33%38%25%33%37%25%32E%25%33%32%25%33%35%25%33%34%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%35%25%33%35%25%33%39%25%33%37%25%33%32%25%33%32%26expiry%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%38%25%33%31%25%33%38%25%33%39%25%33%32%25%33%32%26key%3Dhash%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&HASHURL%34xpCxbDs=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dyes%26created%25%35Fby%3D%25%33%31%25%33%30%25%33%39%25%32E%25%33%37%25%33%32%25%32E%25%33%38%25%33%37%25%32E%25%33%32%25%33%35%25%33%34%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%35%25%33%35%25%33%34%25%33%39%25%33%37%25%33%35%26expiry%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%38%25%33%31%25%33%34%25%33%31%25%33%37%25%33%35%26key%3Dhash%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&HASHURL%36aoleUQJ=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dyes%26created%25%35Fby%3D%25%33%31%25%33%30%25%33%39%25%32E%25%33%37%25%33%32%25%32E%25%33%38%25%33%37%25%32E%25%33%32%25%33%35%25%33%34%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%35%25%33%36%25%33%34%25%33%31%25%33%39%25%33%37%26expiry%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%38%25%33%32%25%33%33%25%33%33%25%33%39%25%33%37%26key%3Dhash%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&HASHURL%39%33pqcYz%32=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dyes%26created%25%35Fby%3D%25%33%31%25%33%30%25%33%39%25%32E%25%33%37%25%33%32%25%32E%25%33%38%25%33%37%25%32E%25%33%32%25%33%35%25%33%34%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%35%25%33%35%25%33%39%25%33%36%25%33%33%25%33%39%26expiry%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%38%25%33%31%25%33%38%25%33%38%25%33%33%25%33%39%26key%3Dhash%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&HASHURLUa%33xdbvw=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dyes%26created%25%35Fby%3D%25%33%35%25%33%32%25%32E%25%33%32%25%33%35%25%33%31%25%32E%25%33%34%25%33%38%25%32E%25%33%31%25%33%30%25%33%31%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%34%25%33%39%25%33%34%25%33%39%25%33%31%25%33%33%26expiry%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%37%25%33%35%25%33%34%25%33%31%25%33%31%25%33%33%26key%3Dhash%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&HASHURLdcQShHr%37=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dyes%26created%25%35Fby%3D%25%33%31%25%33%30%25%33%39%25%32E%25%33%37%25%33%32%25%32E%25%33%38%25%33%37%25%32E%25%33%32%25%33%35%25%33%34%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%35%25%33%36%25%33%33%25%33%34%25%33%30%25%33%31%26expiry%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%38%25%33%32%25%33%32%25%33%36%25%33%30%25%33%31%26key%3Dhash%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&HASHURLlrILs%37%32P=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dyes%26created%25%35Fby%3D%25%33%34%25%33%30%25%32E%25%33%37%25%33%30%25%32E%25%33%36%25%33%38%25%32E%25%33%31%25%33%35%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%34%25%33%39%25%33%34%25%33%39%25%33%35%25%33%30%26expiry%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%37%25%33%35%25%33%34%25%33%31%25%33%35%25%33%30%26key%3Dhash%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&HASHURLmtNhfirX=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dyes%26created%25%35Fby%3D%25%33%34%25%33%30%25%32E%25%33%37%25%33%39%25%32E%25%33%32%25%33%35%25%33%35%25%32E%25%33%31%25%33%34%25%33%34%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%34%25%33%39%25%33%34%25%33%39%25%33%36%25%33%30%26expiry%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%37%25%33%35%25%33%34%25%33%31%25%33%36%25%33%30%26key%3Dhash%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&HASHURLpRnGQ%30oz=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dyes%26created%25%35Fby%3D%25%33%31%25%33%30%25%33%39%25%32E%25%33%37%25%33%32%25%32E%25%33%38%25%33%37%25%32E%25%33%32%25%33%35%25%33%34%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%35%25%33%36%25%33%34%25%33%30%25%33%39%25%33%38%26expiry%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%38%25%33%32%25%33%33%25%33%32%25%33%39%25%33%38%26key%3Dhash%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&HASHURLsAnUdPfm=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dyes%26created%25%35Fby%3D%25%33%31%25%33%30%25%33%39%25%32E%25%33%37%25%33%32%25%32E%25%33%38%25%33%37%25%32E%25%33%32%25%33%35%25%33%34%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%35%25%33%36%25%33%33%25%33%38%25%33%36%25%33%39%26expiry%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%38%25%33%32%25%33%33%25%33%30%25%33%36%25%33%39%26key%3Dhash%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&HASHURLswFOtzc%39=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dyes%26created%25%35Fby%3D%25%33%31%25%33%30%25%33%39%25%32E%25%33%37%25%33%32%25%32E%25%33%38%25%33%37%25%32E%25%33%32%25%33%35%25%33%34%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%34%25%33%37%25%33%38%25%33%39%25%33%31%25%33%34%26expiry%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%37%25%33%33%25%33%38%25%33%31%25%33%31%25%33%34%26key%3Dhash%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&HASHURLvuY%38fUkW=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dyes%26created%25%35Fby%3D%25%33%31%25%33%30%25%33%39%25%32E%25%33%37%25%33%32%25%32E%25%33%38%25%33%37%25%32E%25%33%32%25%33%35%25%33%34%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%34%25%33%38%25%33%33%25%33%33%25%33%37%25%33%31%26expiry%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%37%25%33%34%25%33%32%25%33%35%25%33%37%25%33%31%26key%3Dhash%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&HASHURLypnfQ%30%36%35=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dyes%26created%25%35Fby%3D%25%33%31%25%33%30%25%33%39%25%32E%25%33%37%25%33%32%25%32E%25%33%38%25%33%37%25%32E%25%33%32%25%33%35%25%33%34%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%35%25%33%36%25%33%32%25%33%39%25%33%36%25%33%34%26expiry%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%38%25%33%32%25%33%32%25%33%31%25%33%36%25%33%34%26key%3Dhash%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&HASHURLzuRcW%34ff=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dyes%26created%25%35Fby%3D%25%33%34%25%33%30%25%32E%25%33%37%25%33%39%25%32E%25%33%32%25%33%35%25%33%35%25%32E%25%33%31%25%33%34%25%33%34%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%34%25%33%39%25%33%34%25%33%38%25%33%39%25%33%32%26expiry%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%37%25%33%35%25%33%34%25%33%30%25%33%39%25%33%32%26key%3Dhash%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&dontkethree=allow%25%35Fhtm%3Dno%26clear%25%35Fkey%3Dno%26created%25%35Fby%3D%25%33%31%25%33%30%25%33%34%25%32E%25%33%34%25%33%31%25%32E%25%33%31%25%33%34%25%33%30%25%32E%25%33%32%25%33%32%25%33%36%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%34%25%33%37%25%33%39%25%33%38%25%33%37%25%33%35%25%33%36%26expiry%3D%25%33%30%26key%3D%25%32%34%25%33%36%25%32%34%25%33%30jxJw%25%33%35QI%25%32%34BxPzuqruJs%25%33%31JVI%25%33%31CpIPVlyrYj%25%33%30L%25%33%34AVyuxo%25%33%34BCa%25%33%31tbzJO%25%33%32ieSo%25%33%35k%25%33%35HNWzXhsLqvM%25%33%32FEV%25%33%32kHDda%25%33%37%25%33%33xquQ%25%33%33FFsAs%25%33%30%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&modifyme=allow%25%35Fhtm%3Dno%26clear%25%35Fkey%3Dno%26created%25%35Fby%3D%25%33%31%25%33%30%25%33%39%25%32E%25%33%37%25%33%32%25%32E%25%33%38%25%33%37%25%32E%25%33%32%25%33%35%25%33%34%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%39%25%33%39%25%33%35%25%33%36%25%33%34%25%33%31%25%33%39%25%33%38%26expiry%3D%25%33%30%26key%3D%25%32%34%25%33%36%25%32%34%25%33%30WadYWuk%25%32%34w%25%32EkLUlh%25%33%31%25%33%33zdE%25%33%38McSIeM%25%33%30gSIJkm%25%32EwiMDg%25%33%32gOhW%25%33%36SRtM%25%33%32jwVkM%25%33%36jwU%25%33%38%25%33%35GAns%25%33%34Wwn%25%33%30MibtEtoMIDPCmix%25%33%33SIrhNa%25%33%30%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%30&test=allow%25%35Fhtm%3Dyes%26clear%25%35Fkey%3Dno%26created%25%35Fby%3D%25%33%32%25%33%31%25%33%32%25%32E%25%33%32%25%33%30%25%33%33%25%32E%25%33%32%25%33%34%25%32E%25%33%31%25%33%31%25%33%30%26date%25%35Fcreated%3D%25%33%31%25%33%35%25%33%38%25%33%32%25%33%35%25%33%34%25%33%35%25%33%31%25%33%30%25%33%39%26expiry%3D%25%33%30%26key%3D%25%32%34%25%33%36%25%32%34zo%25%33%39ldZPD%25%32%34XnMoPtFDgqSU%25%32FJH%25%32FbBn%25%33%36fVHQkTVdXIbBU%25%33%39wiGLJgiSbC%25%33%37MHCd%25%33%33g%25%33%34%25%33%37v%25%33%35eRFJ%25%33%34P%25%33%34QYjI%25%33%31L%25%33%32kU%25%33%34V%25%33%30qLjRa%25%33%38aFLw%25%33%30%25%33%30%26max%25%35Fuses%3D%25%33%30%26uses%3D%25%33%33%25%33%32%25%33%35%25%33%30';
        $responseCreate = 'error=0&text=Key%20Created%2E%20Take%20note%20of%20it%27s%20value%20and%20keep%20it%20safe%2E&details=vzJbqtARc%26%23%35%31%3BrnjiCRyhlLQ%26%23%35%30%3BljQ%26%23%35%32%3BOpU%26%23%35%30%3BqS%26%23%35%34%3BwJBuwyd%26%23%34%38%3BoujLq%26%23%35%37%3BvwB%26%23%35%30%3BRYOWx%26%23%34%38%3BvUxYjSu';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
            new Response(200, $this->getDefaultResponseHeaders(), $responseCreate),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $api = new DirectAdminApi($this->getTestServer(), $client);

        // Check if tester needs to be created
        $loginKeysCmd = new ShowLoginKeys();
        $keyList = $api->call($loginKeysCmd)->getLoginKeys();

        if (! key_exists($name, $keyList)) {
            $testKey = new CreateLoginKey()
                ->setKeyName($name)
                ->setExpires(false)
                ->setCurrentPassword((string) $api->getConnection()->getServer()->getPassword());

            $api->call($testKey);
        }
    }

    /**
     * Delete one or more test login keys on the directadmin test server.
     *
     * @param array<string>|string $keys
     *
     * @throws DirectAdminCommandException|ReflectionException|GuzzleException
     */
    protected function deleteTestKeys(array|string $keys): void
    {
        $response = 'error=0&text=Key%28s%29%20Deleted&details=%3Ca%20href%3D%22CMD%5FLOGIN%5FKEYS%22%3EBack%3C%2Fa%3E';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $api = new DirectAdminApi($this->getTestServer(), $client);
        $cmd = new DeleteLoginKeys();

        if (! is_array($keys)) {
            $cmd->addKey($keys);
        } else {
            $cmd->setKeys($keys);
        }

        $api->call($cmd);
    }

    /**
     * Delete one or more test user packages on the directadmin test server.
     *
     * @param array<string>|string $packages
     *
     * @throws DirectAdminCommandException|ReflectionException|GuzzleException
     */
    protected function deleteTestPackages(array|string $packages): void
    {
        $response = 'error=0&text=Deleted&details=';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $api = new DirectAdminApi($this->getTestServer(), $client);

        $cmd = new DeleteUserPackage();

        if (! is_array($packages)) {
            $cmd->addPackage($packages);
        } else {
            $cmd->setPackages($packages);
        }

        $api->call($cmd);
    }

    /**
     * Delete one or more test user packages on the directadmin test server.
     *
     * @param array<string>|string $packages
     *
     * @throws DirectAdminCommandException|ReflectionException|GuzzleException
     */
    protected function deleteResellerTestPackages(array|string $packages): void
    {
        $response = 'error=0&text=Deleted&details=';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $api = new DirectAdminApi($this->getTestServer(), $client);

        $cmd = new DeleteResellerPackage();

        if (! is_array($packages)) {
            $cmd->addPackage($packages);
        } else {
            $cmd->setPackages($packages);
        }

        $api->call($cmd);
    }

    protected function getTestServer(): DirectAdminTestServer
    {
        return new DirectAdminTestServer('', '', '', false, 'sandwave.io', 3214);
    }

    protected function getTestIp(): string
    {
        return self::$ip;
    }
}
