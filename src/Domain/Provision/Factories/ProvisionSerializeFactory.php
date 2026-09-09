<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Factories;

use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Waterfront\Domain\Provision\Serializer\Normalizer\ProvisionRequestNormalizer;
use Waterfront\Domain\Provision\Serializer\Normalizer\ProvisionThrowableNormalizer;
use Waterfront\Infra\Serialization\UuidNormalizer;

class ProvisionSerializeFactory
{
    public function get(): Serializer
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $metadataAwareNameConverter = new MetadataAwareNameConverter($classMetadataFactory);

        $propertyTypeExtractor = new PropertyInfoExtractor(
            typeExtractors: [
                new PhpDocExtractor(),
                new ReflectionExtractor(),
            ],
        );

        $objectNormalizer = new ObjectNormalizer(
            classMetadataFactory: $classMetadataFactory,
            nameConverter: $metadataAwareNameConverter,
            propertyTypeExtractor: $propertyTypeExtractor,
        );

        $normalizers = [
            new ProvisionRequestNormalizer($objectNormalizer),
            new UuidNormalizer(),
            new ProvisionThrowableNormalizer(),
            new BackedEnumNormalizer(),
            $objectNormalizer,
            new ArrayDenormalizer(),
        ];

        $serializer = new Serializer(
            normalizers: $normalizers,
            encoders: [
                new JsonEncoder(),
            ]
        );

        /**
         * We need the actual serializer because the ProvisionRequestNormalizer delegates back to the
         * ObjectNormalizer, which implements the SerializerAwareInterface. We use the implemented
         * SerializerAwareTrait on the object normalizer to set the current serializer instance.
         */
        $objectNormalizer->setSerializer($serializer);

        return $serializer;
    }
}
