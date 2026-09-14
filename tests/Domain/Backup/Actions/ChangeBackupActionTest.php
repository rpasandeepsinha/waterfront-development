<?php

declare(strict_types=1);

namespace Tests\Domain\Backup\Actions;

use Carbon\CarbonImmutable;
use Exception;
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
use Tests\IntegrationTestCase;
use Waterfront\Domain\Backup\Actions\ChangeBackupAction;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Repositories\BackupProductSpecRepository;
use Waterfront\Domain\Provision\Backup\Requests\UpdateBackupRequest;
use Waterfront\Domain\Provision\Backup\Results\BackupUpdateResult;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Subscriptions\DTO\SubscriptionChangeResult;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionChangeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Models\SubscriptionChange;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(ChangeBackupAction::class)]
class ChangeBackupActionTest extends IntegrationTestCase
{
    private Subscription $subscription;

    private ChangeBackupAction $changeBackupAction;

    private SubscriptionChange $subscriptionChange;

    private LoggerInterface&MockObject $logger;

    private ProvisionGateway&MockObject $gateway;

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
        $this->gateway = self::createMock(ProvisionGateway::class);
        $this->changeBackupAction = new ChangeBackupAction(
            $this->gateway,
            $this->logger,
            self::resolve(BackupProductSpecRepository::class),
        );
    }

    #[Test]
    public function changeSubscriptionSuccessful(): void
    {
        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                sprintf(
                    'Changing subscription {subscription.uuid} from product %s to product %s',
                    $this->subscriptionChange->fromProduct->slug,
                    $this->subscriptionChange->toProduct->slug,
                ),
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $this->subscription->uuid,
                    LoggingContextKeys::META => [
                        'from_product_slug' => $this->subscriptionChange->fromProduct->slug,
                        'to_product_slug' => $this->subscriptionChange->toProduct->slug,
                        'subscription_change_id' => $this->subscriptionChange->id,
                    ],
                ],
            );

        $mockResult = new BackupUpdateResult(
            provisionData: self::createStub(ProvisionRequestInterface::class),
            provisionStatus: ProvisionStatus::SUCCESS,
        );

        $this->gateway
            ->expects(self::once())
            ->method('request')
            ->with(
                self::callback(function (UpdateBackupRequest $request) {
                    self::assertSame($this->subscription->uuid, $request->tagUuid->toString());
                    self::assertSame(100.0, $request->cloudStorageInGb);
                    self::assertSame(100.0, $request->localStorageInGb);
                    self::assertSame(15, $request->mobileDevices);
                    self::assertSame(10, $request->workStations);
                    self::assertSame(20, $request->vms);
                    self::assertNull($request->servers);

                    return true;
                }),
            )
            ->willReturn($mockResult);

        $result = $this->changeBackupAction->execute($this->subscription, $this->subscriptionChange);

        $this->subscription->refresh();
        $this->subscriptionChange->refresh();

        self::assertSame(AdministrativeStatus::ACTIVE->value, $this->subscription->administrative_status);
        self::assertSame(TechnicalStatus::OK->value, $this->subscription->technical_status);
        self::assertSame(SubscriptionChangeStatus::COMPLETED, $this->subscriptionChange->status);
        self::assertSame(SubscriptionChangeResult::STATUS_OK, $result->status);
    }

    #[Test]
    public function changeSubscriptionFailed(): void
    {
        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                sprintf(
                    'Changing subscription {subscription.uuid} from product %s to product %s',
                    $this->subscriptionChange->fromProduct->slug,
                    $this->subscriptionChange->toProduct->slug,
                ),
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $this->subscription->uuid,
                    LoggingContextKeys::META => [
                        'from_product_slug' => $this->subscriptionChange->fromProduct->slug,
                        'to_product_slug' => $this->subscriptionChange->toProduct->slug,
                        'subscription_change_id' => $this->subscriptionChange->id,
                    ],
                ],
            );

        $exception = new Exception('Something went wrong');
        $mockResult = new BackupUpdateResult(
            provisionData: self::createStub(ProvisionRequestInterface::class),
            provisionStatus: ProvisionStatus::FAILED,
            exception: $exception,
        );

        $this->gateway
            ->expects(self::once())
            ->method('request')
            ->with(
                self::callback(function (UpdateBackupRequest $request) {
                    self::assertSame($this->subscription->uuid, $request->tagUuid->toString());
                    self::assertSame(100.0, $request->cloudStorageInGb);
                    self::assertSame(100.0, $request->localStorageInGb);
                    self::assertSame(15, $request->mobileDevices);
                    self::assertSame(10, $request->workStations);
                    self::assertSame(20, $request->vms);
                    self::assertNull($request->servers);

                    return true;
                }),
            )
            ->willReturn($mockResult);

        $result = $this->changeBackupAction->execute($this->subscription, $this->subscriptionChange);

        $this->subscription->refresh();
        $this->subscriptionChange->refresh();

        self::assertSame(AdministrativeStatus::ACTIVE->value, $this->subscription->administrative_status);
        self::assertSame(TechnicalStatus::ERROR->value, $this->subscription->technical_status);
        self::assertSame(SubscriptionChangeStatus::EXECUTION_FAILED, $this->subscriptionChange->status);
        self::assertSame(SubscriptionChangeResult::STATUS_ERROR, $result->status);
        self::assertSame('Something went wrong', $this->subscriptionChange->failure_message);
        self::assertSame(0, $this->subscriptionChange->failure_code);
    }
}
