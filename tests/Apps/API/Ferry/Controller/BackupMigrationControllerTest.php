<?php

declare(strict_types=1);

namespace Tests\Apps\API\Ferry\Controller;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\Factories\AcronisProviderFactory;
use Tests\Factories\CustomerFactory;
use Tests\Factories\MigratedCustomersFactory;
use Tests\Factories\MigratedSubscriptionsFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Ferry\Controllers\BackupMigrationController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Ferry\Exceptions\NotEligibleForMigrationException;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Provision\Backup\Acronis\Models\AcronisProvider;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\AcronisClient\Clients\AcronisClient;
use Waterfront\Infra\AcronisClient\Clients\AcronisGenericClient;
use Waterfront\Infra\AcronisClient\Clients\AcronisOfferingItemsClient;
use Waterfront\Infra\AcronisClient\Clients\AcronisTenantClient;
use Waterfront\Infra\AcronisClient\Clients\AcronisUserClient;
use Waterfront\Infra\AcronisClient\DTO\Applications\ApplicationsList;
use Waterfront\Infra\AcronisClient\DTO\OfferingItems\OfferingItems;
use Waterfront\Infra\AcronisClient\DTO\Responses\Users\OneTimeToken;
use Waterfront\Infra\AcronisClient\Factories\AcronisClientFactory;

#[CoversClass(BackupMigrationController::class)]
class BackupMigrationControllerTest extends IntegrationTestCase
{
    private Customer $customer;

    private Subscription $validBackupSubscription;

    private Subscription $invalidBackupSubscription;

    private AcronisProvider $acronisProvider;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();

        $this->customer = CustomerFactory::new()->createOne();

        $backupGroup = ProductGroupFactory::new()->backup()->createOne();
        $backupProduct = ProductFactory::new()->backupAcronis($backupGroup)->createOne();

        $this->validBackupSubscription = SubscriptionFactory::new()
            ->for($this->customer)
            ->for($backupProduct)
            ->technicalStatusOk()
            ->createOne();

        $this->invalidBackupSubscription = SubscriptionFactory::new()
            ->for($this->customer)
            ->for($backupProduct)
            ->administrativeStatusExpired()
            ->technicalStatusOk()
            ->createOne();

        $migratedValid = MigratedSubscriptionsFactory::new()->createOne([
            'reference_subscription_id' => 'backup_sub_valid_1337',
        ]);
        $migratedInvalid = MigratedSubscriptionsFactory::new()->createOne([
            'reference_subscription_id' => 'backup_sub_invalid_1338',
        ]);

        $this->validBackupSubscription->migratedSubscriptions()->attach($migratedValid);
        $this->invalidBackupSubscription->migratedSubscriptions()->attach($migratedInvalid);

        $migrationCustomer = MigratedCustomersFactory::new()->createOne();
        $migrationCustomer->migratedSubscriptions()->attach($migratedValid);
        $migrationCustomer->migratedSubscriptions()->attach($migratedInvalid);
        $migrationCustomer->customers()->attach($this->customer);

        $this->acronisProvider = AcronisProviderFactory::new()->default()->createOne();
    }

    #[Test]
    public function migrateBackupsWithMixedValidAndInvalidSubscriptions(): void
    {
        $userUuid = '66666666-7777-8888-9999-aaaaaaaaaaaa';
        $tenantUuid = '11111111-2222-3333-4444-555555555555';

        $postData = [
            [
                'reference_subscription_id' => 'backup_sub_valid_1337',
                'backup_data' => [
                    'bu_tenant_uuid' => $this->acronisProvider->tenant_uuid,
                    'customer_tenant_uuid' => $tenantUuid,
                    'user_uuid' => $userUuid,
                ],
            ],
            [
                'reference_subscription_id' => 'backup_sub_invalid_1338',
                'backup_data' => [
                    'bu_tenant_uuid' => $this->acronisProvider->tenant_uuid,
                    'customer_tenant_uuid' => 'bbbbbbbb-cccc-dddd-eeee-ffffffffffff',
                    'user_uuid' => '12121212-3434-5656-7878-909090909090',
                ],
            ],
        ];

        $backupGenericClient = $this->createMock(AcronisGenericClient::class);
        $backupGenericClient->expects(self::atLeastOnce())->method('listApplications')->willReturn(new ApplicationsList(items: []));
        $backupOfferingClient = $this->createMock(AcronisOfferingItemsClient::class);
        $backupOfferingClient->expects(self::atLeastOnce())->method('get')->willReturn(new OfferingItems(checkUsage: false, offeringItems: []));
        $backupUserClient = $this->createMock(AcronisUserClient::class);
        $backupUserClient->expects(self::atLeastOnce())->method('getSso')->willReturn(new OneTimeToken(ott: 'test'));

        $acronisClientFactory = $this->createMock(AcronisClientFactory::class);
        $acronisClientFactory->expects(self::atLeastOnce())->method('create')->willReturn(new AcronisClient(
            tenantId: $this->acronisProvider->tenant_uuid,
            userClient: $backupUserClient,
            offeringItemsClient: $backupOfferingClient,
            tenantClient: $this->createStub(AcronisTenantClient::class),
            genericClient: $backupGenericClient
        ));

        $this->app->bind(AcronisClientFactory::class, fn () =>  $acronisClientFactory);

        $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.migrate_backups', ['customer' => $this->customer->id]),
                $postData,
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                ]
            )
            ->assertStatus(Response::HTTP_MULTI_STATUS)
            ->assertExactJson([
                'failures' => [
                    [
                        'message' => 'Backup migration step not allowed for subscription: ' . NotEligibleForMigrationException::administrativeStatusIncorrect(AdministrativeStatus::EXPIRED->value)->getMessage(),
                        'parameters' => [
                            'customerId' => $this->customer->id,
                            'subscriptionId' => $this->invalidBackupSubscription->id,
                        ],
                        'baseParameters' => [],
                    ],
                ],
                'success' => [
                    [
                        'baseParameters' => [],
                        'message' => 'Created jobs to migrate backups for every eligible subscription',
                        'parameters' => [
                            'customerId' => $this->customer->id,
                            'subscriptionIds' => (string) $this->validBackupSubscription->id,
                        ],
                    ],
                ],
            ]);

        $this->validBackupSubscription->refresh();
        $this->invalidBackupSubscription->refresh();

        $createdDeployment = $this->validBackupSubscription->provisionBackupDeployment?->acronisBackupDeployment;

        self::assertNotNull($createdDeployment);

        self::assertSame($this->acronisProvider->id, $createdDeployment->acronis_provider_id);
        self::assertSame($userUuid, $createdDeployment->user_uuid->toString());
        self::assertSame($tenantUuid, $createdDeployment->tenant_uuid->toString());

        self::assertSame(TechnicalStatus::OK->value, $this->validBackupSubscription->technical_status);
        self::assertSame(TechnicalStatus::OK->value, $this->invalidBackupSubscription->technical_status);

        self::assertSame([], Invoice::all()->toArray(), 'Technical migration should not create any invoices');
    }
}
