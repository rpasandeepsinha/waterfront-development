<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Jobs;

use Exception;
use Illuminate\Bus\Dispatcher;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\AcronisProviderFactory;
use Tests\Factories\CustomerFactory;
use Tests\Factories\MigratedCustomersFactory;
use Tests\Factories\MigratedSubscriptionsFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Backup\Services\BackupService;
use Waterfront\Domain\Ferry\Dto\Backup\BackupMigrationPayload;
use Waterfront\Domain\Ferry\Jobs\TechnicalBackupMigrationJob;
use Waterfront\Domain\Ferry\Services\AdfPayloadService;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\AcronisClient\DTO\OfferingItems\OfferingItems;
use Waterfront\Infra\AcronisClient\DTO\Responses\Users\OneTimeToken;

#[CoversClass(TechnicalBackupMigrationJob::class)]
#[AllowMockObjectsWithoutExpectations]
class TechnicalBackupMigrationJobTest extends IntegrationTestCase
{
    private Subscription $subscription;

    private BackupMigrationPayload $payload;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $customer = CustomerFactory::new()->createOne();
        $backupProduct = ProductFactory::new()
            ->backupAcronis(ProductGroupFactory::new()->backup()->createOne())
            ->createOne();

        $this->subscription = SubscriptionFactory::new()
            ->for($customer)
            ->for($backupProduct)
            ->technicalStatusOk()
            ->createOne();

        $migratedSubscription = MigratedSubscriptionsFactory::new()->createOne([
            'reference_subscription_id' => 'backup_ref_1337',
        ]);
        $this->subscription->migratedSubscriptions()->attach($migratedSubscription);

        $migratedCustomer = MigratedCustomersFactory::new()->createOne([
            'reference_name' => 'versio',
        ]);
        $migratedCustomer->customers()->attach($customer);
        $migratedCustomer->migratedSubscriptions()->attach($migratedSubscription);

        $provider = AcronisProviderFactory::new()->createOne([
            'tenant_uuid' => '190f3136-02e3-424d-8dab-51f3a4acca7e',
        ]);

        $this->payload = new BackupMigrationPayload(
            referenceSubscriptionId: 'backup_ref_1337',
            buTenantUuid: (string) $provider->tenant_uuid,
            customerTenantUuid: '0f2d2f3a-7c2b-4f6a-9d66-0b8e3c2d1a11',
            userUuid: '0f2d2f3a-7c2b-4f6a-9d66-0b8e3c2d1a12',
        );
    }

    #[DataProvider('backupMigrationExceptionProvider')]
    #[Test]
    public function backupMigration(
        bool $listApplicationsException,
        bool $offeringItemsException,
        bool $ssoException,
    ): void {
        $backupService = self::createMock(BackupService::class);

        if ($listApplicationsException) {
            $backupService->method('getApplicationListFromProvider')
                ->willThrowException(new Exception('listApplications exception'));
        } else {
            $backupService->method('getApplicationListFromProvider')
                ->willReturn(null);
        }

        if ($offeringItemsException) {
            $backupService->method('getOfferingItemsForProviderByTenant')
                ->willThrowException(new Exception('getOfferingItems exception'));
        } else {
            $backupService->method('getOfferingItemsForProviderByTenant')
                ->willReturn(new OfferingItems(checkUsage: false, offeringItems: []));
        }

        if ($ssoException) {
            $backupService->method('getSsoForProviderByUuids')
                ->willThrowException(new Exception('getSso exception'));
        } else {
            $backupService->method('getSsoForProviderByUuids')
                ->willReturn(new OneTimeToken(ott: 'test'));
        }

        $this->app->bind(BackupService::class, fn (): BackupService => $backupService);

        $job = new TechnicalBackupMigrationJob(
            subscription: $this->subscription,
            failedTechnicalStatus: TechnicalStatus::FAILED->value,
            backupTechnicalPayload: $this->payload,
        );

        $job->handle(
            self::resolve(AdfPayloadService::class),
            self::resolve(Dispatcher::class),
            self::resolve(LoggerInterface::class),
        );

        $this->subscription->refresh();

        self::assertSame(TechnicalStatus::OK->value, $this->subscription->technical_status);
    }

    /**
     * @return iterable<string, array{
     *   listApplicationsException: bool,
     *   offeringItemsException: bool,
     *   ssoException: bool
     * }>
     */
    public static function backupMigrationExceptionProvider(): iterable
    {
        yield 'listApplications exception' => [
            'listApplicationsException' => true,
            'offeringItemsException' => false,
            'ssoException' => false,
        ];

        yield 'getOfferingItems exception' => [
            'listApplicationsException' => false,
            'offeringItemsException' => true,
            'ssoException' => false,
        ];

        yield 'getSso exception' => [
            'listApplicationsException' => false,
            'offeringItemsException' => false,
            'ssoException' => true,
        ];
    }
}
