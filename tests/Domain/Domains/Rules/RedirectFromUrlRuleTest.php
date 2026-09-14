<?php

declare(strict_types=1);

namespace Tests\Domain\Domains\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Waterfront\Domain\Domains\Rules\RedirectFromUrlRule;
use Waterfront\Infra\Common\PublicSuffixList;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(RedirectFromUrlRule::class)]
class RedirectFromUrlRuleTest extends TestCase
{
    #[DataProvider('passesProvider')]
    #[Test]
    public function passes(bool $expectedResult, string $value): void
    {
        $rules = $this->app->make(PublicSuffixList::class);
        $rule = new RedirectFromUrlRule($rules, self::createStub(TranslatorInterface::class));
        $rule->validate('domain', $value, self::assertClosureIsCalled(! $expectedResult));
    }

    /**
     * @return array<int, array{bool, string}>
     */
    public static function passesProvider(): array
    {
        return [
            // Bare domains
            [true,  'versio.com'],
            [true,  'VERSIO.COM'],
            [true,  'mkyong-info.com'],
            [true,  'versio.co.uk'],
            [true,  'versio.com.au'],
            [true,  'a-1234567890-1234567890-1234567890-1234567890-1234567890-1234-z.eu.us'],

            // Subdomains
            [true,  'sub.versio.com'],
            [true,  'www.versio.com'],
            [true,  'shop.sub.versio.com'],
            [true,  'sub.versio.co.uk'],

            // With path (no scheme)
            [true,  'versio.com/old-page'],
            [true,  'versio.com/old-page/'],
            [true,  'sub.versio.com/old-page'],
            [true,  'versio.com/products*'],
            [true,  'versio.com/bands/%*/*'],
            [true,  'versio.com/old%20page'],

            // With query parameters (no scheme)
            [true,  'versio.com?ref=yh'],
            [true,  'versio.com/old-page?ref=yh'],
            [true,  'versio.com?ref=yh&source=newsletter'],
            [true,  'versio.com?x=1&x=2'],
            [true,  'versio.com?x[]=1&x[]=2'],
            [true,  'versio.com?x[0]=1&x[1]=2'],
            [true,  'versio.com?ref=your%20hosting'],
            [true,  'versio.com?flag'],

            // Invalid — schemes not allowed
            [false, 'https://versio.com'],
            [false, 'http://versio.com'],
            [false, 'ftp://versio.com'],
            [false, 'https://versio.com/old-page'],

            // Invalid — unsupported URL parts
            [false, 'user@versio.com'],
            [false, 'versio.com:8080'],
            [false, 'versio.com#fragment'],

            // Invalid — malformed paths and queries
            [false, 'versio.com/path with space'],
            [false, 'versio.com/path%ZZ'],
            [false, 'versio.com?=value'],
            [false, 'versio.com?[]=value'],
            [false, 'versio.com?x=1;y=2'],
            [false, 'versio.com?x=%ZZ'],

            // Invalid — no valid TLD
            [false, 'mkyong'],
            [false, 'mkyong.123'],
            [false, 'mkyong.t.t.c'],

            // Invalid — bad hostname characters
            [false, 'mkyong,com'],
            [false, '.com'],
            [false, '-mkyong.com'],
            [false, 'mkyong-.com'],
            [false, 'sub.-mkyong.com'],
            [false, 'sub.mkyong-.com'],
            [false, '0-0o_.com'],

            // Invalid — hostname too long
            [false, 'a-1234567890-1234567890-1234567890-1234567890-1234567890-12345-z.eu.us'],
        ];
    }
}
