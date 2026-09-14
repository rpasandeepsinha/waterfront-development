<?php

declare(strict_types=1);

namespace Waterfront\Domain\Orders\Serializers;

use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class OrderLineMetaDataSerializerFactory
{
    public function get(): Serializer
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());

        $discriminator = new ClassDiscriminatorFromClassMetadata($classMetadataFactory);

        $normalizers = [
            new BackedEnumNormalizer(),
            new ObjectNormalizer(
                classMetadataFactory: $classMetadataFactory,
                classDiscriminatorResolver: $discriminator,
            ),
        ];
        $encoders = ['json' => new JsonEncoder()];

        return new Serializer($normalizers, $encoders);
    }
}
