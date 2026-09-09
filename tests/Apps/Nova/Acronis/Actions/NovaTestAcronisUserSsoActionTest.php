<?php

declare(strict_types=1);

namespace Tests\Apps\Nova\Acronis\Actions;

use Exception;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Actions\Responses\Message;
use Laravel\Nova\Actions\Responses\Redirect;
use Laravel\Nova\Fields\ActionFields;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use SandwaveIo\LighthouseAuthBase\Identity\KratosIdentity;
use Tests\Factories\AcronisProviderFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Nova\Acronis\Actions\NovaTestAcronisUserSsoAction;
use Waterfront\Domain\Backup\Services\BackupService;
use Waterfront\Domain\Provision\Backup\Acronis\Models\AcronisProvider;
use Waterfront\Infra\AcronisClient\DTO\Responses\Users\OneTimeToken;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Authentication\DTO\AuthenticatedEmployee;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(NovaTestAcronisUserSsoAction::class)]
#[AllowMockObjectsWithoutExpectations]
class NovaTestAcronisUserSsoActionTest extends IntegrationTestCase
{
    private const string SSO_TARGET_URL = 'https://target.test/dashboard';
    private const string ENDPOINT = 'https://acronis.test';

    private AcronisProvider $provider;

    private BackupService&MockObject $backupService;

    private AuthenticationManager&MockObject $authenticationManager;

    private LoggerInterface&MockObject $logger;

    private string $userUuid;

    private UuidInterface $employeeUuid;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = AcronisProviderFactory::new()->createOne([
            'endpoint' => self::ENDPOINT,
            'sso_target_url' => self::SSO_TARGET_URL,
        ]);
        $this->backupService = self::createMock(BackupService::class);
        $this->authenticationManager = self::createMock(AuthenticationManager::class);
        $this->logger = self::createMock(LoggerInterface::class);

        $this->userUuid = Uuid::uuid4()->toString();
        $this->employeeUuid = Uuid::uuid4();

        $this->authenticationManager
            ->method('getAuthenticatedEmployee')
            ->willReturn($this->buildAuthenticatedEmployee($this->employeeUuid));
    }

    #[Test]
    public function handleReturnsOpenInNewTabRedirectWithSsoUrlOnSuccess(): void
    {
        $expectedEmployeeUuid = $this->employeeUuid->toString();

        $this->backupService
            ->expects(self::once())
            ->method('getSsoForProviderByUuids')
            ->with(
                $this->provider,
                $this->userUuid,
                $expectedEmployeeUuid
            )
            ->willReturn(new OneTimeToken('one-time-token-value'));

        $result = $this->makeAction()->handle(
            $this->newActionFields(['user_uuid' => $this->userUuid]),
            new Collection([$this->provider]),
        );

        self::assertInstanceOf(ActionResponse::class, $result);

        $redirect = $result['redirect'];
        self::assertInstanceOf(Redirect::class, $redirect);
        self::assertTrue($redirect->openInNewTab);
        self::assertSame(
            sprintf(
                '%s/idp/external-login#ott=%s&targetURI=%s',
                self::ENDPOINT,
                rawurlencode('one-time-token-value'),
                self::SSO_TARGET_URL,
            ),
            $redirect->url,
        );
    }

    #[Test]
    public function handleReturnsDangerWhenGetSsoThrowsException(): void
    {
        $exception = new Exception('boom');

        $this->backupService
            ->expects(self::once())
            ->method('getSsoForProviderByUuids')
            ->willThrowException($exception);

        $this->logger
            ->expects(self::once())
            ->method('error');

        $result = $this->makeAction()->handle(
            $this->newActionFields(['user_uuid' => $this->userUuid]),
            new Collection([$this->provider]),
        );

        self::assertInstanceOf(ActionResponse::class, $result);
        $danger = $result['danger'];
        self::assertInstanceOf(Message::class, $danger);
        self::assertSame(
            'nova-action.acronis-provider.test-user-sso.failure => Exception',
            $danger->text,
        );
    }

    private function makeAction(): NovaTestAcronisUserSsoAction
    {
        return new NovaTestAcronisUserSsoAction(
            translator: self::resolve(TranslatorInterface::class),
            logger: $this->logger,
            authenticationManager: $this->authenticationManager,
            backupService: $this->backupService,
        );
    }

    private function buildAuthenticatedEmployee(UuidInterface $uuid): AuthenticatedEmployee
    {
        $kratosIdentity = new KratosIdentity(
            id: $uuid,
            schemaId: SchemaId::EMPLOYEE,
            state: 'active',
            schemaUrl: null,
            traits: null,
            createdAt: null,
            updatedAt: null,
            verifiableAddresses: null,
            recoveryAddresses: null,
            credentials: null,
            metadataPublic: null,
            metadataAdmin: null,
            authenticatedSession: null,
        );

        return new AuthenticatedEmployee($kratosIdentity, true);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function newActionFields(array $values = []): ActionFields
    {
        // @phpstan-ignore-next-line
        return new ActionFields(new Collection($values), new Collection([]));
    }
}
