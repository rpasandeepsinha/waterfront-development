<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Serializer\Normalizer;

use ArrayObject;
use ReflectionNamedType;
use ReflectionObject;
use ReflectionType;
use SensitiveParameter;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Requests\ProvisionRequest;
use Webmozart\Assert\Assert;

class ProvisionRequestNormalizer implements NormalizerInterface, DenormalizerInterface
{
    public function __construct(
        private readonly NormalizerInterface&DenormalizerInterface $normalizer,
    ) {
    }

    /**
     * @param array<mixed> $context
     *
     * @throws ExceptionInterface
     *
     * @return array<mixed>|string|int|float|bool|ArrayObject<int, mixed>|null
     */
    public function normalize(
        mixed $data,
        ?string $format = null,
        array $context = [],
    ): array|string|int|float|bool|ArrayObject|null {
        if (! $data instanceof ProvisionRequestInterface) {
            throw NotNormalizableValueException::createForUnexpectedDataType(
                message: 'Received incorrect data type during provision request normalization.',
                data: $data,
                expectedTypes: [ProvisionRequestInterface::class],
            );
        }

        $objectNormalizedData = $this->normalizer->normalize($data, $format, $context);

        if (! is_array($objectNormalizedData)) {
            throw NotNormalizableValueException::createForUnexpectedDataType(
                message: 'Received incorrect normalization through given NormalizerInterface, expected array.',
                data: $data,
                expectedTypes: [ProvisionRequestInterface::class],
            );
        }

        $reflectionObject = new ReflectionObject($data);

        /**
         * The SensitiveParameter attribute has its flag set as TARGET_PARAMETER
         * so we need to get the constructor parameters to be able to retrieve
         * the SensitiveParameter attribute from the properties of the class.
         */
        $constructor = $reflectionObject->getConstructor();
        Assert::notNull($constructor, 'ProvisionRequest class should have a constructor.');

        foreach ($constructor->getParameters() as $parameter) {
            $propertyName = $parameter->getName();
            $attributes = $parameter->getAttributes(SensitiveParameter::class);

            if ($attributes !== [] && array_key_exists($propertyName, $objectNormalizedData)) {
                $objectNormalizedData[$propertyName] = $this->getMaskRepresentation($parameter->getType());
            }
        }

        return $objectNormalizedData;
    }

    /**
     * @param array<mixed> $context
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof ProvisionRequestInterface;
    }

    /**
     * @param array<mixed> $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        if (! is_array($data) || ! array_key_exists('name', $data) || ! is_string($data['name'])) {
            throw NotNormalizableValueException::createForUnexpectedDataType(
                message: 'Received invalid data during provision request denormalization, expected array with string "name" key.',
                data: $data,
                expectedTypes: ['array with string name key'],
            );
        }

        $requestName = ProvisionRequestName::tryFrom($data['name']);

        if ($requestName === null) {
            throw NotNormalizableValueException::createForUnexpectedDataType(
                message: sprintf('Unknown provision request name "%s" during denormalization.', $data['name']),
                data: $data,
                expectedTypes: [ProvisionRequestName::class],
            );
        }

        unset($data['name']);

        /** @var ProvisionRequestInterface */
        return $this->normalizer->denormalize($data, $requestName->toRequestClass(), $format, $context);
    }

    /**
     * @param array<mixed> $context
     */
    public function supportsDenormalization(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = [],
    ): bool {
        return $type === ProvisionRequestInterface::class || $type === ProvisionRequest::class;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            ProvisionRequestInterface::class => true,
            ProvisionRequest::class => true,
        ];
    }

    private function getMaskRepresentation(?ReflectionType $reflectionType): mixed
    {
        if (! $reflectionType instanceof ReflectionNamedType) {
            return null;
        }

        return match ($reflectionType->getName()) {
            'string' => '****',
            'int' => 0,
            'float' => 0.0,
            default => null,
        };
    }
}
