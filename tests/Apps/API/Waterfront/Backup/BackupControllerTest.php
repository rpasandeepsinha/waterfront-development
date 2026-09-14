<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Backup;

use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Saloon\Exceptions\SaloonException;
use Tests\Factories\AcronisBackupDeploymentFactory;
use Tests\Factories\BackupDeploymentFactory;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProvisioningRequestFactory;
use Tests\Factories\ProvisioningResultFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\BackupController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Provision\Backup\Requests\GetBackupSsoRequest;
use Waterfront\Domain\Provision\Backup\Requests\GetBackupUsageRequest;
use Waterfront\Domain\Provision\Backup\Results\BackupSsoResult;
use Waterfront\Domain\Provision\Backup\Results\BackupUsagesResult;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\AcronisClient\DTO\OfferingItems\Quota;
use Waterfront\Infra\AcronisClient\DTO\Responses\OfferingItems\OfferingItem;
use Waterfront\Infra\AcronisClient\DTO\Tenants\TenantUsage;
use Waterfront\Infra\AcronisClient\DTO\Tenants\TenantUsages;
use Waterfront\Infra\AcronisClient\Enums\OfferingItems\MeasurementUnit;
use Waterfront\Infra\AcronisClient\Enums\OfferingItems\OfferingItemStatus;
use Waterfront\Infra\AcronisClient\Enums\OfferingItems\OfferingItemType;
use Waterfront\Infra\AcronisClient\Enums\OfferingItems\UsageName;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Helpers\ByteHelper;

#[CoversClass(BackupController::class)]
class BackupControllerTest extends IntegrationTestCase
{
    private Customer $customer;

    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();
        $backupProductGroup = new ProductGroupFactory()->backup()->createOne();

