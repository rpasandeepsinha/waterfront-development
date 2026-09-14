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
use Waterfront\Infra\DirectAdminClient\Commands\Domains\EnableDisableDKIM;
use Waterfront\Infra\DirectAdminClient\DirectAdminApi;

#[CoversClass(EnableDisableDKIM::class)]
class EnableDisableDKIMTest extends DirectAdminTestCase
{
    private const string DOMAIN = 'enabledisabledkim.nl';

    #[Test]
    public function enableDkimUser(): void
    {
        $json = sprintf('{"result": "%s: DKIM enabled", "success": "Success"}', self::DOMAIN);

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $json),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $api = new DirectAdminApi($this->getTestServer(), $client);

        $modifyDomain = $api->loginAs('admin')->call(new EnableDisableDKIM(self::DOMAIN, true));

        $successResponse = sprintf('"result": "%s: DKIM enabled"', self::DOMAIN);

        $response = $modifyDomain->getResponseBody();

        Assert::assertTrue($modifyDomain->hasSucceeded());
        Assert::assertStringContainsString($successResponse, $response ?? '');
    }
}
