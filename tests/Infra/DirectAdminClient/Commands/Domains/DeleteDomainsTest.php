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
use Waterfront\Infra\DirectAdminClient\Commands\Domains\DeleteDomains;
use Waterfront\Infra\DirectAdminClient\DirectAdminApi;

#[CoversClass(DeleteDomains::class)]
class DeleteDomainsTest extends DirectAdminTestCase
{
    private DeleteDomains $deleteDomains;

    private DirectAdminApi $api;

    protected function setUp(): void
    {
        parent::setUp();

        $server = $this->getTestServer();
        $this->api = new DirectAdminApi($server);
        $this->deleteDomains = new DeleteDomains();
    }

    #[Test]
    public function deleteSingleDomain(): void
    {
        $this->createTestUser('deldomain');
        $domain = 'delete-me';

        $responseSuccess = '{"extended": "true", "result": "delete-me.nl deleted successfully.\ndelete-me.nl\'s config files have been removed\nDeleting domain delete-me.nl from the SpamExperts<br/>Domain has been deleted from the SpamExperts\n\n\n", "success": "Domain Deletion Results"}';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $responseSuccess),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $this->api = new DirectAdminApi($this->getTestServer(), $client);

        $this->deleteDomains
            ->addDomain($domain);

        $this->api->call($this->deleteDomains);

        self::assertTrue($this->deleteDomains->hasSucceeded());
    }
}
