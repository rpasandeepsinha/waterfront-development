<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Services\ManualMigration;

use Illuminate\Pipeline\Pipeline;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Requests\ManualMigrationValidateRequest;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\DNS\ValueObjects\Fqdn;
use Waterfront\Domain\Ferry\Dto\ManualMigration\MigrationOption;
use Waterfront\Domain\Ferry\Dto\ManualMigration\ValidatedHosting;
use Waterfront\Domain\Ferry\Dto\Validation\ValidationErrorResult;
use Waterfront\Domain\Ferry\Dto\Validation\ValidationPayload;
use Waterfront\Domain\Ferry\Enums\ManualMigrationOption;
use Waterfront\Domain\Ferry\Enums\MigrationValidation;
use Waterfront\Domain\Ferry\Enums\MigrationValidationPipes;
use Waterfront\Domain\Ferry\Pipes\HostingMigrationPipe;
use Waterfront\Domain\Ferry\Pipes\MailOnlyMigrationPipe;
use Waterfront\Domain\Ferry\Pipes\SubscriptionPipe;
use Waterfront\Domain\Ferry\Services\ManualMigration\SubscriptionFormatter;
use Waterfront\Domain\Ferry\Services\ManualMigration\ValidationService;
use Waterfront\Infra\PowerDnsClient\Enums\PowerDnsZoneKind;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Exceptions\NotImplementedException;
use Waterfront\Support\Helpers\DnsHelper;

#[CoversClass(ValidationService::class)]
class ValidationServiceTest extends IntegrationTestCase
{
    #[Test]
    public function validateThatSubscriptionDataIsPassedToValidationPipe(): void
    {
        $product = new ProductFactory()->nlDomain()->createOne();
        new ProductPriceComponentFactory()
            ->prolongation()
            ->for($product)
            ->createOne();

        $httpRequest = ManualMigrationValidateRequest::create('', parameters: [
            'billing_period' => 12,
            'contract_period' => 12,
            'domain_name' => 'testdomain.nl',
            'start_date' => '2021-01-01',
            'next_contract_date' => '2022-01-01',
            'next_billing_date' => '2022-01-01',
            'product_uuid' => $product->uuid,
            'reference_product_id' => 'some-reference-product-id',
            'reference_subscription_id' => 'some-reference-product-id',
            'reference_customer_number' => '123',
        ]);

        $pipelineMock = self::createMock(Pipeline::class);
        $pipelineMock->expects(self::once())->method('send')->willReturn($pipelineMock);

        $pipelineMock->method('through')->willReturn($pipelineMock);
        $pipelineMock->method('via')->willReturn($pipelineMock);
        $pipelineMock->method('then')->willReturn(new ValidationPayload('', [], []));

        $service = new ValidationService(
            $pipelineMock,
            self::resolve(SubscriptionFormatter::class),
            self::createStub(DnsService::class),
            self::resolve(TranslatorInterface::class),
            self::createStub(DnsHelper::class),
        );

        $service->validate($httpRequest, new CustomerFactory()->createOne());
    }

    #[Test]
    public function validateThatSubscriptionDataIsPassedToValidationPipeWithHostingValues(): void
    {
        $product = new ProductFactory()->hostingBrons()->createOne();
        new ProductPriceComponentFactory()
            ->prolongation()
            ->for($product)
            ->createOne();

        $httpRequest = ManualMigrationValidateRequest::create('', parameters: [
            'billing_period' => 12,
            'contract_period' => 12,
            'domain_name' => 'testdomain.nl',
            'start_date' => '2021-01-01',
            'next_contract_date' => '2022-01-01',
            'next_billing_date' => '2022-01-01',
            'product_uuid' => $product->uuid,
            'reference_product_id' => 'some-reference-product-id',
            'reference_subscription_id' => 'some-reference-product-id',
            'reference_customer_number' => '123',
        ]);

        $pipelineMock = self::createMock(Pipeline::class);
        $pipelineMock->expects(self::once())->method('send')->willReturn($pipelineMock);

        $pipelineMock
            ->expects(self::once())
            ->method('through')
            ->with(
                self::equalToCanonicalizing([
                    SubscriptionPipe::class,
                    HostingMigrationPipe::class,
                    MailOnlyMigrationPipe::class,
                ]),
            )
            ->willReturn($pipelineMock);
        $pipelineMock->method('via')->willReturn($pipelineMock);
        $pipelineMock->method('then')->willReturn(new ValidationPayload('', [], []));

        $service = new ValidationService(
            $pipelineMock,
            self::resolve(SubscriptionFormatter::class),
            self::createStub(DnsService::class),
            self::resolve(TranslatorInterface::class),
            self::createStub(DnsHelper::class),
        );

        $validatedHosting = $service->validate($httpRequest, new CustomerFactory()->createOne());

        self::assertInstanceOf(ValidatedHosting::class, $validatedHosting);
    }

