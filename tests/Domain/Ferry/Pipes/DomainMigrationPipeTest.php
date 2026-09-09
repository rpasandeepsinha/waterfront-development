<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Pipes;

use Exception;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RealtimeRegister\Exceptions\ForbiddenException;
use RealtimeRegister\Exceptions\RealtimeRegisterClientException;
use Tests\Factories\DomainProviderBusinessUnitFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\DTO\PhoneDTO;
use Waterfront\Domain\Domains\DTO\DomainDetailsDTO;
use Waterfront\Domain\Domains\DTO\Handles;
use Waterfront\Domain\Domains\DTO\RetrieveResult;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Domains\Serializers\DomainSerializerFactory;
use Waterfront\Domain\Ferry\Dto\Validation\ValidationPayload;
use Waterfront\Domain\Ferry\Enums\MigrationValidation;
use Waterfront\Domain\Ferry\Enums\MigrationValidationPipes;
use Waterfront\Domain\Ferry\Pipes\DomainMigrationPipe;
use Waterfront\Domain\Ferry\Services\DomainAndSslMigrationService;
use Waterfront\Infra\OpenproviderClient\OpenproviderClient;
use Waterfront\Infra\RtrClient\Services\RtrService;

#[CoversClass(DomainMigrationPipe::class)]
class DomainMigrationPipeTest extends IntegrationTestCase
{
    private const string REFERENCE = 'unique_reference_for_adf';

