<?php

declare(strict_types=1);

namespace Waterfront\Infra\HubspotClient\Serializer;

use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class PropertiesNormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    /**
     * @param mixed[] $context
     *
     * @throws ExceptionInterface
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        assert(is_array($data));
        $properties = $data['properties'];

        if (array_key_exists('id', $data)) {
            $properties['id'] = $data['id'];
        }

        return $this->denormalizer->denormalize($properties, $type, $format, $context);
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
        return is_array($data) && array_key_exists('properties', $data);
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['object' => true, '*' => false];
    }
}
