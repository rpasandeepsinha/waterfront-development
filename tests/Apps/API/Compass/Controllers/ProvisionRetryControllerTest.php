<?php

declare(strict_types=1);

namespace Tests\Apps\API\Compass\Controllers;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Controllers\ProvisionRetryController;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Exceptions\RetryOriginNotFoundException;
use Waterfront\Domain\Provision\Models\ProvisioningRequest;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Repositories\ProvisioningRequestRepository;
use Waterfront\Domain\Provision\Services\ProvisionRetryService;
use Waterfront\Domain\Provision\Sitebuilder\Requests\CreateSitebuilderRequest;
use Waterfront\Domain\Provision\Sitebuilder\Results\SitebuilderResult;
use Waterfront\Domain\Provision\Sitebuilder\Services\BasekitProvisionService;

#[CoversClass(ProvisionRetryController::class)]
class ProvisionRetryControllerTest extends IntegrationTestCase
{
    #[Test]
    public function retrySucceedsAfterEditingFailedSitebuilderRequestData(): void
    {
        Model::preventLazyLoading(false);

        $invalidEmail = 'invalid-email';
        $correctedEmail = 'retry@example.test';
        $retryRequester = Uuid::uuid4();
        $context = Uuid::uuid4();
        $retryData = [
            'domain' => 'example.com',
            'packages' => [1, 2],
            'firstname' => 'Retry',
            'lastname' => 'Requester',
            'email' => $correctedEmail,
            'contractPeriod' => 12,
        ];

        $basekitProvisionServiceMock = self::createMock(BasekitProvisionService::class);
        $basekitProvisionServiceMock->expects(self::once())
            ->method('create')
            ->with(self::callback(static function (CreateSitebuilderRequest $request) use ($correctedEmail): bool {
                self::assertSame($correctedEmail, $request->email);

                return true;
            }))
            ->willReturnCallback(
                static fn (CreateSitebuilderRequest $request): SitebuilderResult => new SitebuilderResult(
                    provisionData: $request,
                    provisionStatus: ProvisionStatus::SUCCESS,
                )
            );
        $this->app->bind(
            BasekitProvisionService::class,
            fn (): BasekitProvisionService => $basekitProvisionServiceMock,
        );

        $provisionGateway = self::resolve(ProvisionGateway::class);
        $failedRequest = new CreateSitebuilderRequest(
            domain: 'example.com',
            packages: [1, 2],
            firstname: 'Retry',
            lastname: 'Requester',
            email: $invalidEmail,
            contractPeriod: 12,
            context: $context,
        );

        $failedResult = $provisionGateway->request($failedRequest);

        self::assertSame(ProvisionStatus::VALIDATION_ERROR, $failedResult->provisionStatus);
        self::assertNotNull($failedResult->validationResult);
        self::assertArrayHasKey('email', $failedResult->validationResult->messages);

        $originRequest = self::resolve(ProvisioningRequestRepository::class)
            ->findById($failedRequest->requestId);
        self::assertNotNull($originRequest);

        $this->actingAsEmployee($retryRequester)
            ->postJson($this->generateRoute('admin.deployments.requests.retry'), [
                'retryOf' => $originRequest->uuid->toString(),
                'retryData' => $retryData,
            ])
            ->assertOk()
            ->assertJsonPath('provisionStatus', ProvisionStatus::SUCCESS->value);

        self::assertDatabaseHas(ProvisioningRequest::class, [
            'retry_of_request_id' => $originRequest->id,
            'requested_by_uuid' => $retryRequester,
        ]);
    }

    #[Test]
    public function retrySendsAuthenticatedRequesterAndRetryDataToService(): void
    {
        $retryOf = Uuid::uuid4();
        $retryRequester = Uuid::uuid4();
        $context = Uuid::uuid4();
        $retryData = [
            'domain' => 'retry.example',
            'packages' => [1, 2],
            'firstname' => 'Retry',
            'lastname' => 'Requester',
            'email' => 'retry@example.test',
            'contractPeriod' => 12,
        ];
        $provisionRequest = new CreateSitebuilderRequest(
            domain: 'retry.example',
            packages: [1, 2],
            firstname: 'Retry',
            lastname: 'Requester',
            email: 'retry@example.test',
            contractPeriod: 12,
            context: $context,
        );
        $provisionResult = new SitebuilderResult(
            provisionData: $provisionRequest,
            provisionStatus: ProvisionStatus::SUCCESS,
        );

        $provisionRetryServiceMock = self::createMock(ProvisionRetryService::class);
        $provisionRetryServiceMock->expects(self::once())
            ->method('retry')
            ->with(
                self::callback(static fn (UuidInterface $uuid): bool => $retryOf->equals($uuid)),
                self::callback(static fn (UuidInterface $uuid): bool => $retryRequester->equals($uuid)),
                $retryData,
            )
            ->willReturn($provisionResult);
        $this->app->bind(
            ProvisionRetryService::class,
            fn (): ProvisionRetryService => $provisionRetryServiceMock,
        );

        $this->actingAsEmployee($retryRequester)
            ->postJson(
                $this->generateRoute('admin.deployments.requests.retry'),
                [
                    'retryOf' => $retryOf->toString(),
                    'retryData' => $retryData,
                ],
            )
            ->assertOk()
            ->assertJsonPath('provisionStatus', ProvisionStatus::SUCCESS->value);
    }

    #[Test]
    public function retryRequiresRetryOfAndRetryData(): void
    {
        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.deployments.requests.retry'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'retryOf',
                'retryData',
            ]);
    }

    #[Test]
    public function retryRejectsEmptyRetryData(): void
    {
        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.deployments.requests.retry'), [
                'retryOf' => Uuid::uuid4()->toString(),
                'retryData' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['retryData']);
    }

    #[Test]
    public function retryReturnsValidationErrorWhenOriginRequestCannotBeFetched(): void
    {
        $retryOf = Uuid::uuid4();

        $provisionRetryServiceMock = self::createMock(ProvisionRetryService::class);
        $provisionRetryServiceMock->expects(self::once())
            ->method('retry')
            ->willThrowException(new RetryOriginNotFoundException($retryOf));
        $this->app->bind(
            ProvisionRetryService::class,
            fn (): ProvisionRetryService => $provisionRetryServiceMock,
        );

        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.deployments.requests.retry'), [
                'retryOf' => $retryOf->toString(),
                'retryData' => ['email' => 'retry@example.test'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['retryOf']);
    }
}
