<?php

declare(strict_types=1);

namespace Tests\Support\Helpers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Support\Helpers\JsonHelper;

#[CoversClass(JsonHelper::class)]
class JsonHelperTest extends TestCase
{
    #[Test]
    public function decodeOfSimpleObject(): void
    {
        $json = '{"foo":1337}';
        $decoded = JsonHelper::decodeOrNull($json);
        self::assertSame(1337, $decoded?->foo);
    }

    #[Test]
    public function decodeOfInvalidJson(): void
    {
        $json = '{foo:1}';
        $decoded = JsonHelper::decodeOrNull($json);
        self::assertNull($decoded);
    }

    #[Test]
    public function decodeOfNull(): void
    {
        $json = null;
        $decoded = JsonHelper::decodeOrNull($json);
        self::assertNull($decoded);
    }
}
