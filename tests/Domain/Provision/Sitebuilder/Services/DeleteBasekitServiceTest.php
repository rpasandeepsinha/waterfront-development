<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Sitebuilder\Services;

use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use SandwaveIo\BaseKit\BaseKit;
use Tests\TestCase;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Sitebuilder\Models\BasekitContext;
use Waterfront\Domain\Provision\Sitebuilder\Models\SitebuilderDeployment;
use Waterfront\Domain\Provision\Sitebuilder\Repositories\BasekitContextRepository;
use Waterfront\Domain\Provision\Sitebuilder\Repositories\SitebuilderDeploymentRepository;
use Waterfront\Domain\Provision\Sitebuilder\Requests\RollbackBasekitDeploymentsFromMigrationRequest;
use Waterfront\Domain\Provision\Sitebuilder\Services\DeleteBasekitService;

#[CoversClass(DeleteBasekitService::class)]
class DeleteBasekitServiceTest extends TestCase
{
    private BasekitContextRepository&MockObject $baseKitContextRepository;

    private SitebuilderDeploymentRepository&MockObject $sitebuilderDeploymentRepository;

    private DeleteBasekitService $deleteBasekitService;

    protected function setUp(): void
    {
        parent::setUp();

        $mockBasekitClient = new BaseKit(
            username: 'user',
            password: 'pass',
            baseUrl: 'https://api.basekit.com',
            logger: $this->app->make(LoggerInterface::class),
        );

        $this->baseKitContextRepository = self::createMock(BasekitContextRepository::class);
        $this->sitebuilderDeploymentRepository = self::createMock(SitebuilderDeploymentRepository::class);

        $this->deleteBasekitService = new DeleteBasekitService(
            logger: self::createStub(LoggerInterface::class),
            baseKitContextRepository: $this->baseKitContextRepository,
            sitebuilderDeploymentRepository: $this->sitebuilderDeploymentRepository,
            basekitClient: $mockBasekitClient,
        );
    }

    #[Test]
    public function rollbackFromMigrationRemovesDeploymentAndContext(): void
    {
        $uuid = Uuid::uuid4();

        $request = new RollbackBasekitDeploymentsFromMigrationRequest(
            context: $uuid,
            tagUuid: $uuid,
        );
        $request->requestId = 1234;

        $basekitContext = new BasekitContext();
        $basekitContext->context_uuid = $uuid;
        $basekitContext->user_ref = 234;

        $this->baseKitContextRepository
            ->expects($this->once())
            ->method('findByContext')
            ->with($request->context)
            ->willReturn($basekitContext);

        $sitebuilderDeployment = new SitebuilderDeployment();
        $sitebuilderDeployment->uuid = $uuid;

        $this->sitebuilderDeploymentRepository
            ->expects($this->once())
            ->method('findByTag')
            ->with($request->tagUuid)
            ->willReturn($sitebuilderDeployment);

        $this->sitebuilderDeploymentRepository
            ->expects($this->once())
            ->method('deleteSitebuilderAndChildren')
            ->with($sitebuilderDeployment)
            ->willReturn(true);

        $this->sitebuilderDeploymentRepository
            ->expects($this->once())
            ->method('getSitebuilderDeploymentsByContext')
            ->with($basekitContext)
            ->willReturn(new Collection());

        $this->baseKitContextRepository
            ->expects($this->once())
            ->method('delete')
            ->with($basekitContext->context_uuid)
            ->willReturn(true);

        $result = $this->deleteBasekitService->rollbackFromMigration($request);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertSame($request, $result->provisionData);
    }

    #[Test]
    public function rollbackFailsWhenContextDoesNotExist(): void
    {
        $uuid = Uuid::uuid4();

        $request = new RollbackBasekitDeploymentsFromMigrationRequest(
            context: $uuid,
            tagUuid: $uuid,
        );
        $request->requestId = 999;

        $this->baseKitContextRepository
            ->expects($this->once())
            ->method('findByContext')
            ->with($request->context)
            ->willReturn(null);

        $this->sitebuilderDeploymentRepository->expects($this->never())->method('findByTag');

        $result = $this->deleteBasekitService->rollbackFromMigration($request);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertSame($request, $result->provisionData);
    }

    #[Test]
    public function rollbackFailsWhenDeploymentDoesNotExistForTag(): void
    {
        $uuid = Uuid::uuid4();

        $request = new RollbackBasekitDeploymentsFromMigrationRequest(
            context: $uuid,
            tagUuid: $uuid,
        );
        $request->requestId = 1001;

        $basekitContext = new BasekitContext();
        $basekitContext->context_uuid = $uuid;
        $basekitContext->user_ref = 555;

        $this->baseKitContextRepository
            ->expects($this->once())
            ->method('findByContext')
            ->with($request->context)
            ->willReturn($basekitContext);

        $this->sitebuilderDeploymentRepository
            ->expects($this->once())
            ->method('findByTag')
            ->with($request->tagUuid)
            ->willReturn(null);

        $this->sitebuilderDeploymentRepository->expects($this->never())->method('deleteSitebuilderAndChildren');

        $result = $this->deleteBasekitService->rollbackFromMigration($request);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertSame($request, $result->provisionData);
    }

    #[Test]
    public function rollbackFailsWhenBasekitDeleteThrows(): void
    {
        $uuid = Uuid::uuid4();

        $request = new RollbackBasekitDeploymentsFromMigrationRequest(
            context: $uuid,
            tagUuid: $uuid,
        );
        $request->requestId = 2002;

        $basekitContext = new BasekitContext();
        $basekitContext->context_uuid = $uuid;
        $basekitContext->user_ref = 777;

        $this->baseKitContextRepository
            ->expects($this->once())
            ->method('findByContext')
            ->with($request->context)
            ->willReturn($basekitContext);

        $sitebuilderDeployment = new SitebuilderDeployment();
        $sitebuilderDeployment->uuid = $uuid;

        $this->sitebuilderDeploymentRepository
            ->expects($this->once())
            ->method('findByTag')
            ->with($request->tagUuid)
            ->willReturn($sitebuilderDeployment);

        $this->sitebuilderDeploymentRepository
            ->expects($this->once())
            ->method('deleteSitebuilderAndChildren')
            ->with($sitebuilderDeployment)
            ->willThrowException(new RuntimeException('DB error'));

        $this->sitebuilderDeploymentRepository->expects($this->never())->method('getSitebuilderDeploymentsByContext');

        $this->baseKitContextRepository->expects($this->never())->method('delete');

        $result = $this->deleteBasekitService->rollbackFromMigration($request);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertSame($request, $result->provisionData);
        self::assertNotNull($result->exception);
    }
}
