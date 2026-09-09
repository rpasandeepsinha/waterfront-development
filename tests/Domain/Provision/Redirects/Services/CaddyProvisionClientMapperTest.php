<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Redirects\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Waterfront\Domain\Provision\Redirects\Enums\RedirectType;
use Waterfront\Domain\Provision\Redirects\Exceptions\CaddyMapperException;
use Waterfront\Domain\Provision\Redirects\Services\CaddyProvisionClientMapper;
use Waterfront\Infra\CaddyClient\DTO\RedirectRoute;
use Waterfront\Infra\CaddyClient\DTO\RedirectRouteHandle;
use Waterfront\Infra\CaddyClient\DTO\RedirectRouteMatch;
use Waterfront\Infra\CaddyClient\Enums\RedirectType as CaddyRedirectType;

#[CoversClass(CaddyProvisionClientMapper::class)]
class CaddyProvisionClientMapperTest extends TestCase
{
    #[DataProvider('caddyRedirectTypeProvider')]
    public function testGetCaddyRedirectType(
        RedirectType $redirectType,
        CaddyRedirectType $expectedCaddyRedirectType,
    ): void {
        $mapper = new CaddyProvisionClientMapper();

        $result = $mapper->getCaddyRedirectType($redirectType);

        self::assertSame($expectedCaddyRedirectType, $result);
    }

    /**
     * @param list<string>|null                $expectedPaths
     * @param array<string, list<string>>|null $expectedQuery
     */
    #[DataProvider('parseSourceMatchersProvider')]
    public function testParseSourceMatchers(
        string $fromUrl,
        string $expectedHost,
        ?array $expectedPaths,
        ?array $expectedQuery,
    ): void {
        $mapper = new CaddyProvisionClientMapper();

        $result = $mapper->parseSourceMatchers($fromUrl);

        self::assertSame($expectedHost, $result->host);
        self::assertSame($expectedPaths, $result->paths);
        self::assertSame($expectedQuery, $result->query);
    }

