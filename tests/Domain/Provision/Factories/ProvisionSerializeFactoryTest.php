<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Factories;

use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Serializer;
use Tests\Domain\Provision\ProvisionGatewayTest;
use Tests\Domain\Provision\Stubs\ProvisionNestedChildResult;
use Tests\Domain\Provision\Stubs\ProvisionNestedParentResult;
use Tests\Domain\Provision\Stubs\ProvisionRequestWithNestedData;
use Tests\TestCase;
use Waterfront\Domain\Provision\Backup\Acronis\Enums\Language;
use Waterfront\Domain\Provision\Backup\Requests\CreateBackupRequest;
use Waterfront\Domain\Provision\Backup\Results\BackupSsoResult;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Factories\ProvisionSerializeFactory;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Sitebuilder\Requests\CreateSitebuilderRequest;
use Waterfront\Domain\Provision\Validation\ValidationResult;

#[CoversClass(ProvisionSerializeFactory::class)]
class ProvisionSerializeFactoryTest extends TestCase
{
    private Serializer $provisionSerializer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provisionSerializer = new ProvisionSerializeFactory()->get();
    }

    #[Test]
    public function provisionRequestNormalization(): void
    {
        $sitebuilder = new CreateSitebuilderRequest(
            domain: 'test-kees.nl',
            packages: [1, 2, 1337],
            firstname: 'test',
            lastname: 'kees',
            email: 'test@kees.nl',
            contractPeriod: 12,
            context: Uuid::uuid4(),
        );

        $sitebuilder->provider = ProvisionProvider::BASEKIT;

        $json = $this->provisionSerializer->serialize($sitebuilder, 'json');

        $object = $this->provisionSerializer->deserialize($json, CreateSitebuilderRequest::class, 'json');

        self::assertEquals($object, $sitebuilder);

        self::assertSame(ProvisionRequestName::CREATE_SITEBUILDER, $object->name);
        self::assertSame(ProvisionProvider::BASEKIT, $object->provider);
    }

    #[Test]
    public function requestDeserializeWithNestedTypedDataAndSerializedNameAttributes(): void
    {
        $json = '{"name":"create_backup","tagUuid":"e4a53b89-284c-47a0-a282-10a5bad56300","users":[{"user_email":"first@test.nl","language":"nl"}],"primary_user":{"user_email":"primary@test.nl","language":"en"}}';

        $object = $this->provisionSerializer->deserialize($json, ProvisionRequestWithNestedData::class, 'json');

        self::assertSame(ProvisionRequestName::CREATE_BACKUP, $object->name);
        self::assertCount(1, $object->users);
        self::assertNotNull($object->primaryUser);
        self::assertSame('first@test.nl', $object->users[0]->email);
        self::assertSame(Language::DUTCH, $object->users[0]->language);
        self::assertSame('primary@test.nl', $object->primaryUser->email);
        self::assertSame(Language::ENGLISH, $object->primaryUser->language);
    }

    #[Test]
    public function serializeResultUsesCanonicalPayload(): void
    {
        $request = new CreateBackupRequest(
            tagUuid: Uuid::uuid4(),
            email: 'test@test.nl',
            firstname: 'John',
            lastname: 'Doe',
        );
        $result = new BackupSsoResult($request, ProvisionStatus::SUCCESS, 'https://example.test/sso');

        $fullPayload = json_decode($this->provisionSerializer->serialize($result, 'json'), true);

        self::assertIsArray($fullPayload);
        self::assertSame('https://example.test/sso', $fullPayload['ssoUrl']);
        self::assertSame('success', $fullPayload['provisionStatus']);
        self::assertArrayHasKey('validationResult', $fullPayload);
        self::assertArrayHasKey('exception', $fullPayload);
        self::assertArrayHasKey('provisionData', $fullPayload);
    }

    #[Test]
    public function nestedProvisionResultsCanBeNormalizedAndDenormalized(): void
    {
        $parentRequest = new CreateBackupRequest(
            tagUuid: Uuid::uuid4(),
            email: 'parent@test.nl',
            firstname: 'Parent',
            lastname: 'Result',
        );

        $childRequest = new CreateBackupRequest(
            tagUuid: Uuid::uuid4(),
            email: 'child@test.nl',
            firstname: 'Child',
            lastname: 'Result',
        );

        $childResult = new ProvisionNestedChildResult(
            provisionData: $childRequest,
            provisionStatus: ProvisionStatus::SUCCESS,
            token: 'child-token',
        );

        $parentResult = new ProvisionNestedParentResult(
            provisionData: $parentRequest,
            provisionStatus: ProvisionStatus::SUCCESS,
            nestedResult: $childResult,
        );

        $json = $this->provisionSerializer->serialize($parentResult, 'json');

        self::assertStringContainsString('"nestedResult"', $json);
        self::assertStringContainsString('"child-token"', $json);

        $denormalized = $this->provisionSerializer->deserialize($json, ProvisionNestedParentResult::class, 'json');

        self::assertNotNull($denormalized->nestedResult);
        self::assertInstanceOf(CreateBackupRequest::class, $denormalized->nestedResult->provisionData);
        self::assertInstanceOf(CreateBackupRequest::class, $denormalized->provisionData);
        self::assertSame('child-token', $denormalized->nestedResult->token);
        self::assertSame('child@test.nl', $denormalized->nestedResult->provisionData->email);
        self::assertSame(ProvisionStatus::SUCCESS, $denormalized->nestedResult->provisionStatus);
        self::assertSame('parent@test.nl', $denormalized->provisionData->email);
        self::assertSame(ProvisionStatus::SUCCESS, $denormalized->provisionStatus);
    }

    #[Test]
    public function normalizeResultsWithExceptionsAndValidation(): void
    {
        $previousException = new Exception('previous error', 503);
        $exception = new RuntimeException('error', 500, $previousException);
        $validationResult = new ValidationResult(
            isValid: false,
            messages: [
                'tag' => ['tag is invalid.'],
            ],
        );

        $request = ProvisionGatewayTest::createMockRequest();

        $result = new BackupSsoResult(
            provisionData: $request,
            provisionStatus: ProvisionStatus::FAILED,
            ssoUrl: 'https://example.test/sso',
            exception: $exception,
            validationResult: $validationResult,
        );

        $normalizedResult = $this->provisionSerializer->normalize($result);

        self::assertIsArray($normalizedResult);

        self::assertSame('https://example.test/sso', $normalizedResult['ssoUrl']);
        self::assertSame(ProvisionStatus::FAILED->value, $normalizedResult['provisionStatus']);

        self::assertArrayHasKey('validationResult', $normalizedResult);
        self::assertSame(
            [
                'isValid' => false,
                'messages' => [
                    'tag' => ['tag is invalid.'],
                ],
            ],
            $normalizedResult['validationResult'],
        );

        self::assertArrayHasKey('exception', $normalizedResult);

        $receivedException = $normalizedResult['exception'];

        self::assertSame($exception->getMessage(), $receivedException['message']);
        self::assertSame($exception->getCode(), $receivedException['code']);
        self::assertSame($exception->getFile(), $receivedException['file']);
        self::assertSame($exception->getLine(), $receivedException['line']);

        self::assertArrayHasKey('previous', $receivedException);
        self::assertNotNull($exception->getPrevious());

        self::assertSame($exception->getPrevious()->getMessage(), $receivedException['previous']['message']);
        self::assertSame($exception->getPrevious()->getCode(), $receivedException['previous']['code']);
        self::assertSame($exception->getPrevious()->getFile(), $receivedException['previous']['file']);
        self::assertSame($exception->getPrevious()->getLine(), $receivedException['previous']['line']);

        self::assertArrayHasKey('provisionData', $normalizedResult);
        self::assertSame($request->tag->toString(), $normalizedResult['provisionData']['tag']);
        self::assertSame($request->type->value, $normalizedResult['provisionData']['type']);
        self::assertSame($request->provider?->value, $normalizedResult['provisionData']['provider']);
        self::assertSame($request->name->value, $normalizedResult['provisionData']['name']);
        self::assertSame($request->requiresValidation, $normalizedResult['provisionData']['requiresValidation']);
    }

    #[Test]
    public function serializeAndDeserializeRoundTripViaInterface(): void
    {
        $sitebuilder = new CreateSitebuilderRequest(
            domain: 'roundtrip.nl',
            packages: [1, 2, 3],
            firstname: 'Round',
            lastname: 'Trip',
            email: 'round@trip.nl',
            contractPeriod: 24,
            context: Uuid::uuid4(),
        );

        $sitebuilder->provider = ProvisionProvider::BASEKIT;

        $json = $this->provisionSerializer->serialize($sitebuilder, 'json');
        $deserialized = $this->provisionSerializer->deserialize($json, ProvisionRequestInterface::class, 'json');

        self::assertInstanceOf(CreateSitebuilderRequest::class, $deserialized);
        self::assertSame(ProvisionRequestName::CREATE_SITEBUILDER, $deserialized->name);
        self::assertSame('roundtrip.nl', $deserialized->domain);
        self::assertSame([1, 2, 3], $deserialized->packages);
        self::assertSame('Round', $deserialized->firstname);
        self::assertSame('Trip', $deserialized->lastname);
        self::assertSame('round@trip.nl', $deserialized->email);
        self::assertSame(24, $deserialized->contractPeriod);
        self::assertSame($sitebuilder->context->toString(), $deserialized->context->toString());
    }

    #[Test]
    public function denormalizeFromDatabaseRepresentation(): void
    {
        $sitebuilder = new CreateSitebuilderRequest(
            domain: 'database-test.nl',
            packages: [5, 10],
            firstname: 'DB',
            lastname: 'Test',
            email: 'db@test.nl',
            contractPeriod: 12,
            context: Uuid::uuid4(),
        );

        $strippedJson = $this->provisionSerializer->serialize(
            $sitebuilder,
            'json',
            [
                AbstractNormalizer::IGNORED_ATTRIBUTES => [
                    'tagUuid',
                    'tag',
                    'context',
                    'name',
                    'type',
                    'requiresValidation',
                    'provider',
                    'requestId',
                    'retryOf',
                    'retryRequester',
                    'retry',
                ],
            ],
        );

        /** @var array<string, mixed> $storedData */
        $storedData = json_decode($strippedJson, true);
        $storedData['name'] = ProvisionRequestName::CREATE_SITEBUILDER->value;
        $storedData['context'] = $sitebuilder->context->toString();

        $deserialized = $this->provisionSerializer->denormalize(
            $storedData,
            ProvisionRequestInterface::class,
        );

        self::assertInstanceOf(CreateSitebuilderRequest::class, $deserialized);
        self::assertSame(ProvisionRequestName::CREATE_SITEBUILDER, $deserialized->name);
        self::assertSame('database-test.nl', $deserialized->domain);
        self::assertSame([5, 10], $deserialized->packages);
        self::assertSame('DB', $deserialized->firstname);
        self::assertSame('db@test.nl', $deserialized->email);
        self::assertSame($sitebuilder->context->toString(), $deserialized->context->toString());
    }
}
