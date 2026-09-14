<?php

declare(strict_types=1);

namespace Tests\Infra\RtrClient\Clients;

use Carbon\CarbonImmutable;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\RequestInterface;
use Tests\Infra\RtrClient\Helpers\MockedClientFactory;
use Tests\TestCase;
use Waterfront\Infra\RtrClient\Clients\RevisionApi;
use Waterfront\Infra\RtrClient\DTO\Revision;
use Waterfront\Infra\RtrClient\Enums\RevisionType;

#[CoversClass(RevisionApi::class)]
class RevisionApiTest extends TestCase
{
    private const string DOMAIN = 'far-pupil.com';

    #[Test]
    public function revisionsReturnsRevisionsWithoutDateFilters(): void
    {
        $client = MockedClientFactory::makeAuthorizedClient(
            [new Response(200, [], $this->getResponseBody())],
            function (RequestInterface $request): void {
                self::assertStringContainsString(
                    'v2/domains/' . self::DOMAIN . '/revisions',
                    $request->getUri()->getPath(),
                );
                self::assertStringNotContainsString('from=', (string) $request->getUri());
                self::assertStringNotContainsString('to=', (string) $request->getUri());
            },
        );

        $revisionApi = new RevisionApi($client);
        $revisions = $revisionApi->listRevisions(self::DOMAIN);

        self::assertCount(7, $revisions);
        self::assertContainsOnlyInstancesOf(Revision::class, $revisions);

        $firstRevision = $revisions[0];
        self::assertSame(2221463478, $firstRevision->revision);
        self::assertSame(RevisionType::MOD, $firstRevision->type);
        self::assertSame(3, $firstRevision->processId);
        self::assertSame('2026-06-25T12:33:32Z', $firstRevision->date->format('Y-m-d\TH:i:s\Z'));
        self::assertSame(self::DOMAIN, $firstRevision->entity->domainName);

        $lastRevision = $revisions[6];
        self::assertSame(1724681384, $lastRevision->revision);
        self::assertSame(RevisionType::ADD, $lastRevision->type);
        self::assertSame(1724681355, $lastRevision->processId);
    }

    #[Test]
    public function revisionsPassesDateFiltersAsQueryParameters(): void
    {
        $from = CarbonImmutable::parse('2026-01-01T00:00:00Z');
        $to = CarbonImmutable::parse('2026-06-25T23:59:59Z');

        $client = MockedClientFactory::makeAuthorizedClient(
            [new Response(200, [], $this->getResponseBody())],
            function (RequestInterface $request) use ($from, $to): void {
                $query = (string) $request->getUri();
                self::assertStringContainsString('from=' . urlencode($from->format('Y-m-d\TH:i:s\Z')), $query);
                self::assertStringContainsString('to=' . urlencode($to->format('Y-m-d\TH:i:s\Z')), $query);
            },
        );

        $revisionApi = new RevisionApi($client);
        $revisions = $revisionApi->listRevisions(self::DOMAIN, $from, $to);

        self::assertCount(7, $revisions);
        self::assertContainsOnlyInstancesOf(Revision::class, $revisions);
    }

    #[Test]
    public function revisionsReturnsEmptyArrayWhenNoRevisions(): void
    {
        $client = MockedClientFactory::makeAuthorizedClient(
            [new Response(200, [], '[]')],
        );

        $revisionApi = new RevisionApi($client);
        $revisions = $revisionApi->listRevisions(self::DOMAIN);

        self::assertSame([], $revisions);
    }

    private function getResponseBody(): string
    {
        return (string) file_get_contents(__DIR__ . '/../data/revisions.json');
    }
}
