<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Serializer\Normalizer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use stdClass;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Tests\Domain\Provision\Stubs\ProvisionRequestWithMaskedPropsProvision;
use Waterfront\Domain\Provision\DomainNames\Coupling\Requests\DomainNameCoupleRequest;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Requests\ProvisionRequest;
use Waterfront\Domain\Provision\Serializer\Normalizer\ProvisionRequestNormalizer;
use Waterfront\Infra\Serialization\UuidNormalizer;

#[CoversClass(ProvisionRequestNormalizer::class)]
class ProvisionRequestNormalizerTest extends TestCase
{
    private ProvisionRequestNormalizer $normalizer;

    protected function setUp(): void
    {
        $objectNormalizer = new ObjectNormalizer();
        $normalizer = new ProvisionRequestNormalizer($objectNormalizer);

        $serializer = new Serializer(
            normalizers: [
                $normalizer,
                new BackedEnumNormalizer(),
                new UuidNormalizer(),
            ],
            encoders: [
                new JsonEncoder(),
            ],
        );

        $objectNormalizer->setSerializer($serializer);

        $this->normalizer = $normalizer;
    }

    #[Test]
    public function onlySupportsNormalizationOfProvisionRequestInterface(): void
    {
        $provisionRequestInterface = self::createStub(ProvisionRequestInterface::class);
        $provisionRequest = self::createStub(ProvisionRequest::class);
        $notRequest = new stdClass();

        self::assertTrue($this->normalizer->supportsNormalization($provisionRequestInterface));
        self::assertTrue($this->normalizer->supportsNormalization($provisionRequest));
        self::assertFalse($this->normalizer->supportsNormalization($notRequest));
    }

    #[Test]
    public function normalize(): void
    {
        $user = 'testuser';
        $password = 'testpassword';
        $secretInt = 1337;
        $secretFloat = 13.37;
        $secretArray = ['key' => 'value'];

        $request = new ProvisionRequestWithMaskedPropsProvision(
            username: $user,
            password: $password,
            secretInt: $secretInt,
            secretFloat: $secretFloat,
            secretArray: $secretArray,
            context: Uuid::uuid4(),
        );

        $decoded = $this->normalizer->normalize($request);

        self::assertIsArray($decoded);
        self::assertArrayHasKey('username', $decoded);
        self::assertArrayHasKey('password', $decoded);
        self::assertSame($user, $decoded['username']);
        self::assertSame('****', $decoded['password']);
        self::assertSame(0.0, $decoded['secretFloat']);
        self::assertSame(0, $decoded['secretInt']);
        self::assertNull($decoded['secretArray']);
    }

    #[Test]
    public function normalizeIncorrectDataType(): void
    {
        $notProvisionRequest = new stdClass();

        self::expectException(NotNormalizableValueException::class);
        self::expectExceptionMessageIs('Received incorrect data type during provision request normalization.');

        $normalized = $this->normalizer->normalize($notProvisionRequest);

        self::assertSame([], $normalized);
    }

    #[Test]
    public function getSupportedTypes(): void
    {
        $supportedTypes = $this->normalizer->getSupportedTypes(null);

        self::assertArrayHasKey(ProvisionRequestInterface::class, $supportedTypes);
        self::assertTrue($supportedTypes[ProvisionRequestInterface::class]);
        self::assertArrayHasKey(ProvisionRequest::class, $supportedTypes);
        self::assertTrue($supportedTypes[ProvisionRequest::class]);
    }

    #[Test]
    public function supportsDenormalizationOfProvisionRequestInterface(): void
    {
        self::assertTrue($this->normalizer->supportsDenormalization([], ProvisionRequestInterface::class));
        self::assertTrue($this->normalizer->supportsDenormalization([], ProvisionRequest::class));
        self::assertFalse($this->normalizer->supportsDenormalization([], DomainNameCoupleRequest::class));
        self::assertFalse($this->normalizer->supportsDenormalization([], stdClass::class));
    }

    #[Test]
    public function denormalizeResolvesCorrectConcreteClass(): void
    {
        $context = Uuid::uuid4();
        $requestUuid = Uuid::uuid4();

        $data = [
            'name' => ProvisionRequestName::COUPLE_DOMAIN->value,
            'domain' => 'example.com',
            'requestUuid' => $requestUuid->toString(),
            'context' => $context->toString(),
        ];

        $result = $this->normalizer->denormalize($data, ProvisionRequestInterface::class);

        self::assertInstanceOf(DomainNameCoupleRequest::class, $result);
        self::assertSame('example.com', $result->domain);
        self::assertSame($requestUuid->toString(), $result->requestUuid->toString());
        self::assertSame($context->toString(), $result->context->toString());
        self::assertSame(ProvisionRequestName::COUPLE_DOMAIN, $result->name);
    }

    #[Test]
    public function denormalizeThrowsOnNonArrayData(): void
    {
        self::expectException(NotNormalizableValueException::class);
        self::expectExceptionMessageIs(
            'Received invalid data during provision request denormalization, expected array with string "name" key.',
        );

        $this->normalizer->denormalize('not-an-array', ProvisionRequestInterface::class);
    }

    #[Test]
    public function denormalizeThrowsOnMissingNameKey(): void
    {
        self::expectException(NotNormalizableValueException::class);
        self::expectExceptionMessageIs(
            'Received invalid data during provision request denormalization, expected array with string "name" key.',
        );

        $this->normalizer->denormalize(['domain' => 'example.com'], ProvisionRequestInterface::class);
    }

    #[Test]
    public function denormalizeThrowsOnNonStringNameValue(): void
    {
        self::expectException(NotNormalizableValueException::class);
        self::expectExceptionMessageIs(
            'Received invalid data during provision request denormalization, expected array with string "name" key.',
        );

        $this->normalizer->denormalize(['name' => 123], ProvisionRequestInterface::class);
    }

    #[Test]
    public function denormalizeThrowsOnUnknownRequestName(): void
    {
        self::expectException(NotNormalizableValueException::class);
        self::expectExceptionMessageIs('Unknown provision request name "nonexistent_request" during denormalization.');

        $this->normalizer->denormalize(
            ['name' => 'nonexistent_request'],
            ProvisionRequestInterface::class,
        );
    }
}
