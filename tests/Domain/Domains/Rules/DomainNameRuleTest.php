<?php

declare(strict_types=1);

namespace Tests\Domain\Domains\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Domains\Rules\DomainNameRule;
use Waterfront\Infra\Common\PublicSuffixList;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(DomainNameRule::class)]
class DomainNameRuleTest extends IntegrationTestCase
{
    #[DataProvider('passesProvider')]
    #[Test]
    public function passes(bool $expectedResult, string $value): void
    {
        $rules = self::resolve(PublicSuffixList::class);
        $rule = new DomainNameRule($rules, self::createStub(TranslatorInterface::class));
        $rule->validate('domain', $value, self::assertClosureIsCalled(! $expectedResult));
    }

    /**
     * Examples are taken from https://regexr.com/3au3g.
     *
     * @return mixed[]
     */
    public static function passesProvider(): array
    {
        return [
            // Valid domains
            [true, 'www.google.com'],
            [true, 'google.com'],
            [true, 'GOOGLE.COM'],
            [true, 'mkyong123.com'],
            [true, 'mkyong-info.com'],
            [true, 'sub.mkyong.com'],
            [true, 'sub.mkyong-info.com'],
            [true, 'mkyong.com.au'],
            [true, 'mkyong.co.uk'],
            [true, 'g.co'],
            [true, 'mkyong.t.t.co'],
            [true, 'a-1234567890-1234567890-1234567890-1234567890-1234567890-1234-z.eu.us'],

            // Invalid domains
            [false, 'mkyong.t.t.c'], // Tld must be between 2 and 6 long
            [false, 'mkyong,com'], // Comma is not allowed
            [false, 'mkyong'], // No Tld
            [false, 'mkyong.123'], // Tld cannot be only digits
            [false, '.com'], // Must start with [A-Za-z0-9]
            [false, 'mkyong.com/users'], // No Tld
            [false, '-mkyong.com'], // Cannot begin with a hyphen
            [false, 'mkyong-.com'], // Cannot end with a hyphen
            [false, 'sub.-mkyong.com'], // Cannot begin with a hyphen
            [false, 'sub.mkyong-.com'], // Cannot end with a hyphen
            [false, '0-0o_.com'], // underscore not allowed
            [false, 'a-1234567890-1234567890-1234567890-1234567890-1234567890-12345-z.eu.us'], // too long
        ];
    }
}
