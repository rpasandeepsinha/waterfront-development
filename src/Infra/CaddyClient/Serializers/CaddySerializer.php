<?php

declare(strict_types=1);

namespace Waterfront\Infra\CaddyClient\Serializers;

use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

// @phpstan-ignore sandwave.custom
class CaddySerializer extends Serializer
{
    public function __construct()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());

        $nameConverter = new MetadataAwareNameConverter(
            $classMetadataFactory,
            new CamelCaseToSnakeCaseNameConverter(),
        );

        $extractor = new PropertyInfoExtractor([], [
            new PhpDocExtractor(),
            new ReflectionExtractor(),
        ]);

        $defaultContext = [
            AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => true,
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
        ];

        $normalizers = [
            new ArrayDenormalizer(),
            new ObjectNormalizer(
                classMetadataFactory: $classMetadataFactory,
                nameConverter: $nameConverter,
                propertyTypeExtractor: $extractor,
                defaultContext: $defaultContext,
            ),
        ];

        parent::__construct($normalizers, [new JsonEncoder()]);
    }
}
