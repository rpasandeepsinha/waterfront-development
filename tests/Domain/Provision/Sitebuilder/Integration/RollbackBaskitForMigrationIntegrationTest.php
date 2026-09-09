<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Sitebuilder\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\Factories\BasekitSitebuilderDeploymentFactory;
use Tests\Factories\ProvisioningRequestFactory;
use Tests\Factories\ProvisioningResultFactory;
use Tests\Factories\SitebuilderContextBasekitFactory;
use Tests\Factories\SitebuilderDeploymentFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Repositories\ProvisioningRequestRepository;
use Waterfront\Domain\Provision\Repositories\ProvisioningResultRepository;
use Waterfront\Domain\Provision\Services\ProvisionTraceabilityService;
use Waterfront\Domain\Provision\Sitebuilder\Models\BasekitContext;
use Waterfront\Domain\Provision\Sitebuilder\Models\BasekitSitebuilderDeployment;
use Waterfront\Domain\Provision\Sitebuilder\Models\SitebuilderDeployment;
use Waterfront\Domain\Provision\Sitebuilder\Repositories\SitebuilderDeploymentRepository;
use Waterfront\Domain\Provision\Sitebuilder\Requests\CreateBasekitDeploymentsFromMigrationRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\RollbackBasekitDeploymentsFromMigrationRequest;
use Waterfront\Domain\Provision\Sitebuilder\Services\CreateBasekitService;

#[CoversClass(CreateBasekitDeploymentsFromMigrationRequest::class)]
#[CoversClass(ProvisionGateway::class)]
#[CoversClass(ProvisionTraceabilityService::class)]
#[CoversClass(ProvisioningResultRepository::class)]
#[CoversClass(ProvisioningRequestRepository::class)]
#[CoversClass(CreateBasekitService::class)]
class RollbackBaskitForMigrationIntegrationTest extends IntegrationTestCase
{
    private const int USER_REF = 1111;
    private const int SITE_REF = 2222;
    private const ProvisionRequestName REQUEST_NAME = ProvisionRequestName::CREATE_BASEKIT_DEPLOYMENTS_FROM_MIGRATION;

    private ProvisionGateway $gateway;

    public function setUp(): void
    {
        parent::setUp();
        $this->gateway = self::resolve(ProvisionGateway::class);
    }

    #[Test]
    public function rollbackDeletesBasekitDeploymentsAndContext(): void
    {
        $contextUuid = Uuid::uuid4();
        $tag = Uuid::uuid4();

        SitebuilderContextBasekitFactory::new()->createOne([
            'context_uuid' => $contextUuid,
            'user_ref' => self::USER_REF,
        ]);

        $basekit = BasekitSitebuilderDeploymentFactory::new()
            ->for(
                SitebuilderDeploymentFactory::new()
                    ->for(
                        ProvisioningRequestFactory::new()
                            ->sitebuilder()
                            ->state([
                                'request_name' => self::REQUEST_NAME,
                                'context_uuid' => $contextUuid,
                                'tag' => $tag,
                            ])
                            ->has(ProvisioningResultFactory::new()->success(), 'result'),
                        'request'
                    ),
                'sitebuilderDeployment'
            )
            ->createOne([
                'site_ref' => self::SITE_REF,
            ]);

        self::assertDatabaseCount(BasekitContext::class, 1);
        self::assertDatabaseCount(SitebuilderDeployment::class, 1);
        self::assertDatabaseCount(BasekitSitebuilderDeployment::class, 1);

        $provisionRequest = new RollbackBasekitDeploymentsFromMigrationRequest(
            context: $contextUuid,
            tagUuid: $tag,
        );
        $provisionRequest->requestId = 999;
        $provisionRequest->provider = ProvisionProvider::BASEKIT;

        $provisionResult = $this->gateway->request($provisionRequest);

        self::assertSame(ProvisionStatus::SUCCESS, $provisionResult->provisionStatus);
        self::assertSoftDeleted(BasekitSitebuilderDeployment::class, [
            'id' => $basekit->id,
        ]);

        self::assertSoftDeleted(SitebuilderDeployment::class, [
            'id' => $basekit->sitebuilder_deployment_id,
        ]);

        self::assertSoftDeleted(BasekitContext::class, [
            'context_uuid' => $contextUuid->toString(),
        ]);
    }

