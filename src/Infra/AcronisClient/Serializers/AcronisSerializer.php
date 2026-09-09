<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\Serializers;

use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Webmozart\Assert\Assert;

// @phpstan-ignore sandwave.custom
class AcronisSerializer extends Serializer
{
    public static function get(): self
    {
        $nameConverter = new CamelCaseToSnakeCaseNameConverter();

        $extractor = new PropertyInfoExtractor([], [
            new PhpDocExtractor(),
            new ReflectionExtractor(),
        ]);

        $defaultContext = [
            AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => true,
            /**
             * Acronis is picky about what we send to their API. They have multiple fields
             * that are required to be a certain type but will also be generated when
             * these fields are not send in the body of the API requests. Such as:
             *
             * default_idp_id is of type string for a tenant creation. But it can be left
             * out of the request, however sending it as `null` is not possible. So we
             * make sure to skip uninitialized values in our serializer here to comply.
             *
             * We do want to keep our null values because there are plenty of occurrences
             * where we actually want to set or update a value to be null. For instance
             * during a PUT update of a tenant or user to remove a certain value.
             */
            AbstractObjectNormalizer::SKIP_UNINITIALIZED_VALUES => true,
            AbstractObjectNormalizer::SKIP_NULL_VALUES => false,
        ];

        $dateTimeContext = [
            DateTimeNormalizer::FORMAT_KEY => 'Y-m-d\TH:i:s',
        ];

        $normalizers = [
            new DateTimeNormalizer($dateTimeContext),
            new ArrayDenormalizer(),
            new BackedEnumNormalizer(),
            new ObjectNormalizer(
                classMetadataFactory: new ClassMetadataFactory(new AttributeLoader()),
                nameConverter: $nameConverter,
                propertyTypeExtractor: $extractor,
                defaultContext: $defaultContext,
            ),
        ];

        return new self($normalizers, [new JsonEncoder()]);
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function normalizeToArray(object $payload, array $context = []): array
    {
        $normalized = self::normalize($payload, 'array', $context);

        Assert::isArray($normalized);

        return $normalized;
    }
}
