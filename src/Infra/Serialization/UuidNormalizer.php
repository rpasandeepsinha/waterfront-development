<?php

declare(strict_types=1);

namespace Waterfront\Infra\Serialization;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class UuidNormalizer implements NormalizerInterface, DenormalizerInterface
{
    /**
     * @param array<mixed> $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        assert(is_string($data));
        return Uuid::fromString($data);
    }

    /** @param array<mixed> $context */
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return is_string($data) && is_a($type, UuidInterface::class, true) && Uuid::isValid($data);
    }

    /**
     * @param array<mixed> $context
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): string
    {
        assert($object instanceof UuidInterface);
        return $object->toString();
    }

    /** @param array<mixed> $context */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof UuidInterface;
    }

    /**
     * @return array<class-string, bool>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [UuidInterface::class => true];
    }
}