    /**
     * @return array<string, array{
     *     fromUrl: string,
     *     expectedHost: string,
     *     expectedPaths: list<string>|null,
     *     expectedQuery: array<string, list<string>>|null
     * }>
     */
    public static function parseSourceMatchersProvider(): array
    {
        return [
            'host only' => [
                'fromUrl' => 'example.com',
                'expectedHost' => 'example.com',
                'expectedPaths' => null,
                'expectedQuery' => null,
            ],
            'host with root path' => [
                'fromUrl' => 'example.com/',
                'expectedHost' => 'example.com',
                'expectedPaths' => null,
                'expectedQuery' => null,
            ],
            'host with path' => [
                'fromUrl' => 'example.com/foo',
                'expectedHost' => 'example.com',
                'expectedPaths' => ['/foo'],
                'expectedQuery' => null,
            ],
            'host with path and query' => [
                'fromUrl' => 'example.com/foo?x=1',
                'expectedHost' => 'example.com',
                'expectedPaths' => ['/foo'],
                'expectedQuery' => [
                    'x' => ['1'],
                ],
            ],
            'host with query only' => [
                'fromUrl' => 'example.com?x=1',
                'expectedHost' => 'example.com',
                'expectedPaths' => null,
                'expectedQuery' => [
                    'x' => ['1'],
                ],
            ],
            'host with repeated query key' => [
                'fromUrl' => 'example.com?x=1&x=2',
                'expectedHost' => 'example.com',
                'expectedPaths' => null,
                'expectedQuery' => [
                    'x' => ['1', '2'],
                ],
            ],
            'host with array-style query key' => [
                'fromUrl' => 'example.com?x[]=1&x[]=2',
                'expectedHost' => 'example.com',
                'expectedPaths' => null,
                'expectedQuery' => [
                    'x' => ['1', '2'],
                ],
            ],
            'host with indexed array-style query key' => [
                'fromUrl' => 'example.com?x[0]=1&x[1]=2',
                'expectedHost' => 'example.com',
                'expectedPaths' => null,
                'expectedQuery' => [
                    'x' => ['1', '2'],
                ],
            ],
            'host with multiple query keys' => [
                'fromUrl' => 'example.com?a=1&b=2',
                'expectedHost' => 'example.com',
                'expectedPaths' => null,
                'expectedQuery' => [
                    'a' => ['1'],
                    'b' => ['2'],
                ],
            ],
            'host with empty query value' => [
                'fromUrl' => 'example.com?x=',
                'expectedHost' => 'example.com',
                'expectedPaths' => null,
                'expectedQuery' => [
                    'x' => [''],
                ],
            ],
            'host with query flag' => [
                'fromUrl' => 'example.com?flag',
                'expectedHost' => 'example.com',
                'expectedPaths' => null,
                'expectedQuery' => [
                    'flag' => [''],
                ],
            ],
            'plus is decoded to space' => [
                'fromUrl' => 'example.com/foo?x=hello+world',
                'expectedHost' => 'example.com',
                'expectedPaths' => ['/foo'],
                'expectedQuery' => [
                    'x' => ['hello world'],
                ],
            ],
            'percent encoding is decoded' => [
                'fromUrl' => 'example.com/foo?x=hello%20world',
                'expectedHost' => 'example.com',
                'expectedPaths' => ['/foo'],
                'expectedQuery' => [
                    'x' => ['hello world'],
                ],
            ],
            'already schemed url' => [
                'fromUrl' => 'https://example.com/foo?x=1',
                'expectedHost' => 'example.com',
                'expectedPaths' => ['/foo'],
                'expectedQuery' => [
                    'x' => ['1'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, array{
     *     redirectType: RedirectType,
     *     expectedCaddyRedirectType: CaddyRedirectType
     * }>
     */
    public static function caddyRedirectTypeProvider(): array
    {
        return [
            'permanent' => [
                'redirectType' => RedirectType::PERMANENT,
                'expectedCaddyRedirectType' => CaddyRedirectType::MOVED_PERMANENTLY,
            ],
            'temporary' => [
                'redirectType' => RedirectType::TEMPORARY,
                'expectedCaddyRedirectType' => CaddyRedirectType::FOUND,
            ],
            'frame' => [
                'redirectType' => RedirectType::FRAME,
                'expectedCaddyRedirectType' => CaddyRedirectType::FRAME,
            ],
        ];
    }

    public function testGetRedirectDtoFromCaddyDtoPermanentRedirect(): void
    {
        $mapper = new CaddyProvisionClientMapper();

        $redirectRoute = new RedirectRoute(
            id: 'redirect:example-com:foo:x-1',
            match: [
                new RedirectRouteMatch(
                    host: ['example.com'],
                    path: ['/foo'],
                    query: [
                        'x' => ['1'],
                    ],
                ),
            ],
            handle: [
                new RedirectRouteHandle(
                    handler: 'static_response',
                    statusCode: 301,
                    headers: [
                        'Location' => ['https://target.example.com'],
                    ],
                ),
            ],
        );

        $result = $mapper->getRedirectDtoFromCaddyDto($redirectRoute);

        self::assertSame('example.com/foo?x=1', $result->source);
        self::assertSame('https://target.example.com', $result->destination);
        self::assertSame(RedirectType::PERMANENT, $result->redirectType);
    }

    public function testGetRedirectDtoFromCaddyDtoTemporaryRedirect(): void
    {
        $mapper = new CaddyProvisionClientMapper();

        $redirectRoute = new RedirectRoute(
            id: 'redirect:example-com:root:root',
            match: [
                new RedirectRouteMatch(
                    host: ['example.com'],
                ),
            ],
            handle: [
                new RedirectRouteHandle(
                    handler: 'static_response',
                    statusCode: 302,
                    headers: [
                        'Location' => ['https://temporary.example.com'],
                    ],
                ),
            ],
        );

        $result = $mapper->getRedirectDtoFromCaddyDto($redirectRoute);

        self::assertSame('example.com', $result->source);
        self::assertSame('https://temporary.example.com', $result->destination);
        self::assertSame(RedirectType::TEMPORARY, $result->redirectType);
    }

    public function testGetRedirectDtoFromCaddyDtoFrameRedirect(): void
    {
        $mapper = new CaddyProvisionClientMapper();

        $redirectRoute = new RedirectRoute(
            id: 'redirect:example-com:root:root',
            match: [
                new RedirectRouteMatch(
                    host: ['example.com'],
                ),
            ],
            handle: [
                new RedirectRouteHandle(
                    handler: 'static_response',
                    statusCode: 200,
                    headers: [
                        'Content-Type' => ['text/html; charset=utf-8'],
                    ],
                    body: '<html><body><iframe src="https://frame.example.com"></iframe></body></html>',
                ),
            ],
        );

        $result = $mapper->getRedirectDtoFromCaddyDto($redirectRoute);

        self::assertSame('example.com', $result->source);
        self::assertSame('https://frame.example.com', $result->destination);
        self::assertSame(RedirectType::FRAME, $result->redirectType);
    }

    public function testGetRedirectDtoFromCaddyDtoPreservesRepeatedQueryKeys(): void
    {
        $mapper = new CaddyProvisionClientMapper();

        $redirectRoute = new RedirectRoute(
            id: 'redirect:example-com:root:x-1-or-2',
            match: [
                new RedirectRouteMatch(
                    host: ['example.com'],
                    query: [
                        'x' => ['1', '2'],
                    ],
                ),
            ],
            handle: [
                new RedirectRouteHandle(
                    handler: 'static_response',
                    statusCode: 301,
                    headers: [
                        'Location' => ['https://target.example.com'],
                    ],
                ),
            ],
        );

        $result = $mapper->getRedirectDtoFromCaddyDto($redirectRoute);

        self::assertSame('example.com?x=1&x=2', $result->source);
    }

    public function testGetRedirectDtoFromCaddyDtoThrowsWhenHandleMissing(): void
    {
        $mapper = new CaddyProvisionClientMapper();

        $redirectRoute = new RedirectRoute(
            id: 'redirect:example-com:root:root',
            match: [
                new RedirectRouteMatch(
                    host: ['example.com'],
                ),
            ],
            handle: [],
        );

        $this->expectException(CaddyMapperException::class);
        $this->expectExceptionMessageIsOrContains('Handle could not be determined from Caddy DTO');

        $mapper->getRedirectDtoFromCaddyDto($redirectRoute);
    }

    public function testGetRedirectDtoFromCaddyDtoThrowsWhenHostMissing(): void
    {
        $mapper = new CaddyProvisionClientMapper();

        $redirectRoute = new RedirectRoute(
            id: 'redirect:example-com:root:root',
            match: [
                new RedirectRouteMatch(
                    path: ['/foo'],
                ),
            ],
            handle: [
                new RedirectRouteHandle(
                    handler: 'static_response',
                    statusCode: 301,
                    headers: [
                        'Location' => ['https://target.example.com'],
                    ],
                ),
            ],
        );

        $this->expectException(CaddyMapperException::class);
        $this->expectExceptionMessageIsOrContains('source could not be determined from Caddy DTO');

        $mapper->getRedirectDtoFromCaddyDto($redirectRoute);
    }

    public function testGetRedirectDtoFromCaddyDtoThrowsWhenLocationMissing(): void
    {
        $mapper = new CaddyProvisionClientMapper();

        $redirectRoute = new RedirectRoute(
            id: 'redirect:example-com:root:root',
            match: [
                new RedirectRouteMatch(
                    host: ['example.com'],
                ),
            ],
            handle: [
                new RedirectRouteHandle(
                    handler: 'static_response',
                    statusCode: 301,
                ),
            ],
        );

        $this->expectException(CaddyMapperException::class);
        $this->expectExceptionMessageIsOrContains('Destination could not be determined from Caddy DTO');

        $mapper->getRedirectDtoFromCaddyDto($redirectRoute);
    }

    public function testGetRedirectDtoFromCaddyDtoThrowsOnUnknownRedirectType(): void
    {
        $mapper = new CaddyProvisionClientMapper();

        $redirectRoute = new RedirectRoute(
            id: 'redirect:example-com:root:root',
            match: [
                new RedirectRouteMatch(
                    host: ['example.com'],
                ),
            ],
            handle: [
                new RedirectRouteHandle(
                    handler: 'static_response',
                    statusCode: 418,
                    headers: [
                        'Location' => ['https://target.example.com'],
                    ],
                ),
            ],
        );

        $this->expectException(CaddyMapperException::class);
        $this->expectExceptionMessageIsOrContains('RedirectType could not be determined from Caddy DTO');

        $mapper->getRedirectDtoFromCaddyDto($redirectRoute);
    }
}
