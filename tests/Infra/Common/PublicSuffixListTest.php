<?php

declare(strict_types=1);

namespace Tests\Infra\Common;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Infra\Common\PublicSuffixList;
use Waterfront\Infra\Configuration\ConfigurationInterface;

#[CoversClass(PublicSuffixList::class)]
class PublicSuffixListTest extends IntegrationTestCase
{
    #[Test]
    public function publicSuffixList(): void
    {
        $publicSuffixList = self::resolve(PublicSuffixList::class);

        $domain = 'subdomain.domain.nl';

        $registrableDomain = $publicSuffixList->getRegistrableDomain($domain);
        $tld = $publicSuffixList->getTld($domain);

        self::assertSame('domain.nl', $registrableDomain);
        self::assertSame('nl', $tld);
    }

    #[DataProvider('hostProvider')]
    #[Test]
    public function getHostFromUrlOrDomain(?string $expectedHost, string $value): void
    {
        $publicSuffixList = new PublicSuffixList(
            self::createStub(ConfigurationInterface::class),
            false,
        );

        self::assertSame($expectedHost, $publicSuffixList->getHostFromUrlOrDomain($value));
    }

    /**
     * @return array<string, array{string|null, string}>
     */
    public static function hostProvider(): array
    {
        return [
            'domain' => ['example.com', 'example.com'],
            'domain with path' => ['example.com', 'example.com/path'],
            'domain with query' => ['example.com', 'example.com?x=1'],
            'domain with path and query' => ['example.com', 'example.com/path?x=1'],
            'schemed url' => ['example.com', 'https://example.com/path?x=1'],
            'scheme without host' => [null, 'mailto:example.com'],
            'path only' => [null, '/path'],
            'query only' => [null, '?x=1'],
        ];
    }
}
