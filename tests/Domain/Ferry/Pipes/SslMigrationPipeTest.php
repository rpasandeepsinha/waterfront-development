<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Pipes;

use JsonException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Exception;
use RealtimeRegister\Domain\CertificateCollection;
use Tests\Factories\DnsNameserverFactory;
use Tests\Factories\DnsRegionFactory;
use Tests\Factories\DomainProviderBusinessUnitFactory;
use Tests\Factories\ServerFactory;
use Tests\Infra\PowerDnsClient\PowerDnsMockHelper;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Domains\DTO\DomainDetailsDTO;
use Waterfront\Domain\Domains\Factories\DomainServiceFactory;
use Waterfront\Domain\Domains\Interfaces\DomainDriverInterface;
use Waterfront\Domain\Domains\Serializers\DomainSerializerFactory;
use Waterfront\Domain\Ferry\Dto\Validation\ValidationPayload;
use Waterfront\Domain\Ferry\Enums\MigrationValidation;
use Waterfront\Domain\Ferry\Enums\MigrationValidationPipes;
use Waterfront\Domain\Ferry\Pipes\SslMigrationPipe;
use Waterfront\Domain\Ferry\Services\DomainAndSslMigrationService;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Infra\Common\PublicSuffixList;
use Waterfront\Infra\DirectAdminClient\DTO\UserConfig;
use Waterfront\Infra\DirectAdminClient\Enums\HostingUserType;
use Waterfront\Support\Helpers\DnsHelper;

#[CoversClass(SslMigrationPipe::class)]
#[AllowMockObjectsWithoutExpectations]
class SslMigrationPipeTest extends IntegrationTestCase
{
    use PowerDnsMockHelper;

    private const string TEST_DIRECTADMIN_SERVER = 'my_hostname.nl';
    private const string TEST_PLESK_SERVER = 'my_plesk_hostname.nl';

