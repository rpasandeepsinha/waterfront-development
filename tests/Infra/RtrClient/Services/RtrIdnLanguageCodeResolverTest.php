<?php

declare(strict_types=1);

namespace Tests\Infra\RtrClient\Services;

use Pdp\Domain;
use Pdp\Rules;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RealtimeRegister\Domain\TLDInfo;
use Tests\TestCase;
use Waterfront\Infra\RtrClient\Services\RtrIdnLanguageCodeResolver;

#[CoversClass(RtrIdnLanguageCodeResolver::class)]
class RtrIdnLanguageCodeResolverTest extends TestCase
{
    private Rules $rules;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rules = Rules::fromString("com\nde\n");
    }

    #[DataProvider('resolveDataProvider')]
    #[Test]
    public function resolve(string $domain, TLDInfo $tldInfo, ?string $expectedLanguageCode): void
    {
        $resolver = new RtrIdnLanguageCodeResolver();
        $resolvedDomain = $this->rules->resolve(Domain::fromIDNA2008($domain));

        self::assertSame($expectedLanguageCode, $resolver->resolve($resolvedDomain, $tldInfo));
    }

    /**
     * @return array<string, array{0: string, 1: TLDInfo, 2: string|null}>
     */
    public static function resolveDataProvider(): array
    {
        $comTldInfoJsonContents = file_get_contents(
            __DIR__ . '/../../../Apps/API/Waterfront/Orders/data/rtr-tld-metadata-com.json',
        );
        self::assertIsString($comTldInfoJsonContents);

        $comTldInfoData = json_decode($comTldInfoJsonContents, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($comTldInfoData);

        $comTldInfo = TLDInfo::fromArray($comTldInfoData);

        $deTldInfoJsonContents = file_get_contents(
            __DIR__ . '/../../../Domain/Domains/Integration/response/rtr-tld-metadata-de.json',
        );
        self::assertIsString($deTldInfoJsonContents);

        $deTldInfoData = json_decode($deTldInfoJsonContents, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($deTldInfoData);

        $deTldInfo = TLDInfo::fromArray($deTldInfoData);

        return [
            'ascii domain' => [
                'example.com',
                $comTldInfo,
                null,
            ],
            'unicode german idn' => [
                'sportgemälde.com',
                $comTldInfo,
                'GER',
            ],
            'punycode german idn' => [
                'xn--sportgemlde-s8a.com',
                $comTldInfo,
                'GER',
            ],
            'uppercase punycode german idn' => [
                'XN--SPORTGEMLDE-S8A.COM',
                $comTldInfo,
                'GER',
            ],
            'polish idn falls back to first matching allowed characters' => [
                'życie.com',
                $comTldInfo,
                'POL',
            ],
            'greek idn falls back to first matching allowed characters' => [
                'δοκιμή.com',
                $comTldInfo,
                'GRE',
            ],
            'tld without language codes' => [
                'sportgemälde.de',
                $deTldInfo,
                null,
            ],
            'idn without matching language code' => [
                '例子.com',
                $comTldInfo,
                null,
            ],
        ];
    }
}
