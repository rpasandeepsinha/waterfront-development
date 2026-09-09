<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Backup\Repositories;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\Factories\AcronisBackupDeploymentFactory;
use Tests\Factories\BackupDeploymentFactory;
use Tests\Factories\ProvisioningRequestFactory;
use Tests\Factories\ProvisioningResultFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Provision\Backup\Repositories\BackupDeploymentRepository;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;

#[CoversClass(BackupDeploymentRepository::class)]
class BackupDeploymentRepositoryTest extends IntegrationTestCase
{
    #[Test]
    public function findByTagX(): void
    {
        $tag = Uuid::uuid4();

        $success = AcronisBackupDeploymentFactory::new()
            ->for(
                BackupDeploymentFactory::new()
                    ->for(
                        ProvisioningRequestFactory::new()
                            ->backup()
                            ->state([
                                'request_name' => ProvisionRequestName::CREATE_BACKUP,
                                'tag' => $tag,
                            ])
                            ->has(ProvisioningResultFactory::new()->success(), 'result'),
                        'request'
                    )
            )
            ->createOne();

        AcronisBackupDeploymentFactory::new()
            ->for(
                BackupDeploymentFactory::new()
                    ->for(
                        ProvisioningRequestFactory::new()
                            ->backup()
                            ->state([
                                'request_name' => ProvisionRequestName::CREATE_BACKUP,
                                'tag' => $tag,
                            ])
                            ->has(ProvisioningResultFactory::new()->failed(), 'result'),
                        'request'
                    )
            )
            ->createOne();

        $backupDeploymentRepository = self::resolve(BackupDeploymentRepository::class);
        $receivedDeployment = $backupDeploymentRepository->findByTag($tag);

        self::assertNotNull($receivedDeployment);
        self::assertTrue($receivedDeployment->is($success->backupDeployment));
    }

    #[Test]
    public function findByTagOnlyReturnsSuccess(): void
    {
        $tag = Uuid::uuid4();

        AcronisBackupDeploymentFactory::new()
            ->for(
                BackupDeploymentFactory::new()
                    ->for(
                        ProvisioningRequestFactory::new()
                            ->backup()
                            ->state([
                                'request_name' => ProvisionRequestName::CREATE_BACKUP,
                                'tag' => $tag,
                            ])
                            ->has(ProvisioningResultFactory::new()->validationError(), 'result'),
                        'request'
                    )
            )
            ->createOne();

        AcronisBackupDeploymentFactory::new()
            ->for(
                BackupDeploymentFactory::new()
                    ->for(
                        ProvisioningRequestFactory::new()
                            ->backup()
                            ->state([
                                'request_name' => ProvisionRequestName::CREATE_BACKUP,
                                'tag' => $tag,
                            ])
                            ->has(ProvisioningResultFactory::new()->failed(), 'result'),
                        'request'
                    )
            )
            ->createOne();

        $backupDeploymentRepository = self::resolve(BackupDeploymentRepository::class);
        $receivedDeployment = $backupDeploymentRepository->findByTag($tag);

        self::assertNull($receivedDeployment);
    }
}
