<?php

declare(strict_types=1);

namespace Tests\Infra\CloudStackClient\Mapper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Infra\CloudStackClient\Mapper\RoleMapper;

#[CoversClass(RoleMapper::class)]
class RoleMapperTest extends TestCase
{
    #[Test]
    public function invoke(): void
    {
        $role = (new RoleMapper())([
            'id' => 'foo',
            'name' => 'bar',
            'description' => 'baz',
            'type' => 'abc',
            'unknown' => 'pqr',
        ]);

        self::assertSame('foo', $role->id);
        self::assertSame('bar', $role->name);
        self::assertSame('baz', $role->description);
        self::assertSame('abc', $role->type);
    }
}
