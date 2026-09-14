<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Pipes;

use Exception;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Nonstandard\Uuid;
use Tests\Factories\AcronisProviderFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Ferry\Dto\Validation\ValidationPayload;
use Waterfront\Domain\Ferry\Enums\MigrationValidation;
use Waterfront\Domain\Ferry\Enums\MigrationValidationPipes;
use Waterfront\Domain\Ferry\Pipes\BackupPipe;
use Waterfront\Infra\AcronisClient\Clients\AcronisClient;
use Waterfront\Infra\AcronisClient\Clients\AcronisGenericClient;
use Waterfront\Infra\AcronisClient\Clients\AcronisOfferingItemsClient;
use Waterfront\Infra\AcronisClient\Clients\AcronisTenantClient;
use Waterfront\Infra\AcronisClient\Clients\AcronisUserClient;
use Waterfront\Infra\AcronisClient\DTO\Applications\ApplicationsList;
use Waterfront\Infra\AcronisClient\DTO\OfferingItems\OfferingItems;
use Waterfront\Infra\AcronisClient\DTO\Responses\Users\OneTimeToken;
use Waterfront\Infra\AcronisClient\Factories\AcronisClientFactory;

#[CoversClass(BackupPipe::class)]
#[AllowMockObjectsWithoutExpectations]
class BackupPipeTest extends IntegrationTestCase
{
    /**
     * @param array<string, mixed> $expectedValidationResults
     */
    #[DataProvider('backupPipeProvider')]
    #[Test]
    public function handleAddsBackupPipePassedValidationResult(
        bool $validationException,
        bool $buTenantException,
        bool $customerTenantException,
        bool $customerSsoException,
        array $expectedValidationResults,
    ): void {
        $customer = include __DIR__ . '/data/customer_correct.php';
        $subscriptions = include __DIR__ . '/data/subscriptions_correct.php';

        $validationReference = 'unique_reference_for_adf';

        $validationPayload = new ValidationPayload(
            validationReference: $validationReference,
            customer: $customer,
            subscriptions: $subscriptions,
        );

        $buTenantUuid = '190f3136-02e3-424d-8dab-51f3a4acca7e';

        if ($validationException) {
            $acronisProvider = AcronisProviderFactory::new()->createOne([
                'tenant_uuid' => Uuid::uuid4(),
                'client_secret' => 'test',
                'name' => 'testBu',
            ]);

            $acronisProvider->id = 555;
            $acronisProvider->save();
        } else {
            $acronisProvider = AcronisProviderFactory::new()->createOne([
                'tenant_uuid' => $buTenantUuid,
                'client_secret' => 'test',
                'name' => 'testBu',
            ]);
        }

        $acronisProvider->id = 555;
        $acronisProvider->save();

        $backupGenericClient = $this->createStub(AcronisGenericClient::class);
        if ($buTenantException) {
            $backupGenericClient
                ->method('listApplications')
                ->willThrowException(new Exception('listApplications exception'));
        } else {
            $backupGenericClient->method('listApplications')->willReturn(new ApplicationsList(items: []));
        }

        $backupOfferingClient = $this->createStub(AcronisOfferingItemsClient::class);
        if ($customerTenantException) {
            $backupOfferingClient->method('get')->willThrowException(new Exception('getOfferingItems exception'));
        } else {
            $backupOfferingClient->method('get')->willReturn(new OfferingItems(checkUsage: false, offeringItems: []));
        }

        $backupUserClient = $this->createStub(AcronisUserClient::class);
        if ($customerSsoException) {
            $backupUserClient->method('getSso')->willThrowException(new Exception('getUser exception'));
        } else {
            $backupUserClient->method('getSso')->willReturn(new OneTimeToken(ott: 'test'));
        }

        $acronisClientFactory = $this->createStub(AcronisClientFactory::class);
        $acronisClientFactory
            ->method('create')
            ->willReturn(new AcronisClient(
                tenantId: $acronisProvider->tenant_uuid,
                userClient: $backupUserClient,
                offeringItemsClient: $backupOfferingClient,
                tenantClient: $this->createStub(AcronisTenantClient::class),
                genericClient: $backupGenericClient,
            ));

        $this->app->bind(AcronisClientFactory::class, fn () => $acronisClientFactory);

        $backupPipe = self::resolve(BackupPipe::class);

        $result = $backupPipe->handle(
            $validationPayload,
            fn (ValidationPayload $validationPayload): ValidationPayload => $validationPayload,
        );

        self::assertSame(
            $expectedValidationResults,
            $result->validationResults,
        );
    }

    /**
     * @return iterable<string, mixed>
     */
    public static function backupPipeProvider(): iterable
    {
        yield 'Validation failed' => [
            'validationException' => true,
            'buTenantException' => false,
            'customerTenantException' => false,
            'customerSsoException' => false,
            'expectedValidationResults' => [
                MigrationValidationPipes::BACKUP->value => [
                    [
                        'id' => MigrationValidation::DEFAULT_VALIDATION->value,
                        'message' => [
                            '0.backup_data.bu_tenant_uuid' => [
                                'Het geselecteerde veld is ongeldig.',
                            ],
                        ],
                    ],
                    [
                        'id' => MigrationValidation::BACKUP_PIPE_PASSED->value,
                        'message' => 'backup_migration reference: unique_reference_for_adf',
                    ],
                ],
            ],
        ];

        yield 'listApplications exception' => [
            'validationException' => false,
            'buTenantException' => true,
            'customerTenantException' => false,
            'customerSsoException' => false,
            'expectedValidationResults' => [
                MigrationValidationPipes::BACKUP->value => [
                    [
                        'id' => MigrationValidation::BACKUP_MIGRATION_BU_TENANT_ERROR->value,
                        'message' => 'Error when testing Acronis Provider [555 (testBu)]: listApplications exception',
                    ],
                    [
                        'id' => MigrationValidation::BACKUP_PIPE_PASSED->value,
                        'message' => 'backup_migration reference: unique_reference_for_adf',
                    ],
                ],
            ],
        ];

        yield 'getOfferItermsForTenant exception' => [
            'validationException' => false,
            'buTenantException' => false,
            'customerTenantException' => true,
            'customerSsoException' => false,
            'expectedValidationResults' => [
                MigrationValidationPipes::BACKUP->value => [
                    [
                        'id' => MigrationValidation::BACKUP_MIGRATION_CUSTOMER_TENANT_ERROR->value,
                        'message' => 'Error fetching Acronis offering items for tenant [0f2d2f3a-7c2b-4f6a-9d66-0b8e3c2d1a11] via provider [555 (testBu)]: getOfferingItems exception',
                    ],
                    [
                        'id' => MigrationValidation::BACKUP_PIPE_PASSED->value,
                        'message' => 'backup_migration reference: unique_reference_for_adf',
                    ],
                ],
            ],
        ];

        yield 'getSso exception' => [
            'validationException' => false,
            'buTenantException' => false,
            'customerTenantException' => false,
            'customerSsoException' => true,
            'expectedValidationResults' => [
                MigrationValidationPipes::BACKUP->value => [
                    [
                        'id' => MigrationValidation::BACKUP_MIGRATION_SSO_ERROR->value,
                        'message' => 'Error generating Acronis SSO link for user [0f2d2f3a-7c2b-4f6a-9d66-0b8e3c2d1a12] via provider [555 (testBu)]: getUser exception',
                    ],
                    [
                        'id' => MigrationValidation::BACKUP_PIPE_PASSED->value,
                        'message' => 'backup_migration reference: unique_reference_for_adf',
                    ],
                ],
            ],
        ];
    }
}
