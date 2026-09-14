<?php

declare(strict_types=1);

namespace Tests\Domain\Backup\Jobs;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\SubscriptionChangeFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\SubscriptionMutationFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Backup\Actions\ChangeBackupAction;
use Waterfront\Domain\Backup\Jobs\UpgradeBackupJob;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Subscriptions\DTO\SubscriptionChangeResult;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionChangeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Exceptions\SubscriptionChangeException;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Models\SubscriptionChange;
use Waterfront\Domain\Subscriptions\Models\SubscriptionMutation;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(UpgradeBackupJob::class)]
class UpgradeBackupJobTest extends IntegrationTestCase
{
    private Subscription $subscription;

    private SubscriptionMutation $mutation;

    private SubscriptionChange $subscriptionChange;

    private LoggerInterface&MockObject $logger;

    private ChangeBackupAction&MockObject $changeBackupAction;

    public function setUp(): void
    {
        parent::setUp();

        $backupGroup = new ProductGroupFactory()->backup()->createOne();

        $newProduct = new ProductFactory()->for($backupGroup)->createOne([
            'name' => 'Backup 100',
            'slug' => 'backup-100',
        ]);

        new ProductSpecFactory()->for($newProduct)->createMany([
            [
                'name' => ProductSpecName::ACRONIS_CLOUD_STORAGE_GB->value,
                'value' => '100',
            ],
            [
                'name' => ProductSpecName::ACRONIS_LOCAL_STORAGE_GB->value,
                'value' => '100',
            ],
            [
                'name' => ProductSpecName::ACRONIS_MOBILE_DEVICES->value,
                'value' => '15',
            ],
            [
                'name' => ProductSpecName::ACRONIS_WORKSTATIONS->value,
                'value' => '10',
            ],
            [
                'name' => ProductSpecName::ACRONIS_VMS->value,
                'value' => '20',
            ],
        ]);

        $this->subscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->backupAcronis()->for($backupGroup))
            ->technicalStatusOk()
            ->createOne();

        $this->mutation = new SubscriptionMutationFactory()
            ->for($this->subscription)
            ->for($this->subscription->product)
            ->createOne();
        $this->subscriptionChange = new SubscriptionChangeFactory()->for($this->subscription)->createOne([
            'subscription_uuid' => $this->subscription->uuid,
            'from_product_uuid' => $this->subscription->product->uuid,
            'to_product_uuid' => $newProduct->uuid,
            'type' => ProductChangeType::UPGRADE,
            'status' => SubscriptionChangeStatus::REQUESTED,
            'completed_at' => null,
            'requested_at' => CarbonImmutable::now()->subYear(),
        ]);

        $this->logger = self::createMock(LoggerInterface::class);
        $this->changeBackupAction = self::createMock(ChangeBackupAction::class);
    }

    #[Test]
    public function upgradeSuccessful(): void
    {
        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Start upgrading backup with subscription: {subscription.uuid}',
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $this->subscription->uuid,
                    LoggingContextKeys::META => [
                        'subscription_change_id' => $this->subscriptionChange->id,
                        'subscription_mutation_id' => $this->mutation->id,
                    ],
                ],
            );

        $this->changeBackupAction
            ->expects(self::once())
            ->method('execute')
            ->with($this->subscription, $this->subscriptionChange)
            ->willReturn(new SubscriptionChangeResult(SubscriptionChangeResult::STATUS_OK));

        new UpgradeBackupJob(
            subscription: $this->subscription,
            subscriptionMutation: $this->mutation,
            subscriptionChange: $this->subscriptionChange,
        )->handle($this->changeBackupAction, $this->logger);

        $this->subscription->refresh();
        $this->subscriptionChange->refresh();

        self::assertSame(AdministrativeStatus::ACTIVE->value, $this->subscription->administrative_status);
        self::assertSame(TechnicalStatus::OK->value, $this->subscription->technical_status);
        self::assertSame(SubscriptionChangeStatus::COMPLETED, $this->subscriptionChange->status);
        self::assertNotNull($this->mutation->processed_technical_at);
    }

    #[Test]
    public function upgradeFailed(): void
    {
        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Start upgrading backup with subscription: {subscription.uuid}',
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $this->subscription->uuid,
                    LoggingContextKeys::META => [
                        'subscription_change_id' => $this->subscriptionChange->id,
                        'subscription_mutation_id' => $this->mutation->id,
                    ],
                ],
            );

        $this->changeBackupAction
            ->expects(self::once())
            ->method('execute')
            ->with($this->subscription, $this->subscriptionChange)
            ->willReturn(new SubscriptionChangeResult(SubscriptionChangeResult::STATUS_ERROR));

        $this->expectException(SubscriptionChangeException::class);

        new UpgradeBackupJob(
            subscription: $this->subscription,
            subscriptionMutation: $this->mutation,
            subscriptionChange: $this->subscriptionChange,
        )->handle($this->changeBackupAction, $this->logger);

        $this->subscription->refresh();
        $this->subscriptionChange->refresh();

        self::assertSame(AdministrativeStatus::ACTIVE->value, $this->subscription->administrative_status);
        self::assertSame(TechnicalStatus::ERROR->value, $this->subscription->technical_status);
        self::assertSame(SubscriptionChangeStatus::EXECUTION_FAILED, $this->subscriptionChange->status);
        self::assertNotNull($this->mutation->processed_technical_at);
    }

    #[Test]
    public function upgradeFailedPersistsFailureReasonOnSubscriptionChange(): void
    {
        $this->logger->expects(self::once())->method('info');

        $this->changeBackupAction
            ->expects(self::once())
            ->method('execute')
            ->with($this->subscription, $this->subscriptionChange)
            ->willReturn(new SubscriptionChangeResult(
                status: SubscriptionChangeResult::STATUS_ERROR,
                errorCode: 422,
                errorMessage: 'Backup upgrade failed',
            ));

        try {
            new UpgradeBackupJob(
                subscription: $this->subscription,
                subscriptionMutation: $this->mutation,
                subscriptionChange: $this->subscriptionChange,
            )->handle($this->changeBackupAction, $this->logger);
            self::fail('Expected a SubscriptionChangeException to be thrown');
        } catch (SubscriptionChangeException $exception) {
            self::assertStringContainsString('Upgrade Backup failed', $exception->getMessage());
        }

        $this->subscriptionChange->refresh();

        self::assertSame(SubscriptionChangeStatus::EXECUTION_FAILED, $this->subscriptionChange->status);
        self::assertSame(422, $this->subscriptionChange->failure_code);
        self::assertSame('Backup upgrade failed', $this->subscriptionChange->failure_message);
    }
}
