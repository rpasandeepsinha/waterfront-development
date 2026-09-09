<?php

declare(strict_types=1);

namespace Tests\Domain\DNS\Services\Serializers;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Waterfront\Domain\DNS\Services\Serializers\PowerDnsRecordContentSerializer;

#[CoversClass(PowerDnsRecordContentSerializer::class)]
class PowerDnsRecordContentSerializerTest extends TestCase
{
    #[Test]
    public function serializeInvalidType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The record type is not valid.');

        $serializer = new PowerDnsRecordContentSerializer();

        $data = ['content' => 'mx.google.com', 'priority' => 10];
        $serializer->serialize('mx', $data);
    }

    #[Test]
    public function serializeMxMissingPriority(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The priority is missing.');

        $serializer = new PowerDnsRecordContentSerializer();

        $data = ['content' => 'mx.google.com'];
        $serializer->serialize('MX', $data);
    }

    #[Test]
    public function serializeMxMissingContent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The content is missing.');

        $serializer = new PowerDnsRecordContentSerializer();

        $data = ['priority' => 10];
        $serializer->serialize('MX', $data);
    }

    #[Test]
    public function serializeMxNonScalarContent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The content is not a scalar value.');

        $serializer = new PowerDnsRecordContentSerializer();

        $data = ['content' => ['mx.google.com'], 'priority' => 10];
        $serializer->serialize('MX', $data);
    }

    #[Test]
    public function serializeMx(): void
    {
        $serializer = new PowerDnsRecordContentSerializer();

        $data = ['content' => 'mx.google.com', 'priority' => 10];
        $result = $serializer->serialize('MX', $data);
        self::assertSame('10 mx.google.com', $result);
    }

    #[Test]
    public function serializeSrvMissingPriority(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The priority is missing.');

        $serializer = new PowerDnsRecordContentSerializer();

        $data = ['weight' => 20, 'port' => 5000, 'content' => 'google.com'];
        $serializer->serialize('SRV', $data);
    }

    #[Test]
    public function serializeSrvMissingWeight(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The weight is missing.');

        $serializer = new PowerDnsRecordContentSerializer();

        $data = ['priority' => 10, 'port' => 5000, 'content' => 'google.com'];
        $serializer->serialize('SRV', $data);
    }

    #[Test]
    public function serializeSrvMissingPort(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The port is missing.');

        $serializer = new PowerDnsRecordContentSerializer();

        $data = ['priority' => 10, 'weight' => 20, 'content' => 'google.com'];
        $serializer->serialize('SRV', $data);
    }

    #[Test]
    public function serializeSrvMissingContent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The content is missing.');

        $serializer = new PowerDnsRecordContentSerializer();

        $data = ['priority' => 10, 'weight' => 20, 'port' => 5000];
        $serializer->serialize('SRV', $data);
    }

    #[Test]
    public function serializeSrv(): void
    {
        $serializer = new PowerDnsRecordContentSerializer();

        $data = ['priority' => 10, 'weight' => 20, 'port' => 5000, 'content' => 'google.com'];
        $result = $serializer->serialize('SRV', $data);
        self::assertSame('10 20 5000 google.com', $result);
    }

    public function serializeTxtWithQuotationMarks(): void
    {
        $serializer = new PowerDnsRecordContentSerializer();

        $data = ['content' => '"test"'];
        $result = $serializer->serialize('TXT', $data);
        self::assertSame('"test"', $result);

        $data = ['content' => 'test'];
        $result = $serializer->serialize('TXT', $data);
        self::assertSame('"test"', $result);
    }

    public function serializeSpfWithQuotationMarks(): void
    {
        $serializer = new PowerDnsRecordContentSerializer();

        $data = ['content' => '"test"'];
        $result = $serializer->serialize('SPF', $data);
        self::assertSame('"test"', $result);

        $data = ['content' => 'test'];
        $result = $serializer->serialize('SPF', $data);
        self::assertSame('"test"', $result);
    }

    #[Test]
    public function unserializeInvalidType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The record type is not valid.');

        $serializer = new PowerDnsRecordContentSerializer();

        $serializer->unserialize('mx', '10 mx.google.com');
    }

    #[Test]
    public function unserializeMxInvalidContent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The content of the MX record does not match the expected format.');

        $serializer = new PowerDnsRecordContentSerializer();

        $serializer->unserialize('MX', '10mx.google.com');
    }

    #[Test]
    public function unserializeMx(): void
    {
        $serializer = new PowerDnsRecordContentSerializer();

        $data = $serializer->unserialize('MX', '10 mx.google.com');
        self::assertSame([ 'priority' => '10', 'content' => 'mx.google.com'], $data);
    }

    #[Test]
    public function unserializeSrvInvalidContent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The content of the SRV record does not match the expected format.');

        $serializer = new PowerDnsRecordContentSerializer();

        $serializer->unserialize('SRV', '10 5060 sipserver.testing.test.');
    }

    #[Test]
    public function unserializeSrv(): void
    {
        $serializer = new PowerDnsRecordContentSerializer();

        $data = $serializer->unserialize(
            'SRV',
            '10 5 5060 sipserver.testing.test.'
        );
        self::assertSame(
            ['priority' => '10', 'weight' => '5', 'port' => '5060', 'content' => 'sipserver.testing.test.'],
            $data
        );
    }

    #[Test]
    public function unserializeTxt(): void
    {
        $serializer = new PowerDnsRecordContentSerializer();

        $data = $serializer->unserialize(
            'TXT',
            '"test"'
        );
        self::assertSame(
            ['content' => 'test'],
            $data
        );

        $data = $serializer->unserialize(
            'TXT',
            'test'
        );
        self::assertSame(
            ['content' => 'test'],
            $data
        );
    }

    #[Test]
    public function unserializeSpf(): void
    {
        $serializer = new PowerDnsRecordContentSerializer();

        $data = $serializer->unserialize(
            'SPF',
            '"test"'
        );
        self::assertSame(
            ['content' => 'test'],
            $data
        );

        $data = $serializer->unserialize(
            'SPF',
            'test'
        );
        self::assertSame(
            ['content' => 'test'],
            $data
        );
    }
}
