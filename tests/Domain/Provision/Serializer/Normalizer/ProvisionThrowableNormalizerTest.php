<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Serializer\Normalizer;

use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Throwable;
use Waterfront\Domain\Provision\Serializer\Normalizer\ProvisionThrowableNormalizer;

#[CoversClass(ProvisionThrowableNormalizer::class)]
class ProvisionThrowableNormalizerTest extends TestCase
{
    private ProvisionThrowableNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new ProvisionThrowableNormalizer();
    }

    #[Test]
    public function onlySupportsNormalizationOfThrowable(): void
    {
        $throwable = self::createStub(Throwable::class);
        $exception = new Exception('error');
        $notThrowable = new stdClass();

        self::assertTrue($this->normalizer->supportsNormalization($throwable));
        self::assertTrue($this->normalizer->supportsNormalization($exception));
        self::assertFalse($this->normalizer->supportsNormalization($notThrowable));
    }

    #[Test]
    public function normalizeThrowable(): void
    {
        $message = 'error';
        $code = 42;

        $exception = new Exception($message, $code);
        $normalized = $this->normalizer->normalize($exception);

        self::assertArrayHasKey('class', $normalized);
        self::assertArrayHasKey('message', $normalized);
        self::assertArrayHasKey('code', $normalized);
        self::assertArrayHasKey('file', $normalized);
        self::assertArrayHasKey('line', $normalized);
        self::assertSame(Exception::class, $normalized['class']);
        self::assertSame($message, $normalized['message']);
        self::assertSame($code, $normalized['code']);
        self::assertSame([], $normalized['previous']);
    }

    #[Test]
    public function normalizeThrowableWillIncludePreviousUntilDepth(): void
    {
        $normalizer = new ProvisionThrowableNormalizer(depth: 2);
        $thirdException = new Exception('third message', 50);
        $previousException = new Exception('previous message', 100, $thirdException);
        $exception = new Exception('current message', 200, $previousException);

        $normalized = $normalizer->normalize($exception);

        self::assertSame('current message', $normalized['message']);
        self::assertIsArray($normalized['previous']);
        self::assertSame('previous message', $normalized['previous']['message']);
        self::assertIsArray($normalized['previous']['previous']);
        self::assertSame('third message', $normalized['previous']['previous']['message']);
        self::assertSame([], $normalized['previous']['previous']['previous']);
    }

    #[Test]
    public function normalizeIncorrectDataType(): void
    {
        $notProvisionResult = new stdClass();

        self::expectException(NotNormalizableValueException::class);
        self::expectExceptionMessageIs('Received incorrect data type during provision throwable normalization.');

        $normalized = $this->normalizer->normalize($notProvisionResult);

        self::assertSame([], $normalized);
    }

    #[Test]
    public function getSupportedTypes(): void
    {
        $supportedTypes = $this->normalizer->getSupportedTypes(null);

        self::assertArrayHasKey(Throwable::class, $supportedTypes);
        self::assertTrue($supportedTypes[Throwable::class]);
    }

    #[Test]
    public function denormalizeWillRecreateThrowableChainUntilDepth(): void
    {
        $normalizer = new ProvisionThrowableNormalizer(depth: 1);

        $denormalized = $normalizer->denormalize([
            'message' => 'current message',
            'class' => Exception::class,
            'code' => 200,
            'file' => '/tmp/current.php',
            'line' => 12,
            'previous' => [
                'message' => 'previous message',
                'class' => Exception::class,
                'code' => 100,
                'file' => '/tmp/previous.php',
                'line' => 13,
                'previous' => [
                    'message' => 'too deep message',
                    'class' => Exception::class,
                    'code' => 50,
                    'file' => '/tmp/deep.php',
                    'line' => 14,
                    'previous' => [],
                ],
            ],
        ], Throwable::class);

        self::assertSame('current message [origin /tmp/current.php:12]', $denormalized->getMessage());
        self::assertInstanceOf(RuntimeException::class, $denormalized);
        self::assertNotNull($denormalized->getPrevious());
        self::assertSame('previous message [origin /tmp/previous.php:13]', $denormalized->getPrevious()->getMessage());
        self::assertNull($denormalized->getPrevious()->getPrevious());
    }

    #[Test]
    public function denormalizeIncorrectDataType(): void
    {
        self::expectException(NotNormalizableValueException::class);
        self::expectExceptionMessageIs('Received incorrect data type during provision throwable denormalization.');

        $this->normalizer->denormalize('invalid', Throwable::class);
    }
}
