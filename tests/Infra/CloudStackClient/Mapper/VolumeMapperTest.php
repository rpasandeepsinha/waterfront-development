<?php

declare(strict_types=1);

namespace Tests\Infra\CloudStackClient\Mapper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Infra\CloudStackClient\Mapper\VolumeMapper;

#[CoversClass(VolumeMapper::class)]
class VolumeMapperTest extends TestCase
{
    #[Test]
    public function invoke(): void
    {
        $volume = (new VolumeMapper())([
            'id' => 'foo',
            'name' => 'bar',
            'domainid' => 'baz',
            'account' => 'abc',
            'type' => 'def',
            'state' => 'ghi',
            'virtualmachineid' => 'jkl',
            'diskofferingid' => 'mno',
            'unknown' => 'pqr',
        ]);

        self::assertSame('foo', $volume->id);
        self::assertSame('bar', $volume->name);
        self::assertSame('baz', $volume->domainId);
        self::assertSame('abc', $volume->account);
        self::assertSame('def', $volume->type);
        self::assertSame('ghi', $volume->state);
        self::assertSame('jkl', $volume->virtualMachineId);
        self::assertSame('mno', $volume->diskOfferingId);
    }

    #[Test]
    public function invokeUnset(): void
    {
        $volume = (new VolumeMapper())([
            'id' => 'foo',
            'name' => 'bar',
            'domainid' => 'baz',
            'account' => 'abc',
            'type' => 'def',
            'state' => 'ghi',
            'unknown' => 'pqr',
        ]);

        self::assertNull($volume->virtualMachineId);
        self::assertNull($volume->diskOfferingId);
    }
}
