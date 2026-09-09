<?php

declare(strict_types=1);

namespace Tests\Support\Helpers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Waterfront\Support\Helpers\IdnHelper;

#[CoversClass(IdnHelper::class)]
class IdnHelperTest extends TestCase
{
    #[DataProvider('toAsciiDataProvider')]
    #[Test]
    public function convertsValueToAscii(string $value, string $expected): void
    {
        self::assertSame($expected, IdnHelper::toAscii($value));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function toAsciiDataProvider(): array
    {
        return [
            'unicode domain' => [
                'sportgemälde.de',
                'xn--sportgemlde-s8a.de',
            ],
            'mixed-case ascii labels' => [
                'NXl18ZS0Mzz8EImpRmKr.sectigo.com',
                'NXl18ZS0Mzz8EImpRmKr.sectigo.com',
            ],
            'mixed ascii and unicode labels' => [
                'NXl18ZS0Mzz8EImpRmKr.sportgemälde.de',
                'NXl18ZS0Mzz8EImpRmKr.xn--sportgemlde-s8a.de',
            ],
            'wildcard label' => [
                '*.sportgemälde.de',
                '*.xn--sportgemlde-s8a.de',
            ],
            'underscore labels' => [
                '_sip._tcp.sportgemälde.de',
                '_sip._tcp.xn--sportgemlde-s8a.de',
            ],
            'trailing dot' => [
                'sportgemälde.de.',
                'xn--sportgemlde-s8a.de.',
            ],
        ];
    }
}