    #[Test]
    public function rollbackDeletesOnlyOneDeploymentAndKeepsContextWhenOtherDeploymentsExist(): void
    {
        $contextUuid = Uuid::uuid4();
        $tagToDelete = Uuid::uuid4();
        $otherTag = Uuid::uuid4();

        SitebuilderContextBasekitFactory::new()->createOne([
            'context_uuid' => $contextUuid,
            'user_ref' => self::USER_REF,
        ]);

        $basekitA = BasekitSitebuilderDeploymentFactory::new()
            ->for(
                SitebuilderDeploymentFactory::new()
                    ->for(
                        ProvisioningRequestFactory::new()
                            ->sitebuilder()
                            ->state([
                                'request_name' => self::REQUEST_NAME,
                                'context_uuid' => $contextUuid,
                                'tag' => $tagToDelete,
                            ])
                            ->has(ProvisioningResultFactory::new()->success(), 'result'),
                        'request'
                    ),
                'sitebuilderDeployment'
            )
            ->createOne();

        $basekitB = BasekitSitebuilderDeploymentFactory::new()
            ->for(
                SitebuilderDeploymentFactory::new()
                    ->for(
                        ProvisioningRequestFactory::new()
                            ->sitebuilder()
                            ->state([
                                'request_name' => self::REQUEST_NAME,
                                'context_uuid' => $contextUuid,
                                'tag' => $otherTag,
                            ])
                            ->has(ProvisioningResultFactory::new()->success(), 'result'),
                        'request'
                    ),
                'sitebuilderDeployment'
            )
            ->createOne();

        $provisionRequest = new RollbackBasekitDeploymentsFromMigrationRequest(
            context: $contextUuid,
            tagUuid: $tagToDelete,
        );
        $provisionRequest->requestId = 1000;
        $provisionRequest->provider = ProvisionProvider::BASEKIT;

        $provisionResult = $this->gateway->request($provisionRequest);

        self::assertSame(ProvisionStatus::SUCCESS, $provisionResult->provisionStatus);

        self::assertSoftDeleted(BasekitSitebuilderDeployment::class, [
            'id' => $basekitA->id,
        ]);

        self::assertSoftDeleted(SitebuilderDeployment::class, [
            'id' => $basekitA->sitebuilder_deployment_id,
        ]);

        self::assertNotSoftDeleted(BasekitSitebuilderDeployment::class, [
            'id' => $basekitB->id,
        ]);

        self::assertNotSoftDeleted(SitebuilderDeployment::class, [
            'id' => $basekitB->sitebuilder_deployment_id,
        ]);

        self::assertNotSoftDeleted(BasekitContext::class, [
            'context_uuid' => $contextUuid->toString(),
        ]);
    }

    #[Test]
    public function rollbackFailsWhenDeploymentForTagDoesNotExist(): void
    {
        $contextUuid = Uuid::uuid4();
        $tagUuid = Uuid::uuid4();

        SitebuilderContextBasekitFactory::new()->createOne([
            'context_uuid' => $contextUuid,
            'user_ref' => self::USER_REF,
        ]);

        BasekitSitebuilderDeploymentFactory::new()
            ->for(
                SitebuilderDeploymentFactory::new()
                    ->for(
                        ProvisioningRequestFactory::new()
                            ->sitebuilder()
                            ->state([
                                'request_name' => self::REQUEST_NAME,
                                'context_uuid' => $contextUuid,
                                'tag' => $tagUuid,
                            ])
                            ->has(ProvisioningResultFactory::new()->success(), 'result'),
                        'request'
                    ),
                'sitebuilderDeployment'
            )
            ->createOne();

        $mockDeploymentRepo = $this->createStub(SitebuilderDeploymentRepository::class);

        $mockDeploymentRepo
            ->method('countCreateRequestsByTag')
            ->willReturn(1);

        $mockDeploymentRepo
            ->method('findByTag')
            ->willReturn(null);

        $this->app->instance(SitebuilderDeploymentRepository::class, $mockDeploymentRepo);

        $this->gateway = $this->app->make(ProvisionGateway::class);

        $provisionRequest = new RollbackBasekitDeploymentsFromMigrationRequest(
            context: $contextUuid,
            tagUuid: $tagUuid,
        );
        $provisionRequest->requestId = 222;
        $provisionRequest->provider = ProvisionProvider::BASEKIT;

        $provisionResult = $this->gateway->request($provisionRequest);

        self::assertSame(ProvisionStatus::FAILED, $provisionResult->provisionStatus);
        self::assertDatabaseCount(BasekitContext::class, 1);
    }

    #[Test]
    public function rollbackSucceedsWhenBasekitChildIsMissing(): void
    {
        $contextUuid = Uuid::uuid4();
        $tag = Uuid::uuid4();

        SitebuilderContextBasekitFactory::new()->createOne([
            'context_uuid' => $contextUuid,
            'user_ref' => self::USER_REF,
        ]);

        $sitebuilder = SitebuilderDeploymentFactory::new()
            ->for(
                ProvisioningRequestFactory::new()
                    ->sitebuilder()
                    ->state([
                        'request_name' => self::REQUEST_NAME,
                        'context_uuid' => $contextUuid,
                        'tag' => $tag,
                    ])
                    ->has(ProvisioningResultFactory::new()->success(), 'result'),
                'request'
            )
            ->createOne();

        $provisionRequest = new RollbackBasekitDeploymentsFromMigrationRequest(
            context: $contextUuid,
            tagUuid: $tag,
        );
        $provisionRequest->requestId = 333;
        $provisionRequest->provider = ProvisionProvider::BASEKIT;

        $provisionResult = $this->gateway->request($provisionRequest);

        self::assertSame(ProvisionStatus::SUCCESS, $provisionResult->provisionStatus);
        self::assertNull($provisionResult->exception);

        self::assertSoftDeleted(SitebuilderDeployment::class, [
            'id' => $sitebuilder->id,
        ]);

        self::assertSoftDeleted(BasekitContext::class, [
            'context_uuid' => $contextUuid->toString(),
        ]);
    }
}