        $acronisProduct = new ProductFactory()
            ->backupAcronis($backupProductGroup)
            ->createOne();
        $this->subscription = new SubscriptionFactory()
            ->for($acronisProduct)
            ->for($this->customer)
            ->createOne();
    }

    #[Test]
    public function getSsoUrl(): void
    {
        $acronisBackupDeployment = AcronisBackupDeploymentFactory::new()->for(
            BackupDeploymentFactory::new()->for(
                ProvisioningRequestFactory::new()
                    ->backup()
                    ->state([
                        'request_name' => ProvisionRequestName::CREATE_BACKUP,
                        'tag' => $this->subscription->uuid,
                    ])
                    ->has(ProvisioningResultFactory::new()->success(), 'result'),
                'request',
            ),
        )->createOne();

        $expectedSsoUrl = sprintf(
            '%s/idp/external-login#ott=%s&targetURI=%s',
            $acronisBackupDeployment->acronisProvider->endpoint,
            rawurlencode('0123456789abcdef'),
            $acronisBackupDeployment->acronisProvider->sso_target_url,
        );

        $mockResult = self::createStub(BackupSsoResult::class);
        $mockResult->ssoUrl = $expectedSsoUrl;
        $mockResult->provisionStatus = ProvisionStatus::SUCCESS;

        $gateway = self::createMock(ProvisionGateway::class);
        $this->app->bind(ProvisionGateway::class, fn (): ProvisionGateway => $gateway);

        $gateway
            ->expects(self::once())
            ->method('request')
            ->with(
                self::callback(
                    fn (GetBackupSsoRequest $request) => $request->tag->toString() === $this->subscription->uuid,
                ),
            )
            ->willReturn($mockResult);

        $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute('partners.backup.sso', $this->subscription->uuid),
            )
            ->assertOk()
            ->assertJsonFragment(['url' => $expectedSsoUrl]);
    }

    #[Test]
    public function getSsoUrlFailsExternal(): void
    {
        AcronisBackupDeploymentFactory::new()->for(
            BackupDeploymentFactory::new()->for(
                ProvisioningRequestFactory::new()
                    ->backup()
                    ->state([
                        'request_name' => ProvisionRequestName::CREATE_BACKUP,
                        'tag' => $this->subscription->uuid,
                    ])
                    ->has(ProvisioningResultFactory::new()->success(), 'result'),
                'request',
            ),
        )->createOne();

        $expectedException = new SaloonException('Something went wrong');
        $mockResult = self::createStub(BackupSsoResult::class);
        $mockResult->exception = $expectedException;
        $mockResult->ssoUrl = null;
        $mockResult->provisionStatus = ProvisionStatus::FAILED;

        $gateway = self::createMock(ProvisionGateway::class);
        $gateway
            ->expects(self::once())
            ->method('request')
            ->with(
                self::callback(
                    fn (GetBackupSsoRequest $request) => $request->tag->toString() === $this->subscription->uuid,
                ),
            )
            ->willReturn($mockResult);
        $this->app->bind(ProvisionGateway::class, fn (): ProvisionGateway => $gateway);

        $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute('partners.backup.sso', $this->subscription->uuid),
            )
            ->assertUnprocessable()
            ->assertJsonFragment([
                'message' => self::resolve(TranslatorInterface::class)
                    ->translate('backup.error.sso-could-not-be-generated'),
            ]);
    }

    #[Test]
    public function getUsage(): void
    {
        AcronisBackupDeploymentFactory::new()->for(
            BackupDeploymentFactory::new()->for(
                ProvisioningRequestFactory::new()
                    ->backup()
                    ->state([
                        'request_name' => ProvisionRequestName::CREATE_BACKUP,
                        'tag' => $this->subscription->uuid,
                    ])
                    ->has(ProvisioningResultFactory::new()->success(), 'result'),
                'request',
            ),
        )->createOne();

        $gigabyteInBytes = ByteHelper::BYTES_IN_GIB;

        $tenantUsages = new TenantUsages(items: [
            new TenantUsage(
                applicationId: Uuid::uuid4()->toString(),
                name: 'storage',
                usageName: UsageName::STORAGE,
                type: OfferingItemType::INFRA,
                measurementUnit: MeasurementUnit::BYTES,
                rangeStart: '2017-06-22T00:00:00',
                absoluteValue: 0,
                value: 20 * $gigabyteInBytes,
                infraId: null,
                edition: 'standard',
                offeringItem: new OfferingItem(
                    status: OfferingItemStatus::ACTIVE,
                    quota: new Quota(
                        value: 50 * $gigabyteInBytes,
                        overage: 0,
                        version: 1,
                    ),
                ),
            ),
        ]);

        $mockResult = new BackupUsagesResult(
            provisionData: new GetBackupUsageRequest(tagUuid: Uuid::fromString($this->subscription->uuid)),
            provisionStatus: ProvisionStatus::SUCCESS,
            tenantUsages: $tenantUsages,
        );

        $gateway = self::createMock(ProvisionGateway::class);
        $this->app->bind(ProvisionGateway::class, fn (): ProvisionGateway => $gateway);

        $gateway
            ->expects(self::once())
            ->method('request')
            ->with(
                self::callback(
                    fn (GetBackupUsageRequest $request) => $request->tagUuid->toString() === $this->subscription->uuid,
                ),
            )
            ->willReturn($mockResult);

        $this->actingAsCustomer($this->customer)
            ->getJson($this->generateRoute('partners.backup.usage', $this->subscription->uuid))
            ->assertOk()
            ->assertJson([
                'cloud_storage_gb_used' => 20,
                'cloud_storage_gb_total' => 50,
            ]);
    }

    #[Test]
    public function getUsageFailsExternal(): void
    {
        AcronisBackupDeploymentFactory::new()->for(
            BackupDeploymentFactory::new()->for(
                ProvisioningRequestFactory::new()
                    ->backup()
                    ->state([
                        'request_name' => ProvisionRequestName::CREATE_BACKUP,
                        'tag' => $this->subscription->uuid,
                    ])
                    ->has(ProvisioningResultFactory::new()->success(), 'result'),
                'request',
            ),
        )->createOne();

        $mockResult = new BackupUsagesResult(
            provisionData: new GetBackupUsageRequest(tagUuid: Uuid::fromString($this->subscription->uuid)),
            provisionStatus: ProvisionStatus::FAILED,
            exception: new Exception('Failed to fetch usage'),
        );

        $gateway = self::createMock(ProvisionGateway::class);
        $this->app->bind(ProvisionGateway::class, fn (): ProvisionGateway => $gateway);

        $gateway
            ->expects(self::once())
            ->method('request')
            ->with(
                self::callback(
                    fn (GetBackupUsageRequest $request) => $request->tagUuid->toString() === $this->subscription->uuid,
                ),
            )
            ->willReturn($mockResult);

        $this->actingAsCustomer($this->customer)
            ->getJson($this->generateRoute('partners.backup.usage', $this->subscription->uuid))
            ->assertUnprocessable()
            ->assertJsonFragment([
                'message' => self::resolve(TranslatorInterface::class)
                    ->translate('backup.error.usage-could-not-be-fetched'),
            ]);
    }

    #[Test]
    public function getUsageFailsUnexpectedResultType(): void
    {
        AcronisBackupDeploymentFactory::new()->for(
            BackupDeploymentFactory::new()->for(
                ProvisioningRequestFactory::new()
                    ->backup()
                    ->state([
                        'request_name' => ProvisionRequestName::CREATE_BACKUP,
                        'tag' => $this->subscription->uuid,
                    ])
                    ->has(ProvisioningResultFactory::new()->success(), 'result'),
                'request',
            ),
        )->createOne();

        $wrongResult = self::createStub(BackupSsoResult::class);
        $wrongResult->provisionStatus = ProvisionStatus::FAILED;
        $wrongResult->ssoUrl = null;

        $gateway = self::createMock(ProvisionGateway::class);
        $this->app->bind(ProvisionGateway::class, fn (): ProvisionGateway => $gateway);

        $gateway
            ->expects(self::once())
            ->method('request')
            ->with(
                self::callback(
                    fn (GetBackupUsageRequest $request) => $request->tagUuid->toString() === $this->subscription->uuid,
                ),
            )
            ->willReturn($wrongResult);

        $this->actingAsCustomer($this->customer)
            ->getJson($this->generateRoute('partners.backup.usage', $this->subscription->uuid))
            ->assertUnprocessable()
            ->assertJsonFragment([
                'message' => self::resolve(TranslatorInterface::class)
                    ->translate('backup.error.usage-could-not-be-fetched'),
            ]);
    }
}