    #[Test]
    public function validateThrowsNotImplementedException(): void
    {
        $product = new ProductFactory()->vps()->createOne();
        new ProductPriceComponentFactory()
            ->prolongation()
            ->for($product)
            ->createOne();

        $httpRequest = ManualMigrationValidateRequest::create('', parameters: [
            'billing_period' => 12,
            'contract_period' => 12,
            'domain_name' => 'testdomain.nl',
            'start_date' => '2021-01-01',
            'next_contract_date' => '2022-01-01',
            'next_billing_date' => '2022-01-01',
            'product_uuid' => $product->uuid,
            'reference_product_id' => 'some-reference-product-id',
            'reference_subscription_id' => 'some-reference-product-id',
            'reference_customer_number' => '123',
        ]);

        $this->expectException(NotImplementedException::class);

        $pipelineMock = self::createMock(Pipeline::class);
        $pipelineMock->expects(self::never())->method('send');

        $service = new ValidationService(
            $pipelineMock,
            self::resolve(SubscriptionFormatter::class),
            self::createStub(DnsService::class),
            self::resolve(TranslatorInterface::class),
            self::createStub(DnsHelper::class),
        );

        $service->validate($httpRequest, new CustomerFactory()->createOne());
    }

    #[Test]
    public function validateDomain(): void
    {
        $ferryValidationError = new ValidationErrorResult(MigrationValidation::DNSSEC_ZONE_UNEXPECTED_EXCEPTION, []);
        $ferryValidationPayload = new ValidationPayload(
            '',
            [],
            [],
            [MigrationValidationPipes::DNSSEC_ENABLE->value => [$ferryValidationError->toArray()]],
        );

        $pipeline = self::createStub(Pipeline::class);
        $pipeline->method('then')->willReturn($ferryValidationPayload);

        $ferryValidationResults = [MigrationValidation::DNSSEC_ZONE_UNEXPECTED_EXCEPTION];

        $zone = new DnsZone(new Fqdn('example.com'));
        $zone->kind = PowerDnsZoneKind::MASTER->value;
        $dnsService = self::createStub(DnsService::class);
        $dnsService->method('getDnsZone')->willReturn($zone);

        $dnsResolver = self::createStub(DnsHelper::class);
        $dnsResolver->method('getNameServers')->willReturn(['ns1.example.com']);

        $validationService = new ValidationService(
            $pipeline,
            self::resolve(SubscriptionFormatter::class),
            $dnsService,
            self::resolve(TranslatorInterface::class),
            $dnsResolver,
        );

        $validatedDomain = $validationService->processDomain($ferryValidationResults, 'example.com');
        $options = array_merge(...array_values($validatedDomain->options));
        $options = array_map(fn (MigrationOption $o) => [
            'option' => $o->title,
            'available' => $o->optionAvailable,
        ], $options);

        self::assertFalse($validatedDomain->dnsSecEnabled);
        self::assertTrue($validatedDomain->zoneIsNative);
        self::assertTrue($validatedDomain->zoneInPowerDns);
        self::assertSame(['ns1.example.com'], $validatedDomain->nameservers);
        self::assertEqualsCanonicalizing(
            [
                ['option' => ManualMigrationOption::NAMESERVERS_DO_NOTHING, 'available' => true],
                ['option' => ManualMigrationOption::NAMESERVERS_UPDATE_NEW, 'available' => true],
                ['option' => ManualMigrationOption::DNSSEC_DO_NOTHING, 'available' => true],
                ['option' => ManualMigrationOption::DNSSEC_ENABLE, 'available' => true],
                ['option' => ManualMigrationOption::DNS_DO_NOTHING, 'available' => true],
                ['option' => ManualMigrationOption::DNS_UPDATE_NATIVE, 'available' => false],
                ['option' => ManualMigrationOption::DNS_DEFAULT_TEMPLATE, 'available' => true],
                ['option' => ManualMigrationOption::DNS_NEW_EMPTY, 'available' => true],
            ],
            $options,
        );
        self::assertSame(
            [MigrationValidation::DNSSEC_ZONE_UNEXPECTED_EXCEPTION->value],
            array_keys($validatedDomain->errors),
        );
    }

