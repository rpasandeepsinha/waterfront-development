<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\DomainNames\Coupling\Repositories;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Tests\Factories\DomainNameCoupleDeploymentFactory;
use Tests\Factories\ProvisioningRequestFactory;
use Tests\TestCase;
use Waterfront\Domain\Provision\DomainNames\Coupling\Models\DomainNameCoupleDeployment;
use Waterfront\Domain\Provision\DomainNames\Coupling\Repositories\DomainNameCoupleRepository;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(DomainNameCoupleRepository::class)]
class DomainNameCoupleRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private DomainNameCoupleRepository $repo;

    private LoggerInterface&MockInterface $mockLogger;

    protected function setUp(): void
    {
        parent::setUp();

        $loggerMock = $this->mock(LoggerInterface::class);

        $this->mockLogger = $loggerMock;
        $this->repo = new DomainNameCoupleRepository($this->mockLogger);
    }

    #[Test]
    public function createSucceeds(): void
    {
        $domain = 'test.nl';
        $coupleType = ProvisionType::HOSTING;
        $deploymentUuid = Uuid::uuid4();
        $requestId = ProvisioningRequestFactory::new()->hosting()->createOne()->id;

        $this->repo->create($domain, $coupleType, $deploymentUuid, $requestId);

        self::assertDatabaseHas(DomainNameCoupleDeployment::class, [
            'domain' => $domain,
            'couple_type' => $coupleType->value,
            'deployment_uuid' => $deploymentUuid->toString(),
            'origin_provisioning_request_id' => $requestId,
        ]);
    }

    #[Test]
    public function deleteSucceeds(): void
    {
        $domain = 'test.nl';
        $coupleType = ProvisionType::HOSTING;
        $deploymentUuid = Uuid::uuid4();

        DomainNameCoupleDeploymentFactory::new()->hostingCoupling()->createOne([
            'domain' => $domain,
            'couple_type' => $coupleType->value,
            'deployment_uuid' => $deploymentUuid->toString(),
        ]);

        $this->repo->delete($domain, ProvisionType::HOSTING, $deploymentUuid);

        self::assertSoftDeleted(DomainNameCoupleDeployment::class, [
            'domain' => $domain,
            'couple_type' => ProvisionType::HOSTING->value,
            'deployment_uuid' => $deploymentUuid->toString(),
        ]);
    }

    #[Test]
    public function deleteWarningWhenDeploymentDoesntExists(): void
    {
        $domain = 'test.nl';
        $deploymentUuid = Uuid::uuid4();

        $this->mockLogger
            ->shouldReceive('warning')
            ->once()
            ->withSomeOfArgs(
                'Attempted to delete non-existing domain name couple deployment',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::INTERNAL,
                    LoggingContextKeys::META => [
                        'couple_type' => ProvisionType::HOSTING,
                        'deployment_uuid' => $deploymentUuid->toString(),
                    ],
                ],
            );

        $this->repo->delete($domain, ProvisionType::HOSTING, $deploymentUuid);
    }
}
