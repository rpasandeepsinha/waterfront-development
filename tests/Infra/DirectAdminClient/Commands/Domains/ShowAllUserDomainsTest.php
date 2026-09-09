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
use Waterfront\Infra\DirectAdminClient\Commands\Domains\ShowAllUserDomains;
use Waterfront\Infra\DirectAdminClient\DirectAdminApi;

#[CoversClass(ShowAllUserDomains::class)]
class ShowAllUserDomainsTest extends DirectAdminTestCase
{
    private string $testUser;

    private ShowAllUserDomains $showAllUserDomains;

    private DirectAdminApi $api;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testUser = 'showalluserdomains';
        $this->showAllUserDomains = new ShowAllUserDomains($this->testUser);
        $this->api = new DirectAdminApi($this->getTestServer());
    }

    #[Test]
    public function domainDataCanBeRetrieved(): void
    {
        $response = '["test1.nl", "test2.nl"]';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $this->api = new DirectAdminApi($this->getTestServer(), $client);

        $this->api->loginAs($this->testUser)->call($this->showAllUserDomains);

        self::assertTrue($this->showAllUserDomains->hasSucceeded());

        $commandData = $this->showAllUserDomains->getDomainData();

        self::assertSame('test1.nl', $commandData[0]);
        self::assertSame('test2.nl', $commandData[1]);
    }
}