    #[Test]
    public function isZoneNative(): void
    {
        $mockedZone = new DnsZone(new Fqdn('example.com'));
        $mockedZone->kind = PowerDnsZoneKind::MASTER->value;

        $mockedZone2 = new DnsZone(new Fqdn('example2.com'));
        $mockedZone2->kind = PowerDnsZoneKind::NATIVE->value;

        $mockedZone3 = new DnsZone(new Fqdn('example3.com'));
        $mockedZone3->kind = PowerDnsZoneKind::SLAVE->value;

        $dnsService = self::createStub(DnsService::class);
        $dnsService->method('getDnsZone')->willReturn(
            $mockedZone,
            $mockedZone2,
            $mockedZone3,
        );
        $validationService = new ValidationService(
            self::resolve(PipeLine::class),
            self::resolve(SubscriptionFormatter::class),
            $dnsService,
            self::resolve(TranslatorInterface::class),
            self::resolve(DnsHelper::class),
        );

        self::assertTrue($validationService->isZoneNative('example.com'));
        self::assertTrue($validationService->isZoneNative('example2.com'));
        self::assertFalse($validationService->isZoneNative('example3.com'));
    }

    #[Test]
    public function filterImportantErrors(): void
    {
        $ferryValidationResults = [
            MigrationValidation::DNS_CONFIGURATION_ZONE_UNEXPECTED_EXCEPTION,
            MigrationValidation::RESELLER_HOSTING_PIPE_PASSED,
        ];
        $validationService = self::resolve(ValidationService::class);

        $errors = $validationService->filterImportantErrors($ferryValidationResults);

        self::assertSame(
            [MigrationValidation::DNS_CONFIGURATION_ZONE_UNEXPECTED_EXCEPTION->value],
            array_keys($errors),
        );
    }

    /**
     * @param array<mixed> $expectedOptions
     */
    #[Test]
    #[DataProvider('stateProvider')]
    public function calculateDomainSelectableOptions(
        bool $zoneInPowerDns,
        bool $domainInSupportedRegistry,
        bool $zoneIsNative,
        bool $dnsSecTldSupported,
        array $expectedOptions,
    ): void {
        $validationService = self::resolve(ValidationService::class);
        $options = $validationService->calculateDomainSelectableOptions(
            $zoneInPowerDns,
            $domainInSupportedRegistry,
            $zoneIsNative,
            $dnsSecTldSupported,
        );

        $options = array_merge(...array_values($options));
        $options = array_map(fn (MigrationOption $o) => [
            'option' => $o->title,
            'available' => $o->optionAvailable,
        ], $options);

        self::assertEqualsCanonicalizing($expectedOptions, $options);
    }

