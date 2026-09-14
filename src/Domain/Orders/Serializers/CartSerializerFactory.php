<?php

declare(strict_types=1);

namespace Waterfront\Domain\Orders\Serializers;

use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\CustomNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Waterfront\Infra\Serialization\UuidNormalizer;

class CartSerializerFactory
{
    public function get(): Serializer
    {
        $encoders = [new JsonEncoder()];

        $extractor = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);

        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());

        $metadataAwareNameConverter = new MetadataAwareNameConverter(
            $classMetadataFactory,
            new CamelCaseToSnakeCaseNameConverter(),
        );
        $discriminator = new ClassDiscriminatorFromClassMetadata($classMetadataFactory);

        $normalizer = [
            new UuidNormalizer(),
            new ArrayDenormalizer(),
            new BackedEnumNormalizer(),
            new CustomNormalizer(),
            new ObjectNormalizer(
                classMetadataFactory: $classMetadataFactory,
                nameConverter: $metadataAwareNameConverter,
                propertyTypeExtractor: $extractor,
                classDiscriminatorResolver: $discriminator,
            ),
        ];

        return new Serializer($normalizer, $encoders);
    }

    /**
     * Same as get() but without camelcase name converter.
     */
    public function getPresenter(): Serializer
    {
        $encoders = [new JsonEncoder()];

        $extractor = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);

        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());

        $metadataAwareNameConverter = new MetadataAwareNameConverter($classMetadataFactory);
        $discriminator = new ClassDiscriminatorFromClassMetadata($classMetadataFactory);

        $normalizer = [
            new UuidNormalizer(),
            new ArrayDenormalizer(),
            new BackedEnumNormalizer(),
            new ObjectNormalizer(
                classMetadataFactory: $classMetadataFactory,
                nameConverter: $metadataAwareNameConverter,
                propertyTypeExtractor: $extractor,
                classDiscriminatorResolver: $discriminator,
            ),
        ];

        return new Serializer($normalizer, $encoders);
    }
}
