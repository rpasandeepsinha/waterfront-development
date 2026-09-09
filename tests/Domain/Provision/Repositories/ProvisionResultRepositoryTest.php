<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Repositories;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\Factories\ProvisioningRequestFactory;
use Tests\Factories\ProvisioningResultFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Provision\DTO\ProvisioningFilteredResult;
use Waterfront\Domain\Provision\DTO\ProvisioningResultQueryFilters;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Repositories\ProvisioningResultRepository;

#[CoversClass(ProvisioningResultRepository::class)]
class ProvisionResultRepositoryTest extends IntegrationTestCase
{
    private ProvisioningResultRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = self::resolve(ProvisioningResultRepository::class);
    }

    #[Test]
    public function filterOnUuid(): void
    {
        $uuid = Uuid::uuid4();
        $filters = new ProvisioningResultQueryFilters(uuid: $uuid);

        $expectedResult = ProvisioningResultFactory::new()
            ->for(ProvisioningRequestFactory::new()->hosting())
            ->createOne([
                'uuid' => $uuid,
            ]);

        $request = $expectedResult->provisioningRequest;
        $result = $this->repo->fetchProvisioningResults($filters, 1)->first();

        self::assertInstanceOf(ProvisioningFilteredResult::class, $result);

        self::assertSame($uuid->toString(), $result->uuid->toString());
        self::assertSame($expectedResult->response, $result->response);
        self::assertSame($expectedResult->id, $result->resultId);
        self::assertSame($request->request_type, $result->requestType);
        self::assertSame($request->request_name, $result->requestName);
        self::assertTrue($request->tag->equals($result->tag));
        self::assertTrue($request->uuid->equals($result->requestUuid));

        self::assertNotNull($request->created_at);
        self::assertNotNull($result->requestCreatedAt);
        self::assertTrue($request->created_at->eq($result->requestCreatedAt));

        self::assertNotNull($result->createdAt);
        self::assertNotNull($expectedResult->created_at);
        self::assertTrue($expectedResult->created_at->eq($result->createdAt));
    }

    #[Test]
    public function filterOnRequestId(): void
    {
        ProvisioningRequestFactory::new()
            ->hosting()
            ->has(ProvisioningResultFactory::new()->success(), 'result')
            ->createMany(2);

        $request = ProvisioningRequestFactory::new()->hosting()->createOne();
        ProvisioningResultFactory::new()->for($request)->createOne();
        $filters = new ProvisioningResultQueryFilters(requestUuid: $request->uuid);

        $results = $this->repo->fetchProvisioningResults($filters, 3);
        self::assertCount(1, $results);

        $result = $results->first();
        self::assertInstanceOf(ProvisioningFilteredResult::class, $result);
        self::assertTrue($request->uuid->equals($result->requestUuid));
    }

    #[Test]
    public function filterOnProvisionStatusMultipleResultsAreCorrect(): void
    {
        $filters = new ProvisioningResultQueryFilters(provisionStatus: [ProvisionStatus::DELETING]);
        ProvisioningResultFactory::new()
            ->for(ProvisioningRequestFactory::new()->hosting())
            ->createOne([
                'status' => ProvisionStatus::DELETING,
            ]);

        ProvisioningResultFactory::new()
            ->for(ProvisioningRequestFactory::new()->hosting())
            ->createOne([
                'status' => ProvisionStatus::DELETING,
            ]);

        ProvisioningResultFactory::new()
            ->for(ProvisioningRequestFactory::new()->hosting())
            ->createOne([
                'status' => ProvisionStatus::PENDING,
            ]);

        $results = $this->repo->fetchProvisioningResults($filters);

        self::assertTrue($results->doesntContain('status', value: ProvisionStatus::PENDING));
        self::assertTrue($results->contains('status', value: ProvisionStatus::DELETING));
    }

    #[Test]
    public function filterOnFromDateAndStatus(): void
    {
        $filters = new ProvisioningResultQueryFilters(provisionStatus: [ProvisionStatus::DELETING], fromDate: CarbonImmutable::now()->subWeek());

        $superOld = ProvisioningResultFactory::new()
            ->for(ProvisioningRequestFactory::new()->hosting())
            ->createOne([
            'status' => ProvisionStatus::DELETING,
            'created_at' => CarbonImmutable::now()->subMonths(22),
        ]);

        $shouldBeFound = ProvisioningResultFactory::new()
            ->for(ProvisioningRequestFactory::new()->hosting())
            ->createOne([
            'status' => ProvisionStatus::DELETING,
            'created_at' => CarbonImmutable::now()->subDay(),
        ]);

        $shouldntBeFound = ProvisioningResultFactory::new()
            ->for(ProvisioningRequestFactory::new()->hosting())
            ->createOne([
            'status' => ProvisionStatus::PENDING,
            'created_at' => CarbonImmutable::now()->subDay(),
        ]);

        $results = $this->repo->fetchProvisioningResults($filters);

        self::assertTrue($results->doesntContain('resultId', value: [$shouldntBeFound->id, $superOld->id]));
        self::assertTrue($results->contains('resultId', value: $shouldBeFound->id));
    }

    #[Test]
    public function filterOnBetweenDates(): void
    {
        $filters = new ProvisioningResultQueryFilters(fromDate: CarbonImmutable::now()->subWeek(), toDate: CarbonImmutable::yesterday());
        $superOld = ProvisioningResultFactory::new()
            ->for(ProvisioningRequestFactory::new())
            ->createOne([
                'created_at' => CarbonImmutable::now()->subMonths(22),
            ]);

        $shouldBeFound = ProvisioningResultFactory::new()
            ->for(ProvisioningRequestFactory::new())
            ->createOne([
                'created_at' => CarbonImmutable::now()->subDays(3),
            ]);

        $shouldntBeFound = ProvisioningResultFactory::new()
            ->for(ProvisioningRequestFactory::new())
            ->createOne([
                'created_at' => CarbonImmutable::today(),
            ]);

        $results = $this->repo->fetchProvisioningResults($filters, 100);

        self::assertTrue($results->doesntContain('resultId', value: [$shouldntBeFound->id, $superOld->id]));
        self::assertTrue($results->contains('resultId', value: $shouldBeFound->id));
    }

    #[Test]
    public function filterOnRequestType(): void
    {
        $hostingRequest = ProvisioningRequestFactory::new()->hosting()->createOne();
        ProvisioningResultFactory::new()->for($hostingRequest)->createOne();
        ProvisioningResultFactory::new()->for(ProvisioningRequestFactory::new()->redirect())->createOne();

        $filters = new ProvisioningResultQueryFilters(requestType: $hostingRequest->request_type);

        $results = $this->repo->fetchProvisioningResults($filters);
        self::assertCount(1, $results);
        self::assertTrue($results->firstOrFail()->requestUuid->equals($hostingRequest->uuid));
    }

    #[Test]
    public function filterOnlyCreateRequests(): void
    {
        $hostingCreateRequest = ProvisioningRequestFactory::new()
            ->hosting()
            ->has(ProvisioningResultFactory::new(), 'result')
            ->createOne(['request_name' => ProvisionRequestName::CREATE_HOSTING]);

        ProvisioningRequestFactory::new()
            ->hosting()
            ->has(ProvisioningResultFactory::new(), 'result')
            ->createOne(['request_name' => ProvisionRequestName::GET_HOSTING_SSO]);

        $filters = new ProvisioningResultQueryFilters(onlyCreateRequests: true);

        $results = $this->repo->fetchProvisioningResults($filters);
        self::assertCount(1, $results);
        self::assertTrue($results->firstOrFail()->requestUuid->equals($hostingCreateRequest->uuid));
    }

    #[Test]
    public function filterOnTagBetweenDates(): void
    {
        $uuid = Uuid::uuid4();

        $filters = new ProvisioningResultQueryFilters(
            fromDate: CarbonImmutable::now()->subWeek(),
            toDate: CarbonImmutable::yesterday(),
            tag: $uuid
        );

        $superOld = ProvisioningResultFactory::new()
            ->for(ProvisioningRequestFactory::new()->hosting()->state(['tag' => $uuid]))
            ->createOne([
                'created_at' => CarbonImmutable::now()->subMonths(22),
            ]);

        $shouldBeFound = ProvisioningResultFactory::new()
            ->for(ProvisioningRequestFactory::new()->dns()->state(['tag' => $uuid]))
            ->createOne([
                'created_at' => CarbonImmutable::now()->subDays(3),
            ]);

        $shouldntBeFound = ProvisioningResultFactory::new()
            ->for(ProvisioningRequestFactory::new()->vps()->state(['tag' => $uuid]))
            ->createOne([
                'created_at' => CarbonImmutable::now()->subDays(3),
            ]);

        $results = $this->repo->fetchProvisioningResults($filters, 100);

        self::assertTrue($results->doesntContain(key: 'resultId', value: [$shouldntBeFound->id, $superOld->id]));
        self::assertTrue($results->contains(key: 'resultId', value: $shouldBeFound->id));
        self::assertTrue($results->contains(key: 'tag', value: $uuid->toString()));
    }

    #[Test]
    public function onlyRetryRequestsFilter(): void
    {
        $filters = new ProvisioningResultQueryFilters(onlyRetryRequests: true);

        $withRetry = ProvisioningResultFactory::new()
            ->for(
                ProvisioningRequestFactory::new()
                    ->hosting()
                    ->for(ProvisioningRequestFactory::new()->hosting(), 'retryOf')
                    ->state(['requested_by_uuid' => Uuid::uuid4()])
            )
            ->validationError()
            ->createOne();

        $withoutRetry = ProvisioningResultFactory::new()
            ->for(ProvisioningRequestFactory::new()->hosting())
            ->success()
            ->createOne();

        $results = $this->repo->fetchProvisioningResults($filters);

        self::assertTrue($results->doesntContain('uuid', value: $withoutRetry->uuid));
        self::assertTrue($results->contains('uuid', value: $withRetry->uuid));
    }

    #[Test]
    public function retryRequestUuidFilter(): void
    {
        $uuidExpectedInResults = Uuid::uuid4();

        $filters = new ProvisioningResultQueryFilters(retryOf: $uuidExpectedInResults);

        $withSpecificRetry = ProvisioningResultFactory::new()
            ->for(
                ProvisioningRequestFactory::new()
                    ->hosting()
                    ->for(ProvisioningRequestFactory::new()->hosting()->state(['uuid' => $uuidExpectedInResults]), 'retryOf')
                    ->state([
                        'requested_by_uuid' => Uuid::uuid4(),
                    ])
            )
            ->validationError()
            ->createOne();

        $withOtherRetry = ProvisioningResultFactory::new()
            ->for(
                ProvisioningRequestFactory::new()
                    ->hosting()
                    ->for(ProvisioningRequestFactory::new()->hosting(), 'retryOf')
                    ->state(['requested_by_uuid' => Uuid::uuid4()])
            )
            ->validationError()
            ->createOne();

        $withoutRetry = ProvisioningResultFactory::new()
            ->for(ProvisioningRequestFactory::new()->hosting())
            ->success()
            ->createOne();

        $results = $this->repo->fetchProvisioningResults($filters);

        self::assertTrue($results->doesntContain('uuid', value: $withOtherRetry->uuid));
        self::assertTrue($results->doesntContain('uuid', value: $withoutRetry->uuid));
        self::assertTrue($results->contains('uuid', value: $withSpecificRetry->uuid));
    }

    #[Test]
    public function retryRequesterFilter(): void
    {
        $uuidExpectedInResults = Uuid::uuid4();

        $filters = new ProvisioningResultQueryFilters(retryRequester: $uuidExpectedInResults);

        $withSpecificRetry = ProvisioningResultFactory::new()
            ->for(
                ProvisioningRequestFactory::new()
                    ->hosting()
                    ->for(ProvisioningRequestFactory::new()->hosting(), 'retryOf')
                    ->state([
                        'requested_by_uuid' => $uuidExpectedInResults,
                    ])
            )
            ->validationError()
            ->createOne();

        $withSpecificRetry2 = ProvisioningResultFactory::new()
            ->for(
                ProvisioningRequestFactory::new()
                    ->hosting()
                    ->for(ProvisioningRequestFactory::new()->hosting(), 'retryOf')
                    ->state([
                        'requested_by_uuid' => $uuidExpectedInResults,
                    ])
            )
            ->validationError()
            ->createOne();

        $withOtherRetry = ProvisioningResultFactory::new()
            ->for(
                ProvisioningRequestFactory::new()
                    ->hosting()
                    ->for(ProvisioningRequestFactory::new()->hosting(), 'retryOf')
                    ->state(['requested_by_uuid' => Uuid::uuid4()])
            )
            ->validationError()
            ->createOne();

        $withoutRetry = ProvisioningResultFactory::new()
            ->for(ProvisioningRequestFactory::new()->hosting())
            ->success()
            ->createOne();

        $results = $this->repo->fetchProvisioningResults($filters);

        self::assertCount(2, $results);
        self::assertTrue($results->doesntContain('uuid', value: $withOtherRetry->uuid));
        self::assertTrue($results->doesntContain('uuid', value: $withoutRetry->uuid));
        self::assertTrue($results->contains('uuid', value: $withSpecificRetry->uuid));
        self::assertTrue($results->contains('uuid', value: $withSpecificRetry2->uuid));
    }

    #[Test]
    public function resultsAreOrderedByCreatedAtDesc(): void
    {
        $requestUuid = Uuid::uuid4();

        $oldest = ProvisioningResultFactory::new()
            ->for(ProvisioningRequestFactory::new()->state(['uuid' => $requestUuid]))
            ->createOne([
                'created_at' => CarbonImmutable::now()->subDays(3),
            ]);

        $newest = ProvisioningResultFactory::new()
            ->for(ProvisioningRequestFactory::new()->state(['uuid' => $requestUuid]))
            ->createOne([
                'created_at' => CarbonImmutable::now()->subDay(),
            ]);

        $middle = ProvisioningResultFactory::new()
            ->for(ProvisioningRequestFactory::new()->state(['uuid' => $requestUuid]))
            ->createOne([
                'created_at' => CarbonImmutable::now()->subDays(2),
            ]);

        $filters = new ProvisioningResultQueryFilters(requestUuid: $requestUuid);
        $results = $this->repo->fetchProvisioningResults($filters);

        self::assertCount(3, $results);
        self::assertSame($newest->id, $results->get(0)?->resultId);
        self::assertSame($middle->id, $results->get(1)?->resultId);
        self::assertSame($oldest->id, $results->get(2)?->resultId);
    }
}
