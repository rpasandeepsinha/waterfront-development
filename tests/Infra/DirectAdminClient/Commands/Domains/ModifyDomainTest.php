<?php

declare(strict_types=1);

namespace Tests\Infra\DirectAdminClient\Commands\Domains;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Testing\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Infra\DirectAdminClient\DirectAdminTestCase;
use Waterfront\Infra\DirectAdminClient\Commands\Domains\ModifyDomain;
use Waterfront\Infra\DirectAdminClient\DirectAdminApi;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;

#[CoversClass(ModifyDomain::class)]
class ModifyDomainTest extends DirectAdminTestCase
{
    private ModifyDomain $modifyDomain;

    private DirectAdminApi $api;

    protected function setUp(): void
    {
        parent::setUp();

        $server = $this->getTestServer();
        $this->modifyDomain = new ModifyDomain();
        $this->api = new DirectAdminApi($server);
    }

    protected function tearDown(): void
    {
        $this->deleteTestUser('modifydomain');

        parent::tearDown();
    }

    #[Test]
    public function aDomainOfAUserCanBeModified(): void
    {
        $json = '{"success": "The domain has been modified"}';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $json),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $this->api = new DirectAdminApi($this->getTestServer(), $client);

        $testUser = 'modifydomain';
        $this->createTestUser($testUser);
        $domain = $testUser . '-domain.nl';

        $this->modifyDomain
            ->setDomain($domain)
            ->setSsl('ON')
            ->setUbandwidth('ON')
            ->setUquota('ON');

        $modifyDomain = $this->api
            ->loginAs($testUser)
            ->call($this->modifyDomain);

        $successResponse = '"success": "The domain has been modified"';

        $response = $modifyDomain->getResponseBody();

        Assert::assertTrue($modifyDomain->hasSucceeded());
        Assert::assertStringContainsString($successResponse, $response ?? '');
    }

    #[Test]
    public function aDomainNotOwnedCannotBeModified(): void
    {
        $this->createTestUser('modifydomain');
        $domain = 'modifydomain-domain.nl';

        $responseError = '{"error": "Unable to modify that domain", "result": "It does not belong to you"}';
        $exceptionMessage = 'Failed [ModifyDomain]: Unable to modify that domain - It does not belong to you';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $responseError),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $this->api = new DirectAdminApi($this->getTestServer(), $client);

        $this->modifyDomain
            ->setDomain($domain)
            ->setSsl('ON');

        $this->expectException(DirectAdminCommandException::class);
        $this->expectExceptionMessageIs($exceptionMessage);

        $this->api->call($this->modifyDomain);
    }

    #[Test]
    public function aBandwidthNeedsToBeSet(): void
    {
        $testUser = 'modifydomain';
        $this->createTestUser($testUser);
        $domain = $testUser . '-domain.nl';

        $responseError = '{"error": "Unable to modify that domain", "result": "You must either provide a valid bandwidth, or specify unlimited"}';
        $exceptionMessage = 'Failed [ModifyDomain]: Unable to modify that domain - You must either provide a valid bandwidth, or specify unlimited';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $responseError),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $this->api = new DirectAdminApi($this->getTestServer(), $client);

        $this->modifyDomain
            ->setDomain($domain)
            ->setSsl('ON');

        $this->expectException(DirectAdminCommandException::class);
        $this->expectExceptionMessageIs($exceptionMessage);

        $this->api->loginAs($testUser)->call($this->modifyDomain);
    }

    #[Test]
    public function aDiskquotaNeedsToBeSet(): void
    {
        $testUser = 'modifydomain';
        $this->createTestUser($testUser);
        $domain = $testUser . '-domain.nl';

        $responseError = '{"error": "Unable to modify that domain", "result": "You must either provide a valid disk quota, or specify unlimited"}';
        $exceptionMessage = 'Unable to modify that domain - You must either provide a valid disk quota, or specify unlimited';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $responseError),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $this->api = new DirectAdminApi($this->getTestServer(), $client);

        $this->modifyDomain
            ->setDomain($domain)
            ->setSsl('ON')
            ->setUbandwidth('ON');

        $this->expectException(DirectAdminCommandException::class);
        $this->expectExceptionMessageIsOrContains($exceptionMessage);

        $this->api->loginAs($testUser)->call($this->modifyDomain);
    }
}
