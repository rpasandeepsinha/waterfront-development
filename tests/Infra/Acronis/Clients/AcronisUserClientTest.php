<?php

declare(strict_types=1);

namespace Tests\Infra\Acronis\Clients;

use Carbon\CarbonImmutable;
use Illuminate\Cache\Repository;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Saloon\Exceptions\SaloonException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Infra\AcronisClient\Clients\AcronisUserClient;
use Waterfront\Infra\AcronisClient\Config\ConnectorConfig;
use Waterfront\Infra\AcronisClient\Connectors\AcronisConnector;
use Waterfront\Infra\AcronisClient\DTO\Requests\Users\Contact;
use Waterfront\Infra\AcronisClient\DTO\Requests\Users\User;
use Waterfront\Infra\AcronisClient\DTO\Responses\Users\PolicyItem;
use Waterfront\Infra\AcronisClient\DTO\Responses\Users\UserAccessPolicies;
use Waterfront\Infra\AcronisClient\Enums\RoleId;
use Waterfront\Infra\AcronisClient\Enums\TrusteeType;
use Waterfront\Infra\AcronisClient\Exceptions\AcronisSerializerException;
use Waterfront\Infra\AcronisClient\Requests\User\GetTenantUsersRequest;
use Waterfront\Infra\AcronisClient\Requests\User\GetUserRequest;
use Waterfront\Infra\AcronisClient\Requests\User\GetUserSsoRequest;
use Waterfront\Infra\AcronisClient\Requests\User\PostSetUserPasswordRequest;
use Waterfront\Infra\AcronisClient\Requests\User\PostUserRequest;
use Waterfront\Infra\AcronisClient\Requests\User\PutUpdateUserAccessPoliciesRequest;
use Waterfront\Infra\Logging\Masker\JsonLogMasker;
use Waterfront\Infra\SaloonClient\Config\RetryConfig;
use Waterfront\Infra\SaloonClient\Faking\OAuthMockClient;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(AcronisUserClient::class)]
class AcronisUserClientTest extends TestCase
{
    private const string USER_ID = '08eb6187-70a7-4b31-b632-9d264fd8bb0f';

    private LoggerInterface&MockInterface $mockLogger;

    public function setUp(): void
    {
        parent::setUp();
        $this->mockLogger = self::mock(LoggerInterface::class);
    }

    #[Test]
    public function getUser(): void
    {
        $successRecordResponse = file_get_contents(__DIR__ . '/../data/user.json');

        $mockClient = new OAuthMockClient([
             GetUserRequest::class => MockResponse::make(body: $successRecordResponse),
         ]);

        $userClient = $this->makeUserClient($mockClient);

        $user = $userClient->get(self::USER_ID);
        self::assertSame(self::USER_ID, $user->id);
        self::assertSame('acronis.yourhosting@yourhosting.nl', $user->login);
    }

    #[Test]
    public function getUserList(): void
    {
        $tenant = 'tenant-test-1234';

        // These id's match the JSON file
        $user1 = 'aa4f8923-8950-4804-8827-c6d78388e5b6';
        $user2 = '4eb7b320-48b4-4552-9bf8-f7482538da23';

        $successRecordResponse = file_get_contents(__DIR__ . '/../data/tenant-user-list.json');

        $mockClient = new OAuthMockClient([
             GetTenantUsersRequest::class => MockResponse::make(body: $successRecordResponse),
         ]);

        $userClient = $this->makeUserClient($mockClient);

        $users = $userClient->list($tenant);

        self::assertCount(2, $users->items);
        self::assertSame($user1, $users->items[0]);
        self::assertSame($user2, $users->items[1]);
    }

    #[Test]
    public function getOtt(): void
    {
        $userUuid = Uuid::uuid4();
        $ott = 'dGhpcyBpcyB0ZXN0IG9uZS10aW1lIHRva2Vu'; // Matches json data

        $successRecordResponse = file_get_contents(__DIR__ . '/../data/one-time-token.json');
        $mockClient = new OAuthMockClient([
             GetUserSsoRequest::class => MockResponse::make(body: $successRecordResponse),
         ]);

        $userClient = $this->makeUserClient($mockClient);
        $response = $userClient->getSso($userUuid);
        self::assertSame($ott, $response->ott);
    }

    #[Test]
    public function getSsoThrowsAcronisSerializerException(): void
    {
        $userId = Uuid::uuid4();

        $successRecordResponse = '{}';
        $mockClient = new OAuthMockClient([
             GetUserSsoRequest::class => MockResponse::make(body: $successRecordResponse),
         ]);

        $userClient = $this->makeUserClient($mockClient);
        self::expectException(AcronisSerializerException::class);
        $userClient->getSso($userId);
    }

    #[Test]
    public function createUser(): void
    {
        $userCreate = $this->getUserCreate();

        $successRecordResponse = file_get_contents(__DIR__ . '/../data/user.json');
        $mockClient = new OAuthMockClient([
             PostUserRequest::class => MockResponse::make(body: $successRecordResponse),
         ]);

        $userClient = $this->makeUserClient($mockClient);
        $user = $userClient->create($userCreate);
        self::assertSame(self::USER_ID, $user->id);
        self::assertSame('acronis.yourhosting@yourhosting.nl', $user->login);
    }

