<?php

declare(strict_types=1);

namespace Tests\Domain\Domains\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Waterfront\Domain\Domains\Rules\RedirectDestinationUrlRule;
use Waterfront\Infra\Common\PublicSuffixList;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(RedirectDestinationUrlRule::class)]
class RedirectDestinationUrlRuleTest extends TestCase
{
    #[DataProvider('passesProvider')]
    #[Test]
    public function passes(bool $expectedResult, string $value): void
    {
        $rules = $this->app->make(PublicSuffixList::class);
        $rule = new RedirectDestinationUrlRule($rules, self::createStub(TranslatorInterface::class));
        $rule->validate('domain', $value, self::assertClosureIsCalled(! $expectedResult));
    }

    /**
     * @return array<int, array{bool, string}>
     */
    public static function passesProvider(): array
    {
        return [
            // Bare domains
            [true, 'versio.com'],
            [true, 'VERSIO.COM'],
            [true, 'sub.versio.com'],
            [true, 'shop.sub.versio.com'],
            [true, 'versio.co.uk'],
            [true, 'versio.com.au'],
            [true, 'mkyong-info.com'],

            // With http/https scheme
            [true, 'http://versio.com'],
            [true, 'https://versio.com'],
            [true, 'https://sub.versio.com'],
            [true, 'https://versio.com/'],

            // With path (no scheme)
            [true, 'versio.com/old-page'],
            [true, 'versio.com/old-page/'],

            // With path and scheme
            [true, 'https://versio.com/landing'],
            [true, 'https://versio.com/landing/page'],

            // With query parameters (no scheme)
            [true, 'versio.com?ref=yh'],

            // With path and query parameters
            [true, 'versio.com/promo?ref=yh'],
            [true, 'https://versio.com/landing?utm_source=newsletter&utm_medium=email'],

            // Invalid — no valid TLD
            [false, 'mkyong'],
            [false, 'mkyong.123'],
            [false, 'http://mkyong'],
            [false, 'https://mkyong.123'],

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
