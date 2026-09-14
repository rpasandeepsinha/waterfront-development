<?php

declare(strict_types=1);

namespace Tests\Domain\DNS\Generators;

use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Waterfront\Domain\DNS\Exceptions\DnsVanityTldCountMismatchException;
use Waterfront\Domain\DNS\Generators\VanityNameserverGenerator;

#[CoversClass(VanityNameserverGenerator::class)]
class VanityNameserverGeneratorTest extends TestCase
{
    /**
     * @var array<int, string>
     */
    private static array $vanityTlds = ['dns-testdomain.nl', 'dns-testdomain.com', 'dns-testdomain.net'];

    /**
     * @param array<int, int|string> $ns1
     * @param array<int, int|string> $ns2
     * @param array<int, int|string> $ns3
     *
     * @throws DnsVanityTldCountMismatchException
     */
    #[DataProvider('providerDomains')]
    #[Test]
    public function generateVanityNames(string $domain, array $ns1, array $ns2, array $ns3): void
    {
        $nameservers = new VanityNameserverGenerator()->generateVanityNames(
            domain: $domain,
            vanityTlds: self::$vanityTlds,
        );

        foreach ($nameservers as $key => $nameserver) {
            $identityParts = $this->getIdentityParts($nameserver);
            $vanityTld = $this->getVanityTld($nameserver);

            $nsCount = $key + 1;
            $ns = match ($nsCount) {
                1 => $ns1,
                2 => $ns2,
                3 => $ns3,
                default => null,
            };

            self::assertIsArray($ns);
            self::assertSame('ns' . $ns[0], $identityParts[0]);
            self::assertSame($ns[1], $vanityTld);
        }
    }

    /**
     * Some domains may result in 0 in the vanity name like ns-0-a-, when this happens the max function makes sure
     * that not 0 is used but 1. This behavior is tested in this test.
     */
    #[DataProvider('providerDomainsResolvesToZero')]
    #[Test]
    public function generateDomainsThatCanResolveToZero(string $domain): void
    {
        $nameservers = new VanityNameserverGenerator()->generateVanityNames(
            domain: $domain,
            vanityTlds: self::$vanityTlds,
        );

        foreach ($nameservers as $nameserver) {
            $mainParts = explode('.', $nameserver);
            $identifierParts = explode('ns', $mainParts[0]);

            self::assertGreaterThanOrEqual(1, $identifierParts[1]);
            self::assertLessThanOrEqual(254, $identifierParts[1]);
        }
    }

    #[Test]
    public function mismatchVanityTldCount(): void
    {
        $this->expectException(DnsVanityTldCountMismatchException::class);
        $this->expectExceptionMessageIs('There are 3 vanitytlds required - 1 given');

        new VanityNameserverGenerator()->generateVanityNames(
            domain: 'test-domain.nl',
            vanityTlds: [self::$vanityTlds[0]],
        );
    }

    public static function providerDomainsResolvesToZero(): Generator
    {
        yield 'Domain : considine.biz' => ['api.considine.biz'];
        yield 'Domain : spinka.info' => ['spinka.info'];
        yield 'Domain : harris.com' => ['harris.com'];
        yield 'Domain : hamill.info' => ['hamill.info'];
        yield 'Domain : koch.com' => ['koch.com'];
    }

    public static function providerDomains(): Generator
    {
        yield 'Domain : domain.nl' => [
            'domain.nl',
            [115, self::$vanityTlds[0]],
            [139, self::$vanityTlds[1]],
            [56, self::$vanityTlds[2]],
        ];
        yield 'Domain : this-is-a-testing-domain.com' => [
            'this-is-a-testing-domain.com',
            [148, self::$vanityTlds[0]],
            [253, self::$vanityTlds[1]],
            [115, self::$vanityTlds[2]],
        ];
        yield 'Domain :google.nl' => [
            'google.nl',
            [176, self::$vanityTlds[0]],
            [114, self::$vanityTlds[1]],
            [59, self::$vanityTlds[2]],
        ];
        yield 'Domain : domain1908.info' => [
            'domain1908.info',
            [101, self::$vanityTlds[0]],
            [197, self::$vanityTlds[1]],
            [74, self::$vanityTlds[2]],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function getIdentityParts(string $nameserver): array
    {
        $parts = explode('.', $nameserver);
        $identityPartString = $parts[0];

        return explode('-', $identityPartString);
    }

    private function getVanityTld(string $nameserver): string
    {
        $parts = explode('.', $nameserver);
        unset($parts[0]);

        return implode('.', $parts);
    }
}
