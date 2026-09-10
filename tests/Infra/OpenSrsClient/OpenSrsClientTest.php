<?php

declare(strict_types=1);

namespace Tests\Infra\OpenSrsClient;

use GuzzleHttp\Client;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\Domains\DTO\CheckResult;
use Waterfront\Infra\OpenSrsClient\Fakers\ConnectionFaker;
use Waterfront\Infra\OpenSrsClient\Fakers\OpenSrsClientFaker;
use Waterfront\Infra\OpenSrsClient\OpenSrsClient;

#[CoversClass(OpenSrsClient::class)]
#[CoversClass(OpenSrsClientFaker::class)]
class OpenSrsClientTest extends TestCase
{
    private OpenSrsClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new OpenSrsClientFaker(new Client(), new ConnectionFaker());
    }

    #[Test]
    public function itChecksADomain(): void
    {
        $result = $this->client->checkDomain('example.org');

        self::assertSame('example.org', $result->getDomain());
        self::assertSame(CheckResult::STATUS_FREE, $result->getStatus());
    }

    #[Test]
    public function itRetrievesADomain(): void
    {
        $result = $this->client->retrieveDomain('example.org');

        self::assertSame('example.org', (string) $result->getDomain());
        self::assertTrue($result->getAutoRenew());
    }

    #[Test]
    public function itRetrievesTheAuthCode(): void
    {
        self::assertSame('aB3-xYz-9Qw', $this->client->retrieveAuthCode('example.org'));
    }

    #[Test]
    public function itUpdatesNameserversWithoutError(): void
    {
        $this->expectNotToPerformAssertions();

        $this->client->updateNameServers('example.org', [new Nameserver('ns1.example.com')]);
    }
}
