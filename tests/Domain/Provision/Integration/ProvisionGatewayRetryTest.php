<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Factories\ProvisionSerializeFactory;
use Waterfront\Domain\Provision\Factories\ProvisionServiceFactory;
use Waterfront\Domain\Provision\Interfaces\ProvisionServiceInterface;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Repositories\ProvisioningRequestRepository;
use Waterfront\Domain\Provision\Repositories\ProvisioningResultRepository;
use Waterfront\Domain\Provision\Results\ProvisionResult;
use Waterfront\Domain\Provision\Services\ProvisionTraceabilityService;
use Waterfront\Domain\Provision\Sitebuilder\Requests\CreateSitebuilderRequest;
use Waterfront\Domain\Provision\Validation\RetryRequestValidator;
use Waterfront\Domain\Provision\Validation\ValidationResult;

#[CoversClass(ProvisionGateway::class)]
#[CoversClass(ProvisionSerializeFactory::class)]
#[CoversClass(ProvisionTraceabilityService::class)]
#[CoversClass(RetryRequestValidator::class)]
class ProvisionGatewayRetryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function retryOfFailedRequestThroughJsonEdit(): void
    {
        $serializer = new ProvisionSerializeFactory()->get();
        $requestRepository = $this->app->make(ProvisioningRequestRepository::class);

        $mockService = self::mock(ProvisionServiceInterface::class);
        // Called once: only the first request has provider=null. After the gateway resolves
        // and stores the default, the serialized JSON keeps provider=BASEKIT, so the retry
        // request already has a provider and the fallback is skipped.
        $mockService->expects('getDefaultProvider')->once()->andReturn(ProvisionProvider::BASEKIT);
        $mockService->expects('validate')->twice()->andReturnNull();

        $mockFactory = self::mock(ProvisionServiceFactory::class);
        $mockFactory->expects('create')->twice()->andReturn($mockService);

        $fixedEmail = 'fixed@email.nl';
        $brokenEmail = 'not-email';

        $request = new CreateSitebuilderRequest(
            domain: 'test-kees.nl',
            packages: [1, 2, 1337],
            firstname: 'test',
            lastname: 'kees',
            email: $brokenEmail,
            contractPeriod: 12,
            context: Uuid::uuid4(),
        );

        $gateway = new ProvisionGateway(
            provisionServiceFactory: $mockFactory,
            provisionTraceabilityService: $this->app->make(ProvisionTraceabilityService::class),
            repository: $this->app->make(ProvisioningResultRepository::class),
            logger: $this->app->make(LoggerInterface::class),
            retryRequestValidator: $this->app->make(RetryRequestValidator::class),
        );

        $mockedResult = new ProvisionResult(
            $request,
            ProvisionStatus::VALIDATION_ERROR,
            validationResult: new ValidationResult(false, ['general' => ['error']]),
        );
        $mockService->expects('send')->once()->with($request)->andReturn($mockedResult);

        $failedValidationResult = $gateway->request($request);

        $json = $serializer->serialize($failedValidationResult->provisionData, 'json');
        $array = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        self::assertIsArray($array);
        self::assertNotNull($array['email']);
        self::assertSame($brokenEmail, $array['email']);

        $array['email'] = $fixedEmail;

        $newJson = json_encode($array, flags: JSON_THROW_ON_ERROR);

        /** @var CreateSitebuilderRequest $retryRequest */
        $retryRequest = $serializer->deserialize($newJson, CreateSitebuilderRequest::class, 'json');

        self::assertSame($fixedEmail, $retryRequest->email);

        $originalRequest = $requestRepository->findById($failedValidationResult->provisionData->requestId);

        $retryRequest->retryOf = $originalRequest?->uuid;
        $retryRequest->retryRequester = Uuid::uuid4();

        $mockedResult = new ProvisionResult($retryRequest, ProvisionStatus::SUCCESS);
        $mockService->expects('send')->once()->with($retryRequest)->andReturn($mockedResult);

        $retryResult = $gateway->request($retryRequest);

        self::assertSame(ProvisionStatus::SUCCESS, $retryResult->provisionStatus);
        self::assertInstanceOf(CreateSitebuilderRequest::class, $retryResult->provisionData);
        self::assertSame($fixedEmail, $retryResult->provisionData->email);

        self::assertDatabaseHas('provisioning_requests', [
            'id' => $originalRequest?->id,
            'retry_of_request_id' => null,
            'requested_by_uuid' => null,
        ]);

        self::assertDatabaseHas('provisioning_requests', [
            'id' => $retryResult->provisionData->requestId,
            'retry_of_request_id' => $originalRequest?->id,
            'requested_by_uuid' => $retryRequest->retryRequester,
        ]);

        self::assertDatabaseHas('provisioning_results', [
            'request_id' => $originalRequest?->id,
            'status' => ProvisionStatus::VALIDATION_ERROR->value,
        ]);

        self::assertDatabaseHas('provisioning_results', [
            'request_id' => $retryResult->provisionData->requestId,
            'status' => ProvisionStatus::SUCCESS->value,
        ]);
    }
}