    /**
     * @return array<mixed>
     */
    public static function stateProvider(): array
    {
        return [
            [
                true,
                true,
                true,
                true,
                [
                    ['option' => ManualMigrationOption::NAMESERVERS_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::NAMESERVERS_UPDATE_NEW, 'available' => true],
                    ['option' => ManualMigrationOption::DNSSEC_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::DNSSEC_ENABLE, 'available' => true],
                    ['option' => ManualMigrationOption::DNS_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::DNS_UPDATE_NATIVE, 'available' => false],
                    ['option' => ManualMigrationOption::DNS_DEFAULT_TEMPLATE, 'available' => true],
                    ['option' => ManualMigrationOption::DNS_NEW_EMPTY, 'available' => true],
                ],
            ],
            [
                false,
                false,
                false,
                false,
                [
                    ['option' => ManualMigrationOption::NAMESERVERS_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::NAMESERVERS_UPDATE_NEW, 'available' => false],
                    ['option' => ManualMigrationOption::DNSSEC_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::DNSSEC_ENABLE, 'available' => false],
                    ['option' => ManualMigrationOption::DNS_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::DNS_UPDATE_NATIVE, 'available' => false],
                    ['option' => ManualMigrationOption::DNS_DEFAULT_TEMPLATE, 'available' => true],
                    ['option' => ManualMigrationOption::DNS_NEW_EMPTY, 'available' => true],
                ],
            ],
            [
                true,
                false,
                false,
                false,
                [
                    ['option' => ManualMigrationOption::NAMESERVERS_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::NAMESERVERS_UPDATE_NEW, 'available' => false],
                    ['option' => ManualMigrationOption::DNSSEC_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::DNSSEC_ENABLE, 'available' => false],
                    ['option' => ManualMigrationOption::DNS_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::DNS_UPDATE_NATIVE, 'available' => true],
                    ['option' => ManualMigrationOption::DNS_DEFAULT_TEMPLATE, 'available' => true],
                    ['option' => ManualMigrationOption::DNS_NEW_EMPTY, 'available' => true],
                ],
            ],
            [
                false,
                true,
                false,
                false,
                [
                    ['option' => ManualMigrationOption::NAMESERVERS_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::NAMESERVERS_UPDATE_NEW, 'available' => true],
                    ['option' => ManualMigrationOption::DNSSEC_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::DNSSEC_ENABLE, 'available' => false],
                    ['option' => ManualMigrationOption::DNS_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::DNS_UPDATE_NATIVE, 'available' => false],
                    ['option' => ManualMigrationOption::DNS_DEFAULT_TEMPLATE, 'available' => true],
                    ['option' => ManualMigrationOption::DNS_NEW_EMPTY, 'available' => true],
                ],
            ],
            [
                false,
                false,
                true,
                false,
                [
                    ['option' => ManualMigrationOption::NAMESERVERS_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::NAMESERVERS_UPDATE_NEW, 'available' => false],
                    ['option' => ManualMigrationOption::DNSSEC_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::DNSSEC_ENABLE, 'available' => false],
                    ['option' => ManualMigrationOption::DNS_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::DNS_UPDATE_NATIVE, 'available' => false],
                    ['option' => ManualMigrationOption::DNS_DEFAULT_TEMPLATE, 'available' => false],
                    ['option' => ManualMigrationOption::DNS_NEW_EMPTY, 'available' => false],
                ],
            ],
            [
                false,
                false,
                false,
                true,
                [
                    ['option' => ManualMigrationOption::NAMESERVERS_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::NAMESERVERS_UPDATE_NEW, 'available' => false],
                    ['option' => ManualMigrationOption::DNSSEC_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::DNSSEC_ENABLE, 'available' => false],
                    ['option' => ManualMigrationOption::DNS_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::DNS_UPDATE_NATIVE, 'available' => false],
                    ['option' => ManualMigrationOption::DNS_DEFAULT_TEMPLATE, 'available' => true],
                    ['option' => ManualMigrationOption::DNS_NEW_EMPTY, 'available' => true],
                ],
            ],
            [
                true,
                true,
                false,
                false,
                [
                    ['option' => ManualMigrationOption::NAMESERVERS_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::NAMESERVERS_UPDATE_NEW, 'available' => true],
                    ['option' => ManualMigrationOption::DNSSEC_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::DNSSEC_ENABLE, 'available' => false],
                    ['option' => ManualMigrationOption::DNS_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::DNS_UPDATE_NATIVE, 'available' => true],
                    ['option' => ManualMigrationOption::DNS_DEFAULT_TEMPLATE, 'available' => true],
                    ['option' => ManualMigrationOption::DNS_NEW_EMPTY, 'available' => true],
                ],
            ],
            [
                false,
                true,
                true,
                false,
                [
                    ['option' => ManualMigrationOption::NAMESERVERS_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::NAMESERVERS_UPDATE_NEW, 'available' => true],
                    ['option' => ManualMigrationOption::DNSSEC_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::DNSSEC_ENABLE, 'available' => false],
                    ['option' => ManualMigrationOption::DNS_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::DNS_UPDATE_NATIVE, 'available' => false],
                    ['option' => ManualMigrationOption::DNS_DEFAULT_TEMPLATE, 'available' => false],
                    ['option' => ManualMigrationOption::DNS_NEW_EMPTY, 'available' => false],
                ],
            ],
            [
                false,
                false,
                true,
                true,
                [
                    ['option' => ManualMigrationOption::NAMESERVERS_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::NAMESERVERS_UPDATE_NEW, 'available' => false],
                    ['option' => ManualMigrationOption::DNSSEC_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::DNSSEC_ENABLE, 'available' => false],
                    ['option' => ManualMigrationOption::DNS_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::DNS_UPDATE_NATIVE, 'available' => false],
                    ['option' => ManualMigrationOption::DNS_DEFAULT_TEMPLATE, 'available' => false],
                    ['option' => ManualMigrationOption::DNS_NEW_EMPTY, 'available' => false],
                ],
            ],
            [
                true,
                false,
                false,
                true,
                [
                    ['option' => ManualMigrationOption::NAMESERVERS_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::NAMESERVERS_UPDATE_NEW, 'available' => false],
                    ['option' => ManualMigrationOption::DNSSEC_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::DNSSEC_ENABLE, 'available' => false],
                    ['option' => ManualMigrationOption::DNS_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::DNS_UPDATE_NATIVE, 'available' => true],
                    ['option' => ManualMigrationOption::DNS_DEFAULT_TEMPLATE, 'available' => true],
                    ['option' => ManualMigrationOption::DNS_NEW_EMPTY, 'available' => true],
                ],
            ],
            [
                true,
                false,
                true,
                false,
                [
                    ['option' => ManualMigrationOption::NAMESERVERS_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::NAMESERVERS_UPDATE_NEW, 'available' => false],
                    ['option' => ManualMigrationOption::DNSSEC_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::DNSSEC_ENABLE, 'available' => false],
                    ['option' => ManualMigrationOption::DNS_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::DNS_UPDATE_NATIVE, 'available' => false],
                    ['option' => ManualMigrationOption::DNS_DEFAULT_TEMPLATE, 'available' => true],
                    ['option' => ManualMigrationOption::DNS_NEW_EMPTY, 'available' => true],
                ],
            ],
            [
                false,
                true,
                false,
                true,
                [
                    ['option' => ManualMigrationOption::NAMESERVERS_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::NAMESERVERS_UPDATE_NEW, 'available' => true],
                    ['option' => ManualMigrationOption::DNSSEC_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::DNSSEC_ENABLE, 'available' => false],
                    ['option' => ManualMigrationOption::DNS_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::DNS_UPDATE_NATIVE, 'available' => false],
                    ['option' => ManualMigrationOption::DNS_DEFAULT_TEMPLATE, 'available' => true],
                    ['option' => ManualMigrationOption::DNS_NEW_EMPTY, 'available' => true],
                ],
            ],
            [
                true,
                true,
                true,
                false,
                [
                    ['option' => ManualMigrationOption::NAMESERVERS_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::NAMESERVERS_UPDATE_NEW, 'available' => true],
                    ['option' => ManualMigrationOption::DNSSEC_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::DNSSEC_ENABLE, 'available' => false],
                    ['option' => ManualMigrationOption::DNS_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::DNS_UPDATE_NATIVE, 'available' => false],
                    ['option' => ManualMigrationOption::DNS_DEFAULT_TEMPLATE, 'available' => true],
                    ['option' => ManualMigrationOption::DNS_NEW_EMPTY, 'available' => true],
                ],
            ],
            [
                true,
                true,
                false,
                true,
                [
                    ['option' => ManualMigrationOption::NAMESERVERS_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::NAMESERVERS_UPDATE_NEW, 'available' => true],
                    ['option' => ManualMigrationOption::DNSSEC_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::DNSSEC_ENABLE, 'available' => true],
                    ['option' => ManualMigrationOption::DNS_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::DNS_UPDATE_NATIVE, 'available' => true],
                    ['option' => ManualMigrationOption::DNS_DEFAULT_TEMPLATE, 'available' => true],
                    ['option' => ManualMigrationOption::DNS_NEW_EMPTY, 'available' => true],
                ],
            ],
            [
                true,
                false,
                true,
                true,
                [
                    ['option' => ManualMigrationOption::NAMESERVERS_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::NAMESERVERS_UPDATE_NEW, 'available' => false],
                    ['option' => ManualMigrationOption::DNSSEC_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::DNSSEC_ENABLE, 'available' => false],
                    ['option' => ManualMigrationOption::DNS_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::DNS_UPDATE_NATIVE, 'available' => false],
                    ['option' => ManualMigrationOption::DNS_DEFAULT_TEMPLATE, 'available' => true],
                    ['option' => ManualMigrationOption::DNS_NEW_EMPTY, 'available' => true],
                ],
            ],
            [
                false,
                true,
                true,
                true,
                [
                    ['option' => ManualMigrationOption::NAMESERVERS_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::NAMESERVERS_UPDATE_NEW, 'available' => true],
                    ['option' => ManualMigrationOption::DNSSEC_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::DNSSEC_ENABLE, 'available' => false],
                    ['option' => ManualMigrationOption::DNS_DO_NOTHING, 'available' => true],
                    ['option' => ManualMigrationOption::DNS_UPDATE_NATIVE, 'available' => false],
                    ['option' => ManualMigrationOption::DNS_DEFAULT_TEMPLATE, 'available' => false],
                    ['option' => ManualMigrationOption::DNS_NEW_EMPTY, 'available' => false],
                ],
            ],
        ];
    }
}