    #[Test]
    public function validateFetchDomainNotFound(): void
    {
        $rtrService = $this->createMock(RtrService::class);

        $rtrService->expects(self::once())
            ->method('fetchDomain')
            ->with(self::equalTo('not-in-rtr.nl'))
            ->willThrowException(new RealtimeRegisterClientException('Domain not found'));

        $rtrService
            ->method('setHandle')
            ->willReturnSelf();

        $rtrService
            ->method('setClient')
            ->willReturnSelf();

        $this->app->bind(RtrService::class, fn () => $rtrService);

        $expectedPayload = [
            MigrationValidationPipes::DOMAIN_MIGRATION->value => [
                [
                    'id'      => MigrationValidation::DOMAIN_MIGRATION_FETCH_NOT_FOUND->value,
                    'message' => 'Unable to fetch Domain [not-in-rtr.nl] from backend: [realtime_register], error message: Domain not found',
                ],
                [
                    'id'      => MigrationValidation::DOMAIN_MIGRATION_PIPE_PASSED->value,
                    'message' => 'domain_migration reference: unique_reference_for_adf',
                ],
            ],
        ];

        $customer = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/domain_migration/incorrect_domain.php');

        $validationPayload = new ValidationPayload(
            validationReference: self::REFERENCE,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $domainMigrationPipe = self::resolve(DomainMigrationPipe::class);
        $processedPayload = $domainMigrationPipe->handle($validationPayload, fn ($result): ValidationPayload => $result);

        self::assertSame($expectedPayload, $processedPayload->validationResults);
    }

    #[Test]
    public function validateFetchDomainForbidden(): void
    {
        $rtrService = $this->createMock(RtrService::class);

        $rtrService->expects(self::once())
            ->method('fetchDomain')
            ->with(self::equalTo('not-in-rtr.nl'))
            ->willThrowException(new ForbiddenException('Forbidden'));

        $rtrService
            ->method('setHandle')
            ->willReturnSelf();

        $rtrService
            ->method('setClient')
            ->willReturnSelf();

        $this->app->bind(RtrService::class, fn () => $rtrService);

        $expectedPayload = [
            MigrationValidationPipes::DOMAIN_MIGRATION->value => [
                [
                    'id'      => MigrationValidation::DOMAIN_MIGRATION_FETCH_FORBIDDEN->value,
                    'message' => 'Unable to fetch Domain [not-in-rtr.nl] from backend: [realtime_register], error message: Forbidden',
                ],
                [
                    'id'      => MigrationValidation::DOMAIN_MIGRATION_PIPE_PASSED->value,
                    'message' => 'domain_migration reference: unique_reference_for_adf',
                ],
            ],
        ];

        $customer = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/domain_migration/incorrect_domain.php');

        $validationPayload = new ValidationPayload(
            validationReference: self::REFERENCE,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $domainMigrationPipe = self::resolve(DomainMigrationPipe::class);
        $processedPayload = $domainMigrationPipe->handle($validationPayload, fn ($result): ValidationPayload => $result);

        self::assertSame($expectedPayload, $processedPayload->validationResults);
    }

    #[Test]
    public function validateDomainAbnormalStatus(): void
    {
        $domainDetails = include __DIR__ . '/data/rtr/domainDetailsAbnormalStatus.php';
        $contactResponse = include __DIR__ . '/data/rtr/retrieveCustomerResponsInvalidPhone.php';
        $serializer = DomainSerializerFactory::getSerializer();
        $domainDetails = $serializer->denormalize($domainDetails, DomainDetailsDTO::class);

        $rtrService = $this->createMock(RtrService::class);
        $rtrMigrationService = $this->createMock(DomainAndSslMigrationService::class);

        $rtrService->expects(self::once())
            ->method('fetchDomain')
            ->with(self::equalTo('phone-not-correct-in-rtr.nl'))
            ->willReturn($domainDetails);

        $rtrService->expects(self::once())
            ->method('retrieveCustomerHandle')
            ->with(self::equalTo('testdummy'))
            ->willReturn($contactResponse);

        $phone = new PhoneDTO(Config::string('bu.phone_number'));
        $phoneCountryCode = $phone->getCountryCode();
        $phoneAreaCode = $phone->getAreaCode();
        $phoneSubscriberNumber = $phone->getNumber();

        $rtrMigrationService->expects(self::once())
            ->method('parseRemotePhone')
            ->with(self::equalTo($contactResponse))
            ->willReturn([$phoneCountryCode, $phoneAreaCode, $phoneSubscriberNumber]);

        $rtrService
            ->method('setHandle')
            ->willReturnSelf();

        $rtrService
            ->method('setClient')
            ->willReturnSelf();

        $this->app->bind(RtrService::class, fn () => $rtrService);
        $this->app->bind(DomainAndSslMigrationService::class, fn () => $rtrMigrationService);

        $expectedPayload = [
            MigrationValidationPipes::DOMAIN_MIGRATION->value => [
                [
                    'id' => MigrationValidation::DOMAIN_MIGRATION_ABNORMAL_STATUS->value,
                    'message' => 'Domain [phone-not-correct-in-rtr.nl] from backend: [realtime_register], has the following status: PENDING_DELETE',
                ],
                [
                    'id'      => MigrationValidation::DOMAIN_MIGRATION_PIPE_PASSED->value,
                    'message' => 'domain_migration reference: unique_reference_for_adf',
                ],
            ],
        ];

        $customer = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/domain_migration/incorrect_phone.php');

        $validationPayload = new ValidationPayload(
            validationReference: self::REFERENCE,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $domainMigrationPipe = Container::getInstance()->make(DomainMigrationPipe::class);
        $processedPayload = $domainMigrationPipe->handle($validationPayload, fn ($result): ValidationPayload => $result);

        self::assertSame($expectedPayload, $processedPayload->validationResults);
    }

    #[Test]
    public function validateFetchHandleRtrFailed(): void
    {
        $domainDetails = include __DIR__ . '/data/rtr/domainDetailsValid.php';
        $serializer = DomainSerializerFactory::getSerializer();
        $domainDetails = $serializer->denormalize($domainDetails, DomainDetailsDTO::class);

        $rtrService = $this->createMock(RtrService::class);

        $rtrService->expects(self::once())
            ->method('fetchDomain')
            ->with(self::equalTo('handle-not-in-rtr.nl'))
            ->willReturn($domainDetails);

        $rtrService->expects(self::once())
            ->method('retrieveCustomerHandle')
            ->with(self::equalTo('testdummy'))
            ->willThrowException(new RealtimeRegisterClientException('bla'));

        $rtrService
            ->method('setHandle')
            ->willReturnSelf();

        $rtrService
            ->method('setClient')
            ->willReturnSelf();

        $this->app->bind(RtrService::class, fn () => $rtrService);

        $expectedPayload = [
            MigrationValidationPipes::DOMAIN_MIGRATION->value => [
                [
                    'id'      => MigrationValidation::DOMAIN_MIGRATION_FETCH_HANDLE_FAILED->value,
                    'message' => 'Unable to fetch contact handle for Domain [handle-not-in-rtr.nl] from backend [realtime_register] message: bla',
                ],
                [
                    'id'      => MigrationValidation::DOMAIN_MIGRATION_PIPE_PASSED->value,
                    'message' => 'domain_migration reference: unique_reference_for_adf',
                ],
            ],
        ];

        $customer = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/domain_migration/incorrect_handle_rtr.php');

        $validationPayload = new ValidationPayload(
            validationReference: self::REFERENCE,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $domainMigrationPipe = Container::getInstance()->make(DomainMigrationPipe::class);
        $processedPayload = $domainMigrationPipe->handle($validationPayload, fn ($result): ValidationPayload => $result);

        self::assertSame($expectedPayload, $processedPayload->validationResults);
    }

    #[Test]
    public function validateFetchHandleOpenproviderFailed(): void
    {
        $mockOpenProvider = self::createMock(OpenproviderClient::class);
        $domainRetrieveResult = new RetrieveResult();
        $domainRetrieveResult->setStatus(DomainStatus::ACTIVE->value);
        $domainRetrieveResult->setHandles(new Handles('AB-1234'));

        $mockOpenProvider->expects(self::once())->method('retrieveDomain')
            ->willReturn($domainRetrieveResult);

        $mockOpenProvider->expects(self::once())->method('getCustomerHandle')
            ->willThrowException(new Exception('bla'));

        $this->app->instance(OpenproviderClient::class, $mockOpenProvider);

        $customer = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/domain_migration/incorrect_handle_op.php');

        $validationPayload = new ValidationPayload(
            validationReference: self::REFERENCE,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $domainMigrationPipe = Container::getInstance()->make(DomainMigrationPipe::class);
        $processedPayload = $domainMigrationPipe->handle($validationPayload, fn ($result): ValidationPayload => $result);

        self::assertSame(
            [
                MigrationValidationPipes::DOMAIN_MIGRATION->value => [
                    [
                        'id'      => MigrationValidation::DOMAIN_MIGRATION_FETCH_HANDLE_FAILED->value,
                        'message' => 'Unable to fetch contact handle for Domain [handle-not-in-op.nl] from backend [openprovider] message: bla',
                    ],
                    [
                        'id'      => MigrationValidation::DOMAIN_MIGRATION_PIPE_PASSED->value,
                        'message' => 'domain_migration reference: unique_reference_for_adf',
                    ],
                ],
            ],
            $processedPayload->validationResults,
        );
    }

    #[Test]
    public function validateParseRemotePhoneFailed(): void
    {
        $domainDetails = include __DIR__ . '/data/rtr/domainDetailsValid.php';
        $contactResponse = include __DIR__ . '/data/rtr/retrieveCustomerResponsInvalidPhone.php';
        $serializer = DomainSerializerFactory::getSerializer();
        $domainDetails = $serializer->denormalize($domainDetails, DomainDetailsDTO::class);

        $rtrService = $this->createMock(RtrService::class);
        $rtrMigrationService = $this->createMock(DomainAndSslMigrationService::class);

        $rtrService->expects(self::once())
            ->method('fetchDomain')
            ->with(self::equalTo('phone-not-correct-in-rtr.nl'))
            ->willReturn($domainDetails);

        $rtrService->expects(self::once())
            ->method('retrieveCustomerHandle')
            ->with(self::equalTo('testdummy'))
            ->willReturn($contactResponse);

        $phone = new PhoneDTO(Config::string('bu.phone_number'));
        $phoneCountryCode = $phone->getCountryCode();
        $phoneAreaCode = $phone->getAreaCode();
        $phoneSubscriberNumber = $phone->getNumber();

        $rtrMigrationService->expects(self::once())
            ->method('parseRemotePhone')
            ->with(self::equalTo($contactResponse))
            ->willReturn([$phoneCountryCode, $phoneAreaCode, $phoneSubscriberNumber]);

        $rtrService
            ->method('setHandle')
            ->willReturnSelf();

        $rtrService
            ->method('setClient')
            ->willReturnSelf();

        $this->app->bind(RtrService::class, fn () => $rtrService);
        $this->app->bind(DomainAndSslMigrationService::class, fn () => $rtrMigrationService);

        $expectedPayload = [
            MigrationValidationPipes::DOMAIN_MIGRATION->value => [
                [
                    'id'      => MigrationValidation::DOMAIN_MIGRATION_PIPE_PASSED->value,
                    'message' => 'domain_migration reference: unique_reference_for_adf',
                ],
            ],
        ];

        $customer = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/domain_migration/incorrect_phone.php');

        $validationPayload = new ValidationPayload(
            validationReference: self::REFERENCE,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $domainMigrationPipe = Container::getInstance()->make(DomainMigrationPipe::class);
        $processedPayload = $domainMigrationPipe->handle($validationPayload, fn ($result): ValidationPayload => $result);

        self::assertSame($expectedPayload, $processedPayload->validationResults);
    }

    #[Test]
    public function validateFailedDomainDataNotArray(): void
    {
        $customer = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/domain_migration/domain_data_not_array.php');

        $validationPayload = new ValidationPayload(
            validationReference: self::REFERENCE,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $domainMigrationPipe = self::resolve(DomainMigrationPipe::class);
        $processedPayload = $domainMigrationPipe->handle($validationPayload, fn ($result): ValidationPayload => $result);

        self::assertSame(
            [
                MigrationValidationPipes::DOMAIN_MIGRATION->value => [
                    [
                        'id' => 'laravel_validation',
                        'message' => [
                            '0.domain_data' => [
                                'Dit veld moet een array zijn.',
                            ],
                            '0.domain_data.reference_dns_template_id' => [
                                'Dit veld is verplicht wanneer 0.domain_data aanwezig is.',
                            ],
                        ],
                    ],
                    [
                        'id' => 'domain_migration_passed',
                        'message' => 'domain_migration reference: unique_reference_for_adf',
                    ],
                ],
            ],
            $processedPayload->validationResults
        );
    }

    #[Test]
    public function validateFailedDnsTemplateNotInCustomer(): void
    {
        $customer = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/domain_migration/dns_template_not_in_customer.php');

        $validationPayload = new ValidationPayload(
            validationReference: self::REFERENCE,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $domainMigrationPipe = Container::getInstance()->make(DomainMigrationPipe::class);
        $processedPayload = $domainMigrationPipe->handle($validationPayload, fn ($result): ValidationPayload => $result);

        self::assertSame(
            [
                MigrationValidationPipes::DOMAIN_MIGRATION->value => [
                    [
                        'id' => 'laravel_validation',
                        'message' => [
                            '0.dns_template_reference_id' => [
                                'The provided reference DNS template id: not_exists_in_customer cannot be found in the customer payload.',
                            ],
                        ],
                    ],
                    [
                        'id' => 'domain_migration_passed',
                        'message' => 'domain_migration reference: unique_reference_for_adf',
                    ],
                ],
            ],
            $processedPayload->validationResults
        );
    }

    #[Test]
    public function validateFailedDomainBusinessUnitDoesntExists(): void
    {
        $expectedPayload = [
            MigrationValidationPipes::DOMAIN_MIGRATION->value => [
                [
                    'id'      => MigrationValidation::DEFAULT_VALIDATION->value,
                    'message' => [
                        '0.reference_domain_provider_business_unit_slug' => [
                            'Het geselecteerde veld is ongeldig.',
                        ],
                    ],
                ],
                [
                    'id'      => MigrationValidation::DOMAIN_MIGRATION_PIPE_PASSED->value,
                    'message' => 'domain_migration reference: unique_reference_for_adf',
                ],
            ],
        ];

        $customer = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/domain_migration/argeweb_bu_domain.php');

        $validationPayload = new ValidationPayload(
            validationReference: self::REFERENCE,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $domainMigrationPipe = self::resolve(DomainMigrationPipe::class);
        $processedPayload = $domainMigrationPipe->handle($validationPayload, fn ($result): ValidationPayload => $result);

        self::assertSame($expectedPayload, $processedPayload->validationResults);
    }

    #[Test]
    public function validateFailedDomainBusinessUnitsDoesntHaveProviderCredentials(): void
    {
        $businessUnit = DomainProviderBusinessUnitFactory::new()
            ->argeweb()
            ->createOne();

        $expectedPayload = [
            MigrationValidationPipes::DOMAIN_MIGRATION->value => [
                [
                    'id'      => MigrationValidation::DOMAIN_MIGRATION_DRIVER_CREDENTIALS_FAILED->value,
                    'message' => 'No credentials set for [realtime_register] with business unit [argeweb], exception: The given business unit slug [argeweb] does not have credentials for the given provider [realtime_register]. Please ensure that the business unit & credentials exists and is correctly configured.',
                ],
                [
                    'id'      => MigrationValidation::DOMAIN_MIGRATION_PIPE_PASSED->value,
                    'message' => 'domain_migration reference: unique_reference_for_adf',
                ],
            ],
        ];

        $customer = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/domain_migration/argeweb_bu_domain.php');

        $validationPayload = new ValidationPayload(
            validationReference: self::REFERENCE,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $domainMigrationPipe = self::resolve(DomainMigrationPipe::class);
        $processedPayload = $domainMigrationPipe->handle($validationPayload, fn ($result): ValidationPayload => $result);

        self::assertSame($expectedPayload, $processedPayload->validationResults);
    }
}
