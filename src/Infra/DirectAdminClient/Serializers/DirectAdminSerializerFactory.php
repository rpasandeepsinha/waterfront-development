<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Serializers;

use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class DirectAdminSerializerFactory
{
    public static function getSerializer(): Serializer
    {
        $encoders = [
            new JsonEncoder(),
        ];

        $normalizers = [
            new BackedEnumNormalizer(),
            new ObjectNormalizer(
                nameConverter: new CamelCaseToSnakeCaseNameConverter(),
            ),
            new GetSetMethodNormalizer(),
        ];

        return new Serializer($normalizers, $encoders);
    }
}
