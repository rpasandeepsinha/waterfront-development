<?php

declare(strict_types=1);

namespace Tests\Infra\CloudStackClient\Mapper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Infra\CloudStackClient\DTO\UserKeys;
use Waterfront\Infra\CloudStackClient\Mapper\UserKeysMapper;

#[CoversClass(UserKeysMapper::class)]
class UserKeysMapperTest extends TestCase
{
    #[Test]
    public function invoke(): void
    {
        $userKeys = (new UserKeysMapper())([
            'apikey' => 'foo',
            'secretkey' => 'bar',
            'unknown' => 'pqr',
        ]);

        self::assertInstanceOf(UserKeys::class, $userKeys);
        self::assertSame('foo', $userKeys->apiKey);
        self::assertSame('bar', $userKeys->secretKey);
    }

    #[Test]
    public function invokeUnset(): void
    {
        $userKeys = (new UserKeysMapper())([
            'unknown' => 'pqr',
        ]);

        self::assertNull($userKeys);
    }
}
