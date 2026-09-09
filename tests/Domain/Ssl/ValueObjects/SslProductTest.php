<?php

declare(strict_types=1);

namespace Tests\Domain\Ssl\ValueObjects;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Domain\Ssl\ValueObjects\SslProduct;

#[CoversClass(SslProduct::class)]
class SslProductTest extends TestCase
{
    #[Test]
    public function regularSsl(): void
    {
        $testItem = SslProduct::fromNative(5);
        self::assertTrue($testItem->isRegularSsl());
        self::assertFalse($testItem->isWildcardSsl());
        self::assertFalse($testItem->isExtendedSsl());
        self::assertSame(5, $testItem->toNative());
    }

    #[Test]
    public function wildcardSsl(): void
    {
        $testItem = SslProduct::fromNative('6');
        self::assertFalse($testItem->isRegularSsl());
        self::assertTrue($testItem->isWildcardSsl());
        self::assertFalse($testItem->isExtendedSsl());
        self::assertSame(6, $testItem->toNative());
    }

    #[Test]
    public function extendedSsl(): void
    {
        $testItem = SslProduct::fromNative(3);
        self::assertFalse($testItem->isRegularSsl());
        self::assertFalse($testItem->isWildcardSsl());
        self::assertTrue($testItem->isExtendedSsl());
        self::assertSame(3, $testItem->toNative());
    }
}
