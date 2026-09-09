<?php

declare(strict_types=1);

namespace Tests\Infra\Caddy\Generators;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Waterfront\Infra\CaddyClient\Generators\CaddyRouteIdGenerator;

#[CoversClass(CaddyRouteIdGenerator::class)]
class CaddyRouteIdGeneratorTest extends TestCase
{
    /**
     * @param list<string>|null                $paths
     * @param array<string, list<string>>|null $query
     */
    #[DataProvider('routeIdProvider')]
    #[Test]
    public function generate(
        string $fromHost,
        ?array $paths,
        ?array $query,
        string $expectedRouteId,
    ): void {
        $generator = new CaddyRouteIdGenerator();
        $actualRouteId = $generator->generate($fromHost, $paths, $query);

        self::assertSame(
            $this->buildExpectedRouteId($fromHost, $paths, $query, $expectedRouteId),
            $actualRouteId,
        );
    }

    /**
     * @return iterable<string, array{
     *     fromHost: string,
     *     paths: list<string>|null,
     *     query: array<string, list<string>>|null,
     *     expectedRouteId: string
     * }>
     */
    public static function routeIdProvider(): iterable
    {
        yield 'root route with null paths and null query' => [
            'fromHost' => 'example.com',
            'paths' => null,
            'query' => null,
            'expectedRouteId' => 'redirect:example.com:root:root',
        ];

        yield 'root route with empty paths array and null query' => [
            'fromHost' => 'example.com',
            'paths' => [],
            'query' => null,
            'expectedRouteId' => 'redirect:example.com:root:root',
        ];

        yield 'subdomain root route' => [
            'fromHost' => 'shop.example.com',
            'paths' => null,
            'query' => null,
            'expectedRouteId' => 'redirect:shop.example.com:root:root',
        ];

        yield 'single marketing section wildcard path' => [
            'fromHost' => 'example.com',
            'paths' => ['/campaigns*'],
            'query' => null,
            'expectedRouteId' => 'redirect:example.com:campaigns-wildcard:root',
        ];

        yield 'single nested help center wildcard path' => [
            'fromHost' => 'example.com',
            'paths' => ['/help/articles/*'],
            'query' => null,
            'expectedRouteId' => 'redirect:example.com:help-articles-wildcard:root',
        ];

        yield 'multiple realistic paths on same host' => [
            'fromHost' => 'example.com',
            'paths' => ['/pricing*', '/contact*'],
            'query' => null,
            'expectedRouteId' => 'redirect:example.com:pricing-wildcard--contact-wildcard:root',
        ];

        yield 'multiple nested paths on subdomain' => [
            'fromHost' => 'api.example.com',
            'paths' => ['/v1/private/*', '/v2/public/*'],
            'query' => null,
            'expectedRouteId' => 'redirect:api.example.com:v1-private-wildcard--v2-public-wildcard:root',
        ];

        yield 'uppercase host and path are normalized to lowercase' => [
            'fromHost' => 'WWW.Example.COM',
            'paths' => ['/Products/New/*'],
            'query' => null,
            'expectedRouteId' => 'redirect:www.example.com:products-new-wildcard:root',
        ];

        yield 'host is trimmed and lowercased' => [
            'fromHost' => '  portal.example.com  ',
            'paths' => null,
            'query' => null,
            'expectedRouteId' => 'redirect:portal.example.com:root:root',
        ];

        yield 'path is trimmed' => [
            'fromHost' => 'example.com',
            'paths' => ['  /products/*  '],
            'query' => null,
            'expectedRouteId' => 'redirect:example.com:products-wildcard:root',
        ];

        yield 'slash path becomes root' => [
            'fromHost' => 'example.com',
            'paths' => ['/'],
            'query' => null,
            'expectedRouteId' => 'redirect:example.com:root:root',
        ];

        yield 'empty string path becomes root' => [
            'fromHost' => 'example.com',
            'paths' => [''],
            'query' => null,
            'expectedRouteId' => 'redirect:example.com:root:root',
        ];

        yield 'special characters in host are normalized' => [
            'fromHost' => 'client_portal.example.com',
            'paths' => null,
            'query' => null,
            'expectedRouteId' => 'redirect:client-portal.example.com:root:root',
        ];

        yield 'special characters in path are normalized' => [
            'fromHost' => 'example.com',
            'paths' => ['/Summer Sale 2026!'],
            'query' => null,
            'expectedRouteId' => 'redirect:example.com:summer-sale-2026:root',
        ];

        yield 'mixed path wildcards and separators edge case' => [
            'fromHost' => 'example.com',
            'paths' => ['/docs/v2*c'],
            'query' => null,
            'expectedRouteId' => 'redirect:example.com:docs-v2-wildcardc:root',
        ];

        yield 'multiple root like paths are preserved in order' => [
            'fromHost' => 'example.com',
            'paths' => ['/', '/pricing*'],
            'query' => null,
            'expectedRouteId' => 'redirect:example.com:root--pricing-wildcard:root',
        ];

        yield 'single query parameter' => [
            'fromHost' => 'example.com',
            'paths' => null,
            'query' => [
                'x' => ['1'],
            ],
            'expectedRouteId' => 'redirect:example.com:root:x-1',
        ];

        yield 'multiple query values for same key are sorted' => [
            'fromHost' => 'example.com',
            'paths' => null,
            'query' => [
                'x' => ['2', '1'],
            ],
            'expectedRouteId' => 'redirect:example.com:root:x-1-or-2',
        ];

        yield 'multiple query keys are sorted by key' => [
            'fromHost' => 'example.com',
            'paths' => null,
            'query' => [
                'b' => ['2'],
                'a' => ['1'],
            ],
            'expectedRouteId' => 'redirect:example.com:root:a-1--b-2',
        ];

        yield 'path and query together' => [
            'fromHost' => 'example.com',
            'paths' => ['/products'],
            'query' => [
                'utm_source' => ['newsletter'],
            ],
            'expectedRouteId' => 'redirect:example.com:products:utm-source-newsletter',
        ];

        yield 'query values are normalized like paths' => [
            'fromHost' => 'example.com',
            'paths' => null,
            'query' => [
                'q' => ['Summer Sale 2026!'],
            ],
            'expectedRouteId' => 'redirect:example.com:root:q-summer-sale-2026',
        ];

        yield 'empty query becomes root' => [
            'fromHost' => 'example.com',
            'paths' => null,
            'query' => [],
            'expectedRouteId' => 'redirect:example.com:root:root',
        ];
    }

    /**
     * @param list<string>|null                $paths
     * @param array<string, list<string>>|null $query
     */
    private function buildExpectedRouteId(
        string $fromHost,
        ?array $paths,
        ?array $query,
        string $expectedRouteId,
    ): string {
        $canonicalQuery = $query ?? [];
        ksort($canonicalQuery);

        foreach ($canonicalQuery as $key => $values) {
            sort($values);
            $canonicalQuery[$key] = $values;
        }

        $canonical = json_encode(
            [
                'host' => strtolower(trim($fromHost)),
                'paths' => $paths ?? [],
                'query' => $canonicalQuery,
            ],
            JSON_THROW_ON_ERROR,
        );

        $hash = substr(sha1($canonical), 0, 8);

        return sprintf('%s:%s', $expectedRouteId, $hash);
    }
}
