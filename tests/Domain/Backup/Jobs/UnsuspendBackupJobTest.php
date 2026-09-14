<?php

declare(strict_types=1);

namespace Tests\Domain\Backup\Jobs;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\ProductFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Backup\Jobs\UnsuspendBackupJob;
use Waterfront\Domain\Email\Actions\SendSubscriptionUnSuspendedMailAction;
use Waterfront\Domain\Provision\Backup\Results\BackupResult;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCategory;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\SubscriptionMetadataService;

#[CoversClass(UnsuspendBackupJob::class)]
class UnsuspendBackupJobTest extends IntegrationTestCase
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
                    'suspended_at' => CarbonImmutable::now(),
                ],
            );
    }

    #[Test]
    public function suspendBackupJobSuccessful(): void
    {
        $logger = self::createStub(LoggerInterface::class);
        $sendSubscriptionUnSuspendedMailAction = self::createMock(SendSubscriptionUnSuspendedMailAction::class);
        $provisionGateway = self::createMock(ProvisionGateway::class);

        $provisionGateway
            ->expects(self::once())
            ->method('request')
            ->willReturn(
                new BackupResult(
                    provisionData: self::createStub(ProvisionRequestInterface::class),
                    provisionStatus: ProvisionStatus::SUCCESS,
                ),
            );

        $sendSubscriptionUnSuspendedMailAction
            ->expects(self::once())
            ->method('execute')
            ->with($this->backupSubscription);

        $suspendBackupJob = new UnsuspendBackupJob($this->backupSubscription);
        $suspendBackupJob->handle(
            logger: $logger,
            sendSubscriptionUnSuspendedMailAction: $sendSubscriptionUnSuspendedMailAction,
            provisionGateway: $provisionGateway,
            subscriptionMetadataService: self::resolve(SubscriptionMetadataService::class),
        );

        $this->backupSubscription->refresh();
        self::assertSame(TechnicalStatus::OK->value, $this->backupSubscription->technical_status);
        self::assertNull($this->backupSubscription->suspended_at);
    }

    #[Test]
    public function suspendBackupJobFails(): void
    {
        $logger = self::createStub(LoggerInterface::class);
        $sendSubscriptionUnSuspendedMailAction = self::createMock(SendSubscriptionUnSuspendedMailAction::class);
        $provisionGateway = self::createMock(ProvisionGateway::class);

        $provisionGateway
            ->expects(self::once())
            ->method('request')
            ->willReturn(
                new BackupResult(
                    provisionData: self::createStub(ProvisionRequestInterface::class),
                    provisionStatus: ProvisionStatus::FAILED,
                ),
            );

        $sendSubscriptionUnSuspendedMailAction->expects(self::never())->method('execute');

        $suspendBackupJob = new UnsuspendBackupJob($this->backupSubscription);
        $suspendBackupJob->handle(
            logger: $logger,
            sendSubscriptionUnSuspendedMailAction: $sendSubscriptionUnSuspendedMailAction,
            provisionGateway: $provisionGateway,
            subscriptionMetadataService: self::resolve(SubscriptionMetadataService::class),
        );

        $this->backupSubscription->refresh();
        self::assertSame(TechnicalStatus::UNSUSPENSION_FAILED->value, $this->backupSubscription->technical_status);
        self::assertNotNull($this->backupSubscription->suspended_at);
        self::assertDatabaseHas('subscription_categories', [
            'subscription_id' => $this->backupSubscription->id,
            'name' => SubscriptionCategory::UNSUSPENSION->value,
        ]);
    }
}
