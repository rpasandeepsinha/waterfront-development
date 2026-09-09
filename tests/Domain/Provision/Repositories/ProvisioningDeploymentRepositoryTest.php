<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Repositories;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\RedirectDeploymentFactory;
use Tests\TestCase;
use Waterfront\Domain\Provision\Repositories\ProvisioningDeploymentRepository;

#[CoversClass(ProvisioningDeploymentRepository::class)]
class ProvisioningDeploymentRepositoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function findDeploymentByRequestUuid(): void
    {
        $deployment = RedirectDeploymentFactory::new()->createOne();
        $request = $deployment->request;

        $repo = new ProvisioningDeploymentRepository();

        $foundDeployment = $repo->findDeploymentByRequestUuid($request->uuid);

        self::assertNotNull($foundDeployment);
        self::assertTrue($foundDeployment->is($deployment));
    }
}
