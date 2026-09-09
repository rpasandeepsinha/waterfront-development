<?php

declare(strict_types=1);

namespace Tests\Infra\CloudStackClient\Mapper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Infra\CloudStackClient\Mapper\AccountMapper;

#[CoversClass(AccountMapper::class)]
class AccountMapperTest extends TestCase
{
    #[Test]
    public function invoke(): void
    {
        $account = (new AccountMapper())([
            'id' => 'foo',
            'name' => 'bar',
            'domainid' => 'baz',
            'account' => 'abc',
            'unknown' => 'pqr',
        ]);

        self::assertSame('foo', $account->id);
        self::assertSame('bar', $account->name);
        self::assertSame('baz', $account->domainId);
    }
}
