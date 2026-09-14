<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Serializers;

use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class DomainSerializerFactory
{
    public static function getSerializer(): Serializer
    {
        $encoder = [new JsonEncoder()];
        $extractor = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $metadataAwareNameConverter = new MetadataAwareNameConverter($classMetadataFactory);
        $defaultDateContext = [DateTimeNormalizer::FORMAT_KEY => 'Y-m-d\\TH:i:s\\Z'];
        $normalizer = [
            new BackedEnumNormalizer(),
            new ArrayDenormalizer(),
            new DateTimeNormalizer($defaultDateContext),
            new ObjectNormalizer($classMetadataFactory, $metadataAwareNameConverter, null, $extractor),
        ];

        return new Serializer($normalizer, $encoder);
    }
}
