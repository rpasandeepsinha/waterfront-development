<?php

declare(strict_types=1);

namespace Tests\Infra\DirectAdminClient\Commands\Domains;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Infra\DirectAdminClient\DirectAdminTestCase;
use Waterfront\Infra\DirectAdminClient\Commands\Domains\ShowDomain;
use Waterfront\Infra\DirectAdminClient\DirectAdminApi;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminConnectionException;

#[CoversClass(ShowDomain::class)]
class ShowDomainTest extends DirectAdminTestCase
{
    private ShowDomain $showDomain;

    private DirectAdminApi $api;

    protected function setUp(): void
    {
        parent::setUp();

        $server = $this->getTestServer();
        $this->showDomain = new ShowDomain();
        $this->api = new DirectAdminApi($server);
    }

    protected function tearDown(): void
    {
        $this->deleteTestUser('show');

        parent::tearDown();
    }

    #[Test]
    public function domainDataThrowsExceptionWithoutDomain(): void
    {
        $testUser = 'show';
        $this->createTestUser($testUser);

        $this->api = new DirectAdminApi($this->getTestServer());

        $this->expectException(DirectAdminConnectionException::class);
        $this->expectExceptionMessageIsOrContains('Cannot call ShowDomain without domain set.');

        $this->api->loginAs($testUser)->call($this->showDomain);
    }

    #[Test]
    public function domainDataCanBeRetrieved(): void
    {
        $testUser = 'show';
        $this->createTestUser($testUser);
        $domain = $testUser . '-domain.nl';

        $response = '
            {
                "show-domain.nl":
                {
                    "UseCanonicalName": "OFF",
                    "active": "yes",
                    "bandwidth": "0.0",
                    "bandwidth_limit": "unlimited",
                    "cgi": "OFF",
                    "defaultdomain": "yes",
                    "domain": "show-domain.nl",
                    "ip": "62.221.254.125",
                    "ips" :
                    [
                        "62.221.254.125"
                    ],
                    "local_mail": "yes",
                    "open_basedir": "ON",
                    "php": "ON",
                    "pointers":
                    {
                    },
                    "quota": "0.0",
                    "quota_limit": "unlimited",
                    "safemode": "OFF",
                    "ssl": "OFF",
                    "subdomain": "0",
                    "suspended": "no",
                    "username": "newtest"
                }
            }';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $this->api = new DirectAdminApi($this->getTestServer(), $client);

        $this->showDomain
            ->setDomain($domain);

        $this->api->loginAs($testUser)->call($this->showDomain);

        self::assertTrue($this->showDomain->hasSucceeded());

        $commandData = $this->showDomain->getDomainData();

        self::assertSame('0.0', $commandData['quota']);
        self::assertSame('OFF', $commandData['safemode']);
        self::assertSame('62.221.254.125', $commandData['ip']);
        self::assertSame($commandData['domain'], $domain);
    }
}
