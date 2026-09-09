<?php

declare(strict_types=1);

namespace Tests\Infra\PuzzelClient\Serializers;

use Carbon\CarbonImmutable;
use DateTime;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Waterfront\Infra\PuzzelClient\Serializers\MicrosoftDateDenormalizer;

#[CoversClass(MicrosoftDateDenormalizer::class)]
class MicrosoftDateDenormalizerTest extends TestCase
{
    private MicrosoftDateDenormalizer $denormalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->denormalizer = new MicrosoftDateDenormalizer();
    }

    #[DataProvider('provideValidDates')]
    #[Test]
    public function denormalize(string $input, int $expectedTimestamp): void
    {
        $result = $this->denormalizer->denormalize($input, DateTime::class);

        self::assertSame($expectedTimestamp, $result->getTimestamp());
        self::assertEquals(CarbonImmutable::now()->timezoneName, $result->getTimezone()->getName());
    }

    /**
     * @return array<string, list<string|int>>
     */
    public static function provideValidDates(): array
    {
        return [
            'future date' => [
                '/Date(1767016200000-0000)/',
                1767016200,
            ],
            'recent date' => [
                '/Date(1672531200000)/',
                1672531200,
            ],
            'old date' => [
                '/Date(-2208988800000)/',
                -2208988800,
            ],
        ];
    }

    #[Test]
    public function denormalizeThrowsExceptionForInvalidFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Invalid Microsoft date format: invalid-date');

        $this->denormalizer->denormalize('invalid-date', DateTime::class);
    }

    #[Test]
    public function supportsDenormalization(): void
    {
        self::assertTrue($this->denormalizer->supportsDenormalization('/Date(123)/', DateTime::class));
        self::assertFalse($this->denormalizer->supportsDenormalization('2023-01-01', DateTime::class));
        self::assertFalse($this->denormalizer->supportsDenormalization('/Date(123)/', 'string'));
        self::assertFalse($this->denormalizer->supportsDenormalization(123, DateTime::class));
    }

    #[Test]
    public function getSupportedTypes(): void
    {
        $supportedTypes = $this->denormalizer->getSupportedTypes(null);

        self::assertArrayHasKey(DateTime::class, $supportedTypes);
        self::assertTrue($supportedTypes[DateTime::class]);
    }
}
