<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Sitebuilder\Repositories;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\Factories\ProvisioningRequestFactory;
use Tests\Factories\ProvisioningResultFactory;
use Tests\Factories\SitebuilderContextBasekitFactory;
use Tests\Factories\SitebuilderDeploymentFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Sitebuilder\Repositories\SitebuilderDeploymentRepository;

#[CoversClass(SitebuilderDeploymentRepository::class)]
class SitebuilderDeploymentRepositoryTest extends IntegrationTestCase
{
    #[Test]
    public function findByTag(): void
    {
        $contextUuid = Uuid::uuid4();
        $tag = Uuid::uuid4();
        $userRef = 1337;

        SitebuilderContextBasekitFactory::new()->createOne([
            'context_uuid' => $contextUuid,
            'user_ref' => $userRef,
        ]);

        $success = SitebuilderDeploymentFactory::new()
            ->for(
                ProvisioningRequestFactory::new()
                    ->sitebuilder()
                    ->state([
                        'request_name' => ProvisionRequestName::CREATE_SITEBUILDER,
                        'context_uuid' => $contextUuid,
                        'tag' => $tag,
                    ])
                    ->has(ProvisioningResultFactory::new()->success(), 'result'),
                'request'
            )->createOne();

        SitebuilderDeploymentFactory::new()
            ->for(
                ProvisioningRequestFactory::new()
                    ->sitebuilder()
                    ->state([
                        'request_name' => ProvisionRequestName::CREATE_SITEBUILDER,
                        'context_uuid' => $contextUuid,
                        'tag' => $tag,
                    ])
                    ->has(ProvisioningResultFactory::new()->failed(), 'result'),
                'request'
            )->createOne();

        $repository = $this->app->make(SitebuilderDeploymentRepository::class);
        $receivedDeployment = $repository->findByTag($tag);

        self::assertNotNull($receivedDeployment);
        self::assertTrue($receivedDeployment->is($success));
    }

    #[Test]
    public function findByTagOnlyReturnsSuccess(): void
    {
        $contextUuid = Uuid::uuid4();
        $tag = Uuid::uuid4();
        $userRef = 1337;

        SitebuilderContextBasekitFactory::new()->createOne([
            'context_uuid' => $contextUuid,
            'user_ref' => $userRef,
        ]);

        SitebuilderDeploymentFactory::new()
            ->for(
                ProvisioningRequestFactory::new()
                    ->sitebuilder()
                    ->state([
                        'request_name' => ProvisionRequestName::CREATE_SITEBUILDER,
                        'context_uuid' => $contextUuid,
                        'tag' => $tag,
                    ])
                    ->has(ProvisioningResultFactory::new()->validationError(), 'result'),
                'request'
            )->createOne();

        SitebuilderDeploymentFactory::new()
            ->for(
                ProvisioningRequestFactory::new()
                    ->sitebuilder()
                    ->state([
                        'request_name' => ProvisionRequestName::CREATE_SITEBUILDER,
                        'context_uuid' => $contextUuid,
                        'tag' => $tag,
                    ])
                    ->has(ProvisioningResultFactory::new()->failed(), 'result'),
                'request'
            )->createOne();

        $repository = $this->app->make(SitebuilderDeploymentRepository::class);
        $receivedDeployment = $repository->findByTag($tag);

        self::assertNull($receivedDeployment);
    }
}
