<?php

declare(strict_types=1);

namespace Tests\Domain\Mailer;

use Illuminate\Contracts\Encryption\Encrypter;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SensitiveParameter;
use Waterfront\Domain\Mailer\MailTemplateInterface;
use Waterfront\Domain\Mailer\PayloadSerializer;

#[CoversClass(PayloadSerializer::class)]
#[AllowMockObjectsWithoutExpectations]
class PayloadSerializerTest extends TestCase
{
    private Encrypter&MockObject $encrypter;

    private PayloadSerializer $serializer;

    protected function setUp(): void
    {
        $this->encrypter = self::createMock(Encrypter::class);
        $this->serializer = new PayloadSerializer($this->encrypter);
    }

    #[Test]
    public function serializesPayloadCorrectly(): void
    {
        $template = new readonly class('value1', 'value2') implements MailTemplateInterface {
            public function __construct(
                public string $key1,
                #[SensitiveParameter]
                public string $key2,
            ) {
            }

            public static function getTemplateSlug(): string
            {
                return 'template';
            }
        };

        $this->encrypter->method('encrypt')->willReturn('encryptedValue2');

        $result = $this->serializer->serialize($template);

        self::assertSame('{"key1":"value1","key2":"encrypted:encryptedValue2"}', $result);
    }

    #[Test]
    public function handlesEmptyTemplate(): void
    {
        $template = new class() implements MailTemplateInterface {
            public static function getTemplateSlug(): string
            {
                return 'template';
            }
        };

        $result = $this->serializer->serialize($template);

        self::assertNull($result);
    }

    #[Test]
    public function handlesNonSensitiveValues(): void
    {
        $template = new readonly class('value1', 'value2') implements MailTemplateInterface {
            public function __construct(
                public string $key1,
                public string $key2,
            ) {
            }

            public static function getTemplateSlug(): string
            {
                return 'template';
            }
        };

        $result = $this->serializer->serialize($template);

        self::assertSame('{"key1":"value1","key2":"value2"}', $result);
    }

    #[Test]
    public function handlesMultipleSensitiveValues(): void
    {
        $template = new readonly class('value1', 'value2') implements MailTemplateInterface {
            public function __construct(
                #[SensitiveParameter]
                public string $key1,
                #[SensitiveParameter]
                public string $key2,
            ) {
            }

            public static function getTemplateSlug(): string
            {
                return 'template';
            }
        };

        $this->encrypter
            ->expects($this->exactly(2))
            ->method('encrypt')
            ->willReturnMap([
                ['value1', 'encryptedValue1'],
                ['value2', 'encryptedValue2'],
            ]);

        $result = $this->serializer->serialize($template);

        self::assertSame('{"key1":"encrypted:encryptedValue1","key2":"encrypted:encryptedValue2"}', $result);
    }
}
