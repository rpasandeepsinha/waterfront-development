<?php

declare(strict_types=1);

namespace Waterfront\Infra\Authentication;

use SandwaveIo\LighthouseAuthBase\Service\IdentitySerializerFactory;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Temporary hack to prevent instantiating the serializer on application boot.
 * When running phpstan, the larastan plugin boots the application. Because the
 * `phpdocumentor/type-resolver` package behaves differently in phpstan context vs
 * normal execution, loading an older version of the phpstan docparser, a fatal error
 * is thrown. Upgrading rector, phpstan* and larastan solves the issue. These packages
 * have a dependency on Laravel 11.
 */
class IdentitySerializerProxy implements DenormalizerInterface
{
    /**
     * @param mixed[] $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        return IdentitySerializerFactory::create()->denormalize($data, $type, $format, $context);
    }

    /**
     * @param mixed[] $context
     */
    public function supportsDenormalization(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = [],
    ): bool {
        return IdentitySerializerFactory::create()->supportsDenormalization($data, $type, $format, $context);
    }

    /**
     * @return array<string, bool|null>
     */
    public function getSupportedTypes(?string $format): array
    {
        return IdentitySerializerFactory::create()->getSupportedTypes($format);
    }
}
