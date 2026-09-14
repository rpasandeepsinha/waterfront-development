<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\Services;

use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\CustomNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Waterfront\Infra\HubspotClient\Serializer\PropertiesNormalizer;
use Waterfront\Infra\Serialization\UuidNormalizer;

class Microsoft365SerializerFactory
{
    public function get(): Serializer
    {
        $encoder = [new JsonEncoder()];
        $extractor = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $metadataAwareNameConverter = new MetadataAwareNameConverter(
            $classMetadataFactory,
            new CamelCaseToSnakeCaseNameConverter(),
        );

        $normalizer = [
            new BackedEnumNormalizer(),
            new CustomNormalizer(),
            new DateTimeNormalizer(),
            new ObjectNormalizer($classMetadataFactory, $metadataAwareNameConverter, null, $extractor),
            new PropertiesNormalizer(),
            new UuidNormalizer(),
        ];

        return new Serializer($normalizer, $encoder);
    }
}
