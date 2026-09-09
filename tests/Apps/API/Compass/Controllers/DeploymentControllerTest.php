<?php

declare(strict_types=1);

namespace Tests\Apps\API\Compass\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Controllers\DeploymentController;
use Waterfront\Apps\API\Compass\DTO\ProvisioningRequestDTO;
use Waterfront\Apps\API\Compass\DTO\ProvisionResultDTO;
use Waterfront\Apps\API\Compass\Transformers\ProvisioningResultGroupingTransformer;
use Waterfront\Apps\API\Waterfront\Policies\SubscriptionPolicy;
use Waterfront\Domain\Domains\Actions\RetryDomainAction;
use Waterfront\Domain\Hosting\Actions\UpdateHostingDeploymentAction;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Domain\Provision\DTO\ProvisioningFilteredResult;
use Waterfront\Domain\Provision\DTO\ProvisioningResultQueryFilters;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Sitebuilder\SitebuilderService;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(DeploymentController::class)]
class DeploymentControllerTest extends IntegrationTestCase
{
    #[Test]
    public function failedProvisioningResultsReturnsPaginatedResponseWithFailedRequestCount(): void
    {
        $gatewayMock = self::createMock(ProvisionGateway::class);
        $transformerMock = self::createMock(ProvisioningResultGroupingTransformer::class);

        $requestUuidOne = Uuid::uuid4();
        $requestUuidTwo = Uuid::uuid4();
        $tag = Uuid::uuid4();

        $fetchedResults = new Collection([
            $this->createProvisioningFilteredResult($requestUuidOne, $tag),
            $this->createProvisioningFilteredResult($requestUuidOne, $tag),
            $this->createProvisioningFilteredResult($requestUuidTwo, $tag),
        ]);

        $firstGroupedRequest = $this->createProvisioningRequestDto($requestUuidOne, $tag);
        $secondGroupedRequest = $this->createProvisioningRequestDto($requestUuidTwo, $tag);
        $transformedRequests = new Collection([$firstGroupedRequest, $secondGroupedRequest]);

        $gatewayMock->expects(self::once())
            ->method('fetch')
            ->with(
                self::callback(function (ProvisioningResultQueryFilters $filters): bool {
                    self::assertNull($filters->tag);
                    self::assertNull($filters->requestType);
                    self::assertSame(
                        [
                            ProvisionStatus::FAILED,
                            ProvisionStatus::DELETION_FAILED,
                            ProvisionStatus::VALIDATION_ERROR,
                        ],
                        $filters->provisionStatus,
                    );

                    return true;
                }),
                null,
            )
            ->willReturn($fetchedResults);

        $transformerMock->expects(self::once())
            ->method('transform')
            ->with($fetchedResults)
            ->willReturn($transformedRequests);

        $controller = $this->createController($gatewayMock, $transformerMock);

        $response = $controller->FailedProvisioningResults(
            Request::create('/deployments/requests/failed', 'GET', ['pageSize' => 1, 'page' => 2])
        )->response();

        $payload = $response->getData(true);

        self::assertSame(200, $response->status());
        self::assertIsArray($payload);

        self::assertIsArray($payload['data']);
        self::assertCount(1, $payload['data']);

        self::assertIsArray($payload['meta']);
        self::assertArrayHasKey('totalFailedRequests', $payload['meta']);
        self::assertSame(3, $payload['meta']['totalFailedRequests']);

        self::assertIsArray($payload['data'][0]);
        self::assertSame($secondGroupedRequest->uuid->toString(), $payload['data'][0]['uuid']);
    }

    #[Test]
    public function failedProvisioningResultsUsesDefaultPageSizeWhenInvalidPageSizeIsProvided(): void
    {
        $gatewayMock = self::createMock(ProvisionGateway::class);
        $transformerMock = self::createMock(ProvisioningResultGroupingTransformer::class);

        $tag = Uuid::uuid4();
        $requestUuid = Uuid::uuid4();

        $fetchedResults = new Collection([
            $this->createProvisioningFilteredResult($requestUuid, $tag),
        ]);

        $transformedRequests = new Collection();
        for ($i = 0; $i < 101; $i++) {
            $transformedRequests->push($this->createProvisioningRequestDto(Uuid::uuid4(), $tag));
        }

        $gatewayMock->expects(self::once())
            ->method('fetch')
            ->willReturn($fetchedResults);

        $transformerMock->expects(self::once())
            ->method('transform')
            ->with($fetchedResults)
            ->willReturn($transformedRequests);

        $controller = $this->createController($gatewayMock, $transformerMock);

        $payload = $controller->FailedProvisioningResults(
            Request::create('/deployments/requests/failed', 'GET', ['pageSize' => 'invalid'])
        )->response()->getData(true);

        self::assertIsArray($payload);
        self::assertIsArray($payload['data']);

        self::assertCount(100, $payload['data']);
    }

    private function createController(
        ProvisionGateway&MockObject $gatewayMock,
        ProvisioningResultGroupingTransformer&MockObject $transformerMock,
    ): DeploymentController {
        return new DeploymentController(
            hostingService: self::createStub(HostingService::class),
            subscriptionPolicy: self::createStub(SubscriptionPolicy::class),
            provisionGateway: $gatewayMock,
            retryDomainAction: self::createStub(RetryDomainAction::class),
            translator: self::createStub(TranslatorInterface::class),
            sitebuilderService: self::createStub(SitebuilderService::class),
            updateHostingDeploymentAction: self::createStub(UpdateHostingDeploymentAction::class),
            provisioningResultGroupingTransformer: $transformerMock,
        );
    }

    private function createProvisioningFilteredResult(
        UuidInterface $requestUuid,
        UuidInterface $tag,
    ): ProvisioningFilteredResult {
        return new ProvisioningFilteredResult(
            resultId: 1,
            uuid: Uuid::uuid4(),
            tag: $tag,
            context: null,
            response: '{"error":"failed"}',
            status: ProvisionStatus::FAILED,
            createdAt: null,
            requestCreatedAt: null,
            requestUpdatedAt: null,
            requestData: '{"domain":"example.com"}',
            requestUuid: $requestUuid,
            requestName: ProvisionRequestName::CREATE_HOSTING,
            requestType: ProvisionType::HOSTING,
            provider: ProvisionProvider::DIRECTADMIN,
        );
    }

    private function createProvisioningRequestDto(
        UuidInterface $requestUuid,
        UuidInterface $tag,
    ): ProvisioningRequestDTO {
        return new ProvisioningRequestDTO(
            uuid: $requestUuid,
            tag: $tag,
            requestData: '{"domain":"example.com"}',
            requestType: ProvisionType::HOSTING,
            createdAt: null,
            updatedAt: null,
            requestName: ProvisionRequestName::CREATE_HOSTING,
            context: null,
            provisionProvider: ProvisionProvider::DIRECTADMIN,
            retryOf: null,
            retryRequester: null,
            provisioningResults: [
                new ProvisionResultDTO(
                    uuid: Uuid::uuid4(),
                    createdAt: null,
                    response: '{"error":"failed"}',
                    status: ProvisionStatus::FAILED,
                ),
            ],
        );
    }
}