    #[Test]
    public function createUserThrowsAcronisSerializerException(): void
    {
        $userCreate = $this->getUserCreate();

        $successRecordResponse = '{}';
        $mockClient = new OAuthMockClient([
             PostUserRequest::class => MockResponse::make(body: $successRecordResponse),
         ]);

        $userClient = $this->makeUserClient($mockClient);
        self::expectException(AcronisSerializerException::class);
        $userClient->create($userCreate);
    }

    #[Test]
    public function updateUserAccessPolicies(): void
    {
        $userAccessPolicies = $this->getUserAccessPolicies();

        $successRecordResponse = file_get_contents(__DIR__ . '/../data/access-policies-list.json');
        $mockClient = new OAuthMockClient([
            PutUpdateUserAccessPoliciesRequest::class => MockResponse::make(body: $successRecordResponse),
        ]);

        $userClient = $this->makeUserClient($mockClient);
        $userPolicy = $userClient->updateUserAccessPolicies(self::USER_ID, $userAccessPolicies);
        self::assertSame($userAccessPolicies->timestamp, $userPolicy->timestamp);
        self::assertCount(1, $userAccessPolicies->items);
    }

    #[Test]
    public function updateUserAccessPoliciesThrowsAcronisSerializerException(): void
    {
        $userAccessPolicies = $this->getUserAccessPolicies();

        $successRecordResponse = '{}';
        $mockClient = new OAuthMockClient([
            PutUpdateUserAccessPoliciesRequest::class => MockResponse::make(body: $successRecordResponse),
        ]);

        $userClient = $this->makeUserClient($mockClient);
        self::expectException(AcronisSerializerException::class);
        $userClient->updateUserAccessPolicies(self::USER_ID, $userAccessPolicies);
    }

    #[Test]
    public function updatePassword(): void
    {
        $password = 'password';

        $mockClient = new OAuthMockClient([
             PostSetUserPasswordRequest::class => MockResponse::make(),
         ]);

        $userClient = $this->makeUserClient($mockClient);
        $updatePassword = $userClient->updatePassword(self::USER_ID, $password);

        $mockClient->assertSent(fn (PostSetUserPasswordRequest $request) => $request->body()->get('password') === $password);

        self::assertTrue($updatePassword);
    }

    #[Test]
    public function updatePasswordFailed(): void
    {
        $password = 'password';

        $mockResponse = MockResponse::make(status: Response::HTTP_UNPROCESSABLE_ENTITY);
        $saloonException = new SaloonException();
        $mockResponse->throw($saloonException);

        $mockClient = new OAuthMockClient([
            PostSetUserPasswordRequest::class => $mockResponse,
        ]);

        $this->mockLogger
            ->expects('error')
            ->with(
                sprintf('Could not update Acronis password for user %s.', self::USER_ID),
                [
                    LoggingContextKeys::EXCEPTION => $saloonException,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::ACRONIS,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::BACKUP,
                ]
            );

        $userClient = $this->makeUserClient($mockClient);

        self::expectException($saloonException::class);
        $userClient->updatePassword(self::USER_ID, password: $password);
    }

    private function makeUserClient(MockClient $mockClient): AcronisUserClient
    {
        $connector = new AcronisConnector(
            acronisConfig: new ConnectorConfig(
                baseUrl: 'https://acronis.test/api/2',
                clientId: Uuid::uuid4()->toString(),
                clientSecret: 'abc123',
                retryConfig: new RetryConfig(),
            ),
            logger: self::createStub(LoggerInterface::class),
            logMasker: self::createStub(JsonLogMasker::class),
            cache: self::createStub(Repository::class),
        );

        $connector->withMockClient($mockClient);

        return new AcronisUserClient($connector, $this->mockLogger);
    }

    private function getUserCreate(): User
    {
        return new User(
            tenantId: 'b2bece2c-8e94-491b-b6b0-59d1c6f2b903',
            login: 'acronis.yourhosting.lollll@yourhosting.nl',
            enabled: true,
            contact: new Contact(
                firstname: 'le',
                lastname: 'tester',
                email: 'acronis.yourhosting@yourhosting.nl'
            ),
        );
    }

    private function getUserAccessPolicies(): UserAccessPolicies
    {
        return new UserAccessPolicies(
            items: [
                new PolicyItem(
                    tenantId: 'b2bece2c-8e94-491b-b6b0-59d1c6f2b903',
                    trusteeId: 'c83d74ef-8bd4-46af-ba1e-f2d25e51838e',
                    trusteeType: TrusteeType::USER,
                    roleId: RoleId::COMPANY_ADMIN,
                    version: CarbonImmutable::now()->timestamp,
                ),
            ],
            timestamp: '2016-06-22T18:25:16',
        );
    }
}
