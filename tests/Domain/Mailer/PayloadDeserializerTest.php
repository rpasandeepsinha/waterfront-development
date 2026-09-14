<?php

declare(strict_types=1);

namespace Tests\Domain\Mailer;

use Illuminate\Contracts\Encryption\Encrypter;
use JsonException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Waterfront\Domain\Mailer\PayloadDeserializer;

#[CoversClass(PayloadDeserializer::class)]
#[AllowMockObjectsWithoutExpectations]
class PayloadDeserializerTest extends TestCase
{
    private Encrypter&MockObject $encrypter;

    private PayloadDeserializer $deserializer;

    protected function setUp(): void
    {
        $this->encrypter = self::createMock(Encrypter::class);
        $this->deserializer = new PayloadDeserializer($this->encrypter);
    }

    #[Test]
    public function deserializesPayloadCorrectly(): void
    {
        $payload = '{"key1":"value1","key2":"encrypted:encryptedValue"}';
        $this->encrypter->method('decrypt')->willReturn('decryptedValue');

        $result = $this->deserializer->deserialize($payload);

        self::assertSame(['key1' => 'value1', 'key2' => 'decryptedValue'], $result);
    }

    #[Test]
    public function throwsExceptionOnInvalidJson(): void
    {
        $this->expectException(JsonException::class);

        $payload = '{"key1":"value1", "key2":}';
        $this->deserializer->deserialize($payload);
    }

    #[Test]
    public function handlesEmptyPayload(): void
    {
        $payload = '{}';

        $result = $this->deserializer->deserialize($payload);

        self::assertSame([], $result);
    }

    #[Test]
    public function handlesNonEncryptedValues(): void
    {
        $payload = '{"key1":"value1","key2":"value2"}';

        $result = $this->deserializer->deserialize($payload);

        self::assertSame(['key1' => 'value1', 'key2' => 'value2'], $result);
    }

    #[Test]
    public function handlesMultipleEncryptedValues(): void
    {
        $payload = '{"key1":"encrypted:encryptedValue1","key2":"encrypted:encryptedValue2"}';
        $this->encrypter
            ->expects($this->exactly(2))
            ->method('decrypt')
            ->willReturnMap([
                ['encryptedValue1', 'decryptedValue1'],
                ['encryptedValue2', 'decryptedValue2'],
            ]);

        $result = $this->deserializer->deserialize($payload);

        self::assertSame(['key1' => 'decryptedValue1', 'key2' => 'decryptedValue2'], $result);
    }
}
