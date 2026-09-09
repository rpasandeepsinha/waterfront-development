<?php

declare(strict_types=1);

namespace Tests\Domain\Backup\Jobs;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\ProductFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Backup\Jobs\SuspendBackupJob;
use Waterfront\Domain\Email\Actions\SendSubscriptionSuspendedMailAction;
use Waterfront\Domain\Provision\Backup\Results\BackupResult;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCategory;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\SubscriptionMetadataService;

#[CoversClass(SuspendBackupJob::class)]
class SuspendBackupJobTest extends IntegrationTestCase
{
    private Subscription $backupSubscription;

    public function setUp(): void
    {
        parent::setUp();

        $this->backupSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->backupAcronis()->createOne())
            ->createOne(
                [
                    'technical_status' => TechnicalStatus::OK->value,
                    'suspended_at' => null,
                ]
            );
    }

    #[Test]
    public function suspendBackupJobSuccessful(): void
    {
        $logger = self::createStub(LoggerInterface::class);
        $sendSubscriptionSuspendedMailAction = self::createMock(SendSubscriptionSuspendedMailAction::class);
        $provisionGateway = self::createMock(ProvisionGateway::class);

        $provisionGateway->expects(self::once())
            ->method('request')
            ->willReturn(
                new BackupResult(
                    provisionData: self::createStub(ProvisionRequestInterface::class),
                    provisionStatus: ProvisionStatus::SUCCESS,
                )
            );

        $sendSubscriptionSuspendedMailAction->expects(self::once())
            ->method('execute')
            ->with($this->backupSubscription);

        $suspendBackupJob = new SuspendBackupJob($this->backupSubscription);
        $suspendBackupJob->handle(
            logger: $logger,
            sendSubscriptionSuspendedMailAction: $sendSubscriptionSuspendedMailAction,
            provisionGateway: $provisionGateway,
            subscriptionMetadataService: self::resolve(SubscriptionMetadataService::class),
        );

        $this->backupSubscription->refresh();
        self::assertSame(TechnicalStatus::SUSPENDED->value, $this->backupSubscription->technical_status);
        self::assertNotNull($this->backupSubscription->suspended_at);
    }

    #[Test]
    public function suspendBackupJobFails(): void
    {
        $logger = self::createStub(LoggerInterface::class);
        $sendSubscriptionSuspendedMailAction = self::createMock(SendSubscriptionSuspendedMailAction::class);
        $provisionGateway = self::createMock(ProvisionGateway::class);

        $provisionGateway->expects(self::once())
            ->method('request')
            ->willReturn(
                new BackupResult(
                    provisionData: self::createStub(ProvisionRequestInterface::class),
                    provisionStatus: ProvisionStatus::FAILED,
                )
            );

        $sendSubscriptionSuspendedMailAction->expects(self::never())
            ->method('execute');

        $suspendBackupJob = new SuspendBackupJob($this->backupSubscription);
        $suspendBackupJob->handle(
            logger: $logger,
            sendSubscriptionSuspendedMailAction: $sendSubscriptionSuspendedMailAction,
            provisionGateway: $provisionGateway,
            subscriptionMetadataService: self::resolve(SubscriptionMetadataService::class),
        );

        $this->backupSubscription->refresh();
        self::assertSame(TechnicalStatus::SUSPENSION_FAILED->value, $this->backupSubscription->technical_status);
        self::assertNull($this->backupSubscription->suspended_at);
        self::assertDatabaseHas('subscription_categories', [
            'subscription_id' => $this->backupSubscription->id,
            'name' => SubscriptionCategory::SUSPENSION->value,
        ]);
    }
}
