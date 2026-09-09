<?php

declare(strict_types=1);

namespace Tests\Infra\CloudStackClient\Mapper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Infra\CloudStackClient\Mapper\UserMapper;

#[CoversClass(UserMapper::class)]
class UserMapperTest extends TestCase
{
    #[Test]
    public function invoke(): void
    {
        $user = (new UserMapper())([
            'id' => 'foo',
            'username' => 'bar',
            'domainid' => 'baz',
            'accountid' => 'abc',
            'unknown' => 'pqr',
        ]);

        self::assertSame('foo', $user->id);
        self::assertSame('bar', $user->username);
        self::assertSame('abc', $user->accountId);
        self::assertSame('baz', $user->domainId);
    }
}