    /**
     * @param array<mixed> $subscriptions
     * @param array<mixed> $expectedValidationResults
     *
     * @throws JsonException
     * @throws Exception
     */
    #[DataProvider('sslValidationPipelineProvider')]
    #[Test]
    public function sslValidationPipeline(
        array $subscriptions,
        bool $domainAlreadyRegisteredAtRtrForSsl,
        int $numberOfTimesHostingPackageIsFetched,
        bool $hostingHasSslDisabled,
        array $expectedValidationResults
    ): void {
        ServerFactory::new()->directadmin()->createOne([
            'hostname' => self::TEST_DIRECTADMIN_SERVER,
            'domain' => self::TEST_DIRECTADMIN_SERVER,
            'name' => self::TEST_DIRECTADMIN_SERVER,
        ]);

        ServerFactory::new()->plesk()->createOne([
            'hostname' => self::TEST_PLESK_SERVER,
            'domain' => self::TEST_PLESK_SERVER,
            'name' => self::TEST_PLESK_SERVER,
        ]);

        $region = DnsRegionFactory::new()->createOne();
        DnsNameserverFactory::new()->for($region)->createOne(['nameserver' => 'nameserver01.testing_from_db.test']);
        DnsNameserverFactory::new()->for($region)->createOne(['nameserver' => 'nameserver02.testing_from_db.test']);

        $customer = include(__DIR__ . '/data/customer_correct.php');

        $reference = 'unique_reference_for_adf';

        $validationPayload = new ValidationPayload(
            validationReference: $reference,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $rtrMigrationService = $this->createPartialMock(
            DomainAndSslMigrationService::class,
            ['listRtrSslCertificates']
        );
        $rtrMigrationService->method('listRtrSslCertificates')
            ->willReturnCallback(function () use ($domainAlreadyRegisteredAtRtrForSsl): CertificateCollection {
                if ($domainAlreadyRegisteredAtRtrForSsl) {
                    return CertificateCollection::fromArray([
                        include __DIR__ . '/data/certificate_valid.php',
                    ]);
                }

                return CertificateCollection::fromArray([]);
            });
        $this->app->bind(DomainAndSslMigrationService::class, fn (): DomainAndSslMigrationService => $rtrMigrationService);

        $mockHostingService = self::createMock(HostingService::class);
        $mockHostingService
            ->expects(self::exactly($numberOfTimesHostingPackageIsFetched))
            ->method('getUserConfigAsDto')
            ->willReturn(new UserConfig(
                dnscontrol: 'ON',
                ssl: $hostingHasSslDisabled ? 'OFF' : 'ON',
                loginKeys: 'ON',
                vdomains: '10',
                nemails: '10',
                mysql: '10',
                bandwidth: '1024',
                quota: '1024',
                package: 'basic',
                usertype: HostingUserType::USER,
                domain: 'test.nl',
            ));

        $this->app->bind(HostingService::class, fn () => $mockHostingService);

        $sslMigrationPipe = self::resolve(SslMigrationPipe::class);

        $validationPayload = $sslMigrationPipe->handle(
            $validationPayload,
            fn (ValidationPayload $validationPayload): ValidationPayload => $validationPayload
        );

        self::assertSame($reference, $validationPayload->validationReference);
        self::assertSame($expectedValidationResults, $validationPayload->validationResults);
    }

    #[Test]
    public function sslValidationPipelineWithBu(): void
    {
        $dnsNameserver = DnsNameserverFactory::new()
            ->for(DnsRegionFactory::new()->createOne())
            ->createOne();

        $businessUnit = DomainProviderBusinessUnitFactory::new()->argeweb()->createOne();
        $expectedDriver = 'openprovider';

        $domainDetails = include __DIR__ . '/data/rtr/domainDetailsNoNS.php';
        $serializer = DomainSerializerFactory::getSerializer();
        $domainDetails = $serializer->denormalize($domainDetails, DomainDetailsDTO::class);

        $mockDomainList = self::mock(PublicSuffixList::class);
        $mockDomainList->shouldReceive('getRegistrableDomain')->andReturn('test-dns-intern-10.nl');
        $this->app->bind(PublicSuffixList::class, fn () => $mockDomainList);

        $mockDnsHelper = self::mock(DnsHelper::class);
        $mockDnsHelper->shouldReceive('dnsGetRecord')->andReturn([]);
        $this->app->bind(DnsHelper::class, fn () => $mockDnsHelper);

        $mockMigrationService = self::mock(DomainAndSslMigrationService::class);
        $mockMigrationService
            ->shouldReceive('getProviderBusinessUnit')
            ->with($businessUnit->slug, ProviderSlug::from($expectedDriver))
            ->andReturn($businessUnit);

        $this->app->bind(DomainAndSslMigrationService::class, fn () => $mockMigrationService);

        $mockDomainServiceFactory = self::mock(DomainServiceFactory::class);
        $mockDomainDriver = self::mock(DomainDriverInterface::class);

        $mockDomainServiceFactory
            ->shouldReceive('driver')
            ->with(ProviderSlug::from($expectedDriver), $businessUnit)
            ->andReturn($mockDomainDriver);

        $mockDomainDriver
            ->shouldReceive('fetchDomain')
            ->andReturn($domainDetails);

        $this->app->bind(DomainServiceFactory::class, fn () => $mockDomainServiceFactory);

        $rtrMigrationService = $this->createPartialMock(
            DomainAndSslMigrationService::class,
            ['listRtrSslCertificates']
        );
        $rtrMigrationService->method('listRtrSslCertificates')
            ->willReturnCallback(fn (): CertificateCollection => CertificateCollection::fromArray([]));
        $this->app->bind(DomainAndSslMigrationService::class, fn (): DomainAndSslMigrationService => $rtrMigrationService);

        $subscriptions = include(__DIR__ . '/data/subscription_domain_ssl_with_bu_correct.php');
        $customer = include(__DIR__ . '/data/customer_correct.php');
        $reference = 'unique_reference_for_adf';

        $validationPayload = new ValidationPayload(
            validationReference: $reference,
            customer: $customer,
            subscriptions: $subscriptions
        );
        $sslMigrationPipe = self::resolve(SslMigrationPipe::class);

        $sslMigrationPipe->handle(
            $validationPayload,
            fn (ValidationPayload $validationPayload): ValidationPayload => $validationPayload
        );

        self::assertSame(
            [
                MigrationValidationPipes::SSL_MIGRATION->value => [
                    [
                        'id' => MigrationValidation::SSL_PIPE_PASSED->value,
                        'message' => 'ssl_migration reference: unique_reference_for_adf',
                    ],
                ],
            ],
            $validationPayload->validationResults
        );
    }

    /**
     * @return iterable<string, mixed>
     */
    public static function sslValidationPipelineProvider(): iterable
    {
        yield 'Domain in RTR already exists as SSL certificate' => [
            'subscriptions' => include(__DIR__ . '/data/subscriptions_correct.php'),
            'domainAlreadyRegisteredAtRtrForSsl' => true,
            'numberOfTimesHostingPackageIsFetched' => 0,
            'hostingHasSslDisabled' => false,
            'expectedValidationResults' => [
                MigrationValidationPipes::SSL_MIGRATION->value => [
                    [
                        'id' => MigrationValidation::SSL_MIGRATION_DOMAIN_ALREADY_PRESENT_AT_RTR->value,
                        'message' => 'SSL certificate already present at RTR domain: {test-dns-intern-10.nl}',
                    ],
                    [
                        'id' => MigrationValidation::SSL_PIPE_PASSED->value,
                        'message' => 'ssl_migration reference: unique_reference_for_adf',
                    ],
                ],
            ],
        ];

        yield 'Domain has a hosting package but package has SSL disabled' => [
            'subscriptions' => include(__DIR__ . '/data/subscriptions_correct.php'),
            'domainAlreadyRegisteredAtRtrForSsl' => false,
            'numberOfTimesHostingPackageIsFetched' => 1,
            'hostingHasSslDisabled' => true,
            'expectedValidationResults' => [
                MigrationValidationPipes::SSL_MIGRATION->value => [
                    [
                        'id' => MigrationValidation::SSL_MIGRATION_HOSTING_SITE_SSL_IS_DISABLED->value,
                        'message' => 'Hosting server of type "directadmin" and hostname "my_hostname.nl" has no SSL enabled',
                        'reference_subscription_id' => '3535',
                    ],
                    [
                        'id' => MigrationValidation::SSL_PIPE_PASSED->value,
                        'message' => 'ssl_migration reference: unique_reference_for_adf',
                    ],
                ],
            ],
        ];

        yield 'Domain has a hosting package and SSL is subdomain' => [
            'subscriptions' => include(__DIR__ . '/data/subscriptions_correct_ssl_subdomain.php'),
            'domainAlreadyRegisteredAtRtrForSsl' => false,
            'numberOfTimesHostingPackageIsFetched' => 1,
            'hostingHasSslDisabled' => false,
            'expectedValidationResults' => [
                MigrationValidationPipes::SSL_MIGRATION->value => [
                    [
                        'id' => MigrationValidation::SSL_PIPE_PASSED->value,
                        'message' => 'ssl_migration reference: unique_reference_for_adf',
                    ],
                ],
            ],
        ];

        yield 'Domain has a hosting package and SSL is subdomain. But serverType is Plesk' => [
            'subscriptions' => include(__DIR__ . '/data/subscriptions_correct_ssl_subdomain_plesk.php'),
            'domainAlreadyRegisteredAtRtrForSsl' => false,
            'numberOfTimesHostingPackageIsFetched' => 1,
            'hostingHasSslDisabled' => false,
            'expectedValidationResults' => [
                MigrationValidationPipes::SSL_MIGRATION->value => [
                    [
                        'id' => MigrationValidation::SSL_PIPE_PASSED->value,
                        'message' => 'ssl_migration reference: unique_reference_for_adf',
                    ],
                ],
            ],
        ];

        yield 'Domain has invalid base domain' => [
            'subscriptions' => include(__DIR__ . '/data/subscriptions_bad_ssl_domain.php'),
            'domainAlreadyRegisteredAtRtrForSsl' => false,
            'numberOfTimesHostingPackageIsFetched' => 0,
            'hostingHasSslDisabled' => false,
            'expectedValidationResults' => [
                MigrationValidationPipes::SSL_MIGRATION->value => [
                    [
                        'id' => MigrationValidation::SSL_MIGRATION_UNABLE_TO_PARSE_BASE_DOMAIN->value,
                        'message' => 'Unable to parse base domain from SSL domain',
                    ],
                    [
                        'id' => MigrationValidation::SSL_PIPE_PASSED->value,
                        'message' => 'ssl_migration reference: unique_reference_for_adf',
                    ],
                ],
            ],
        ];
    }
}
