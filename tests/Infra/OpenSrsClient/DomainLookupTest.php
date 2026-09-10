<?php

declare(strict_types=1);

namespace Tests\Infra\OpenSrsClient;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Domain\Domains\DTO\CheckResult;
use Waterfront\Infra\OpenSrsClient\Messages\Connection;
use Waterfront\Infra\OpenSrsClient\Messages\DomainLookupRequest;
use Waterfront\Infra\OpenSrsClient\Messages\DomainLookupResponse;
use Waterfront\Infra\OpenSrsClient\Support\OpsXml;

#[CoversClass(DomainLookupRequest::class)]
#[CoversClass(DomainLookupResponse::class)]
class DomainLookupTest extends TestCase
{
    #[Test]
    public function itBuildsTheLookupRequest(): void
    {
        $request = new DomainLookupRequest($this->client(), $this->connection(), 'example.org');

        self::assertSame([
            'protocol'   => 'XCP',
            'object'     => 'DOMAIN',
            'action'     => 'LOOKUP',
            'attributes' => [
                'domain'   => 'example.org',
                'no_cache' => '1',
            ],
        ], OpsXml::decode($request->getXml()));
    }

    #[Test]
    public function itMapsAnAvailableDomainToFree(): void
    {
        $result = $this->response('opensrs_lookup_response_available.xml')->getResult('example.org');

        self::assertSame('example.org', $result->getDomain());
        self::assertSame(CheckResult::STATUS_FREE, $result->getStatus());
    }

    #[Test]
    public function itMapsATakenDomainToActiveAndKeepsThePremiumReason(): void
    {
        $result = $this->response('opensrs_lookup_response_taken.xml')->getResult('example.org');

        self::assertSame(CheckResult::STATUS_ACTIVE, $result->getStatus());
        self::assertSame('Premium Name', $result->getReason());
        self::assertTrue($result->isPremium());
    }

    private function response(string $fixture): DomainLookupResponse
    {
        return new DomainLookupResponse(new Response(
            200,
            ['Content-Type' => 'text/xml'],
            (string) file_get_contents(__DIR__ . '/data/' . $fixture),
        ));
    }

    private function client(): Client
    {
        return new Client();
    }

    private function connection(): Connection
    {
        return new Connection('https://horizon.opensrs.net:55443', 'reseller', 'key');
    }
}
