<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Repositories;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\Factories\ProvisioningRequestFactory;
use Tests\Factories\ProvisioningResultFactory;
use Tests\TestCase;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Repositories\ProvisioningRequestRepository;

#[CoversClass(ProvisioningRequestRepository::class)]
class ProvisioningRequestRepositoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function createRequestExists(): void
    {
        $tag = Uuid::uuid4();

        $request = ProvisioningRequestFactory::new()
            ->state([
                'tag' => $tag,
                'request_name' => ProvisionRequestName::CREATE_HOSTING,
            ])
            ->hosting()
            ->has(ProvisioningResultFactory::new()->success(), 'result')
            ->createOne();

        $repository = new ProvisioningRequestRepository();

        // Assert we should find a request in the same group with a success result not having a retry.
        self::assertTrue($repository->createRequestExists($tag, ProvisionType::HOSTING));

        // Assert we don't find the tag when looking for a different ProvisionType.
        self::assertFalse($repository->createRequestExists($tag, ProvisionType::BACKUP));

        // Assert we check for success result
        self::assertNotNull($request->result);
        $request->result->status = ProvisionStatus::FAILED;
        $request->result->save();

        self::assertFalse($repository->createRequestExists($tag, ProvisionType::HOSTING));
    }

    #[Test]
    public function createRequestCountReturnsZeroWhenNoMatchingRequestsExist(): void
    {
        $repository = new ProvisioningRequestRepository();

        self::assertSame(0, $repository->createRequestCount(Uuid::uuid4(), ProvisionType::HOSTING));
    }

    #[Test]
    public function createRequestCountReturnsNumberOfSuccessfulCreateRequestsForTagAndType(): void
    {
        $tag = Uuid::uuid4();

        ProvisioningRequestFactory::new()
            ->count(2)
            ->state([
                'tag' => $tag,
                'request_name' => ProvisionRequestName::CREATE_HOSTING,
            ])
            ->hosting()
            ->has(ProvisioningResultFactory::new()->success(), 'result')
            ->create();

        ProvisioningRequestFactory::new()
            ->state([
                'tag' => $tag,
                'request_name' => ProvisionRequestName::CREATE_HOSTING,
            ])
            ->hosting()
            ->has(ProvisioningResultFactory::new()->state(['status' => ProvisionStatus::FAILED]), 'result')
            ->createOne();

        ProvisioningRequestFactory::new()
            ->state([
                'tag' => $tag,
                'request_name' => ProvisionRequestName::CREATE_BACKUP,
            ])
            ->backup()
            ->has(ProvisioningResultFactory::new()->success(), 'result')
            ->createOne();

        // A different tag should not be counted.
        ProvisioningRequestFactory::new()
            ->state([
                'tag' => Uuid::uuid4(),
                'request_name' => ProvisionRequestName::CREATE_HOSTING,
            ])
            ->hosting()
            ->has(ProvisioningResultFactory::new()->success(), 'result')
            ->createOne();

        $repository = new ProvisioningRequestRepository();

        self::assertSame(2, $repository->createRequestCount($tag, ProvisionType::HOSTING));
        self::assertSame(1, $repository->createRequestCount($tag, ProvisionType::BACKUP));
    }

    #[Test]
    public function createRequestCountIgnoresNonCreateRequestNames(): void
    {
        $tag = Uuid::uuid4();

        $nonCreateRequestName = ProvisionRequestName::GET_BACKUP_USAGE;

        ProvisioningRequestFactory::new()
            ->state([
                'tag' => $tag,
                'request_name' => $nonCreateRequestName,
            ])
            ->backup()
            ->has(ProvisioningResultFactory::new()->success(), 'result')
            ->createOne();

        $repository = new ProvisioningRequestRepository();

        self::assertSame(0, $repository->createRequestCount($tag, ProvisionType::BACKUP));
    }
}
