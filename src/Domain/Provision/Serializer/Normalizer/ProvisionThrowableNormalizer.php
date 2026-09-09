<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Serializer\Normalizer;

use RuntimeException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Throwable;

class ProvisionThrowableNormalizer implements NormalizerInterface, DenormalizerInterface
{
    public function __construct(public int $depth = 2)
    {
    }

    /**
     * @param array<mixed> $context
     *
     * @throws NotNormalizableValueException|ExceptionInterface
     *
     * @return array<string, mixed>
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        if (! $data instanceof Throwable) {
            throw NotNormalizableValueException::createForUnexpectedDataType(
                message: 'Received incorrect data type during provision throwable normalization.',
                data: $data,
                expectedTypes: [Throwable::class],
            );
        }

        return $this->normalizeThrowable(throwable: $data);
    }

    /**
     * @param array<mixed> $context
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Throwable;
    }

    /**
     * @param array<mixed> $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        if (! is_array($data)) {
            throw NotNormalizableValueException::createForUnexpectedDataType(
                message: 'Received incorrect data type during provision throwable denormalization.',
                data: $data,
                expectedTypes: ['array'],
            );
        }

        return $this->denormalizeThrowable($data);
    }

    /**
     * @param array<mixed> $context
     */
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return is_a($type, Throwable::class, true);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Throwable::class => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeThrowable(
        Throwable $throwable,
        int $currentDepth = 0
    ): array {
        $normalizedThrowable = [
            'message' => $throwable->getMessage(),
            'class' => $throwable::class,
            'code' => $throwable->getCode(),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
            'previous' => [],
        ];

        if ($currentDepth >= $this->depth) {
            return $normalizedThrowable;
        }

        $previousThrowable = $throwable->getPrevious();

        if ($previousThrowable === null) {
            return $normalizedThrowable;
        }

        $normalizedThrowable['previous'] = $this->normalizeThrowable(
            throwable: $previousThrowable,
            currentDepth: $currentDepth + 1,
        );

        return $normalizedThrowable;
    }

    /**
     * @param array<mixed, mixed> $data
     */
    private function denormalizeThrowable(array $data, int $currentDepth = 0): Throwable
    {
        $previous = null;
        $previousData = $data['previous'] ?? null;

        if ($currentDepth < $this->depth && is_array($previousData) && $previousData !== []) {
            $previous = $this->denormalizeThrowable($previousData, $currentDepth + 1);
        }

        $message = array_key_exists('message', $data) && is_string($data['message'])
            ? $data['message']
            : 'Unknown exception';

        $code = array_key_exists('code', $data) && is_int($data['code'])
            ? $data['code']
            : 0;

        $originFile = array_key_exists('file', $data) && is_string($data['file'])
            ? $data['file']
            : 'unknown';

        $originLine = array_key_exists('line', $data) && is_int($data['line'])
            ? $data['line']
            : 0;

        $messageWithOrigin = sprintf('%s [origin %s:%d]', $message, $originFile, $originLine);

        return new RuntimeException($messageWithOrigin, $code, $previous);
    }
}
