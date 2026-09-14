<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Pipes;

use ErrorException;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Infra\PowerDnsClient\PowerDnsMockHelper;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Domains\DTO\RetrieveResult;
use Waterfront\Domain\Ferry\Dto\Validation\ValidationPayload;
use Waterfront\Domain\Ferry\Enums\MigrationValidation;
use Waterfront\Domain\Ferry\Enums\MigrationValidationPipes;
use Waterfront\Domain\Ferry\Models\FerryInternalNameserver;
use Waterfront\Domain\Ferry\Pipes\DnsConfigurationPipe;
use Waterfront\Infra\RtrClient\Services\RtrService;
use Waterfront\Support\Helpers\DnsHelper;

#[CoversClass(DnsConfigurationPipe::class)]
#[AllowMockObjectsWithoutExpectations]
class DnsConfigurationPipeTest extends IntegrationTestCase
{
    use PowerDnsMockHelper;

    private const string REFERENCE = 'unique_reference_for_adf';

    private ValidationPayload $validationPayload;

    protected function setUp(): void
    {
        parent::setUp();

        $customer = include __DIR__ . '/data/customer_bad_data.php';
        $subscriptions = include __DIR__ . '/data/subscriptions_correct_one_domain.php';

        $this->validationPayload = new ValidationPayload(
            validationReference: self::REFERENCE,
            customer: $customer,
            subscriptions: $subscriptions,
        );
    }

    /**
     * @param array<Response> $pdnsPayload
     * @param array<mixed>    $expectedADFPayload
     * @param array<mixed>    $dnsGetRecordCalls
     */
    #[DataProvider('dnsConfigureValidationPipelineProvider')]
    #[Test]
    public function dnsConfigurePipeline(
        array $pdnsPayload,
        array $expectedADFPayload,
        array $dnsGetRecordCalls,
        bool $dnsHelperException,
        bool $useInternalNameservers,
        bool $useInternalNameserversFromDatabase,
    ): void {
        $pdnsMock = $this->makePdnsWithMultipleResponses($pdnsPayload);
        $this->pdns($pdnsMock);

        $dnsHelper = self::createMock(DnsHelper::class);
        if ($dnsHelperException) {
            $dnsHelper
                ->method('dnsGetRecord')
                ->willThrowException(
                    new ErrorException('dns_get_record(): A temporary server error occurred.'),
                );
        } else {
            $dnsHelper
                ->expects(self::exactly(count($dnsGetRecordCalls)))
                ->method('dnsGetRecord')
                ->willReturnOnConsecutiveCalls(...$dnsGetRecordCalls);
        }

        if ($useInternalNameserversFromDatabase) {
            Config::set('ferry-domain.migratable_nameservers', 'nothing');

            $ns = new FerryInternalNameserver();
            $ns->nameserver_hostname = 'ns1.testing.test';
            $ns->save();

            $ns = new FerryInternalNameserver();
            $ns->nameserver_hostname = 'ns2.testing.test';
            $ns->save();
        }

        $this->app->bind(DnsHelper::class, fn (): DnsHelper => $dnsHelper);

        $retrieveResult = new RetrieveResult();
        $retrieveResult->setNameServers(
            $useInternalNameservers
                ? [
                    [
                        'name' => 'ns1.testing.test',
                    ],
                    [
                        'name' => 'ns2.testing.test',
                    ],
                ]
                : [
                    [
                        'name' => 'masterserver.test',
                    ],
                    [
                        'name' => 'masterserver2.test',
                    ],
                ],
        );

        $rtrService = $this->createStub(RtrService::class);
        $rtrService->method('retrieveNameservers')->willReturn($retrieveResult);

        $this->app->bind(RtrService::class, fn (): RtrService => $rtrService);

        $dnsConfigurationPipe = self::resolve(DnsConfigurationPipe::class);
        $validationPayload = $dnsConfigurationPipe->handle(
            $this->validationPayload,
            fn (ValidationPayload $validationPayload): ValidationPayload => $validationPayload,
        );

        self::assertSame(self::REFERENCE, $validationPayload->validationReference);
        if (! $dnsHelperException) {
            self::assertSame($expectedADFPayload, $validationPayload->validationResults);
        }
    }

    /**
     * @return iterable<string, mixed>
     */
    public static function dnsConfigureValidationPipelineProvider(): iterable
    {
        yield 'Zone is already master' => [
            'pdnsPayload' => [
                new Response(
                    200,
                    [],
                    self::getStaticMockedZoneResponseBody('test-dns.test'),
                ),
            ], // Pdns response set
            'expectedADFPayload' => [
                MigrationValidationPipes::DNS_CONFIGURATION->value => [
                    [
                        'id' => MigrationValidation::DNS_CONFIGURATION_ZONE_ALREADY_MASTER->value,
                        'message' => 'PowerDNS zone test-dns.test is already master',
                    ],
                    [
                        'id' => MigrationValidation::DNS_CONFIGURATION_PIPE_PASSED->value,
                        'message' => 'dns_configuration reference: unique_reference_for_adf',
                    ],
                ],
            ], // Expected validation payload to return to ADF
            'dnsGetRecordCalls' => [
                // SOA record
                [
                    [
                        'mname' => 'ns1.testing.test.',
                        'rname' => 'hostmaster.test-dns.test.',
                        'serial' => 77777777, // The serial is not up-to-date on the master zone
                        'refresh' => 10800,
                        'retry' => 3600,
                        'expire' => 604800,
                        'minimum-ttl' => 3600,
                    ],
                ],
                // NS records
                [
                    [
                        'host' => 'test-dns.test',
                        'class' => 'IN',
                        'ttl' => 4500,
                        'type' => 'NS',
                        'target' => 'ns1.testing.test',
                    ],
                    [
                        'host' => 'test-dns.test',
                        'class' => 'IN',
                        'ttl' => 4500,
                        'type' => 'NS',
                        'target' => 'ns2.testing.test',
                    ],
                ],
            ], // dnsGetRecordCalls
            'dnsHelperException' => false, // Dnsget record throws an exception.
            'useInternalNameservers' => false, // when no pdns return internal nameservers
            'useInternalNameserversFromDatabase' => false, // drops the default config and adds a ns to the db to check against
        ];

        yield 'Zone does not exist, nameserver is internal' => [
            'pdnsPayload' => [
                new Response(
                    404,
                    [],
                    'not found',
                ),
            ],
            'expectedADFPayload' => [
                MigrationValidationPipes::DNS_CONFIGURATION->value => [
                    [
                        'id' => MigrationValidation::DNS_CONFIGURATION_ZONE_DOESNT_EXIST->value,
                        'message' => 'PowerDNS zone test-dns.test does not exist',
                    ],
                    [
                        'id' => MigrationValidation::DNS_CONFIGURATION_DOMAIN_NAMESERVERS_INTERNAL->value,
                        'message' => 'Domain test-dns.test has internal primary nameserver ns1.testing.test but PowerDNS zone does not exist for it',
                    ],
                    [
                        'id' => MigrationValidation::DNS_CONFIGURATION_PIPE_PASSED->value,
                        'message' => 'dns_configuration reference: unique_reference_for_adf',
                    ],
                ],
            ],
            'dnsGetRecordCalls' => [
                // SOA record
                [
                    [
                        'mname' => 'ns1.testing.test.',
                        'rname' => 'hostmaster.test-dns.test.',
                        'serial' => 77777777, // The serial is not up-to-date on the master zone
                        'refresh' => 10800,
                        'retry' => 3600,
                        'expire' => 604800,
                        'minimum-ttl' => 3600,
                    ],
                ],
                // NS records
                [
                    [
                        'host' => 'test-dns.test',
                        'class' => 'IN',
                        'ttl' => 4500,
                        'type' => 'NS',
                        'target' => 'ns1.testing.test',
                    ],
                    [
                        'host' => 'test-dns.test',
                        'class' => 'IN',
                        'ttl' => 4500,
                        'type' => 'NS',
                        'target' => 'ns2.testing.test',
                    ],
                ],
            ],
            'dnsHelperException' => false,
            'useInternalNameservers' => true,
            'useInternalNameserversFromDatabase' => false,
        ];

        yield 'Zone does not exist, nameserver is internal. Use database check for internal nameserver' => [
            'pdnsPayload' => [
                new Response(
                    404,
                    [],
                    'not found',
                ),
            ],
            'expectedADFPayload' => [
                MigrationValidationPipes::DNS_CONFIGURATION->value => [
                    [
                        'id' => MigrationValidation::DNS_CONFIGURATION_ZONE_DOESNT_EXIST->value,
                        'message' => 'PowerDNS zone test-dns.test does not exist',
                    ],
                    [
                        'id' => MigrationValidation::DNS_CONFIGURATION_DOMAIN_NAMESERVERS_INTERNAL->value,
                        'message' => 'Domain test-dns.test has internal primary nameserver ns1.testing.test but PowerDNS zone does not exist for it',
                    ],
                    [
                        'id' => MigrationValidation::DNS_CONFIGURATION_PIPE_PASSED->value,
                        'message' => 'dns_configuration reference: unique_reference_for_adf',
                    ],
                ],
            ],
            'dnsGetRecordCalls' => [
                // SOA record
                [
                    [
                        'mname' => 'ns1.testing.test.',
                        'rname' => 'hostmaster.test-dns.test.',
                        'serial' => 77777777, // The serial is not up-to-date on the master zone
                        'refresh' => 10800,
                        'retry' => 3600,
                        'expire' => 604800,
                        'minimum-ttl' => 3600,
                    ],
                ],
                // NS records
                [
                    [
                        'host' => 'test-dns.test',
                        'class' => 'IN',
                        'ttl' => 4500,
                        'type' => 'NS',
                        'target' => 'ns1.testing.test',
                    ],
                    [
                        'host' => 'test-dns.test',
                        'class' => 'IN',
                        'ttl' => 4500,
                        'type' => 'NS',
                        'target' => 'ns2.testing.test',
                    ],
                ],
            ],
            'dnsHelperException' => false,
            'useInternalNameservers' => true,
            'useInternalNameserversFromDatabase' => true,
        ];

        yield 'PowerDNS unexpected exception, nameserver is internal' => [
            'pdnsPayload' => [
                new Response(
                    500,
                    [],
                    'random PowerDNS error',
                ),
            ],
            'expectedADFPayload' => [
                MigrationValidationPipes::DNS_CONFIGURATION->value => [
                    [
                        'id' => MigrationValidation::DNS_CONFIGURATION_ZONE_UNEXPECTED_EXCEPTION->value,
                        'message' => 'Fetching PowerDNS zone test-dns.test gave unexpected exception: random PowerDNS error',
                    ],
                    [
                        'id' => MigrationValidation::DNS_CONFIGURATION_DOMAIN_NAMESERVERS_INTERNAL->value,
                        'message' => 'Domain test-dns.test has internal primary nameserver ns1.testing.test but PowerDNS zone does not exist for it',
                    ],
                    [
                        'id' => MigrationValidation::DNS_CONFIGURATION_PIPE_PASSED->value,
                        'message' => 'dns_configuration reference: unique_reference_for_adf',
                    ],
                ],
            ],
            'dnsGetRecordCalls' => [
                // SOA record
                [
                    [
                        'mname' => 'ns1.testing.test.',
                        'rname' => 'hostmaster.test-dns.test.',
                        'serial' => 77777777, // The serial is not up-to-date on the master zone
                        'refresh' => 10800,
                        'retry' => 3600,
                        'expire' => 604800,
                        'minimum-ttl' => 3600,
                    ],
                ],
                // NS records
                [
                    [
                        'host' => 'test-dns.test',
                        'class' => 'IN',
                        'ttl' => 4500,
                        'type' => 'NS',
                        'target' => 'ns1.testing.test',
                    ],
                    [
                        'host' => 'test-dns.test',
                        'class' => 'IN',
                        'ttl' => 4500,
                        'type' => 'NS',
                        'target' => 'ns2.testing.test',
                    ],
                ],
            ],
            'dnsHelperException' => false,
            'useInternalNameservers' => true,
            'useInternalNameserversFromDatabase' => false,
        ];

        yield 'Zone does not exist, nameserver is external' => [
            'pdnsPayload' => [
                new Response(
                    404,
                    [],
                    'not found',
                ),
            ],
            'expectedADFPayload' => [
                MigrationValidationPipes::DNS_CONFIGURATION->value => [
                    [
                        'id' => MigrationValidation::DNS_CONFIGURATION_ZONE_DOESNT_EXIST->value,
                        'message' => 'PowerDNS zone test-dns.test does not exist',
                    ],
                    [
                        'id' => MigrationValidation::DNS_CONFIGURATION_DOMAIN_NAMESERVERS_EXTERNAL->value,
                        'message' => 'Domain test-dns.test has external nameservers: another-external.nameserver.test, masterserver.test',
                    ],
                    [
                        'id' => MigrationValidation::DNS_CONFIGURATION_PIPE_PASSED->value,
                        'message' => 'dns_configuration reference: unique_reference_for_adf',
                    ],
                ],
            ],
            'dnsGetRecordCalls' => [
                // SOA record
                [
                    [
                        'mname' => 'masterserver.test.',
                        'rname' => 'hostmaster.test-dns.test.',
                        'serial' => 77777777, // The serial is not up-to-date on the master zone
                        'refresh' => 10800,
                        'retry' => 3600,
                        'expire' => 604800,
                        'minimum-ttl' => 3600,
                    ],
                ],
                // NS records
                [
                    [
                        'host' => 'test-dns.test',
                        'class' => 'IN',
                        'ttl' => 4500,
                        'type' => 'NS',
                        'target' => 'ns1.testing.test',
                    ],
                    [
                        'host' => 'test-dns.test',
                        'class' => 'IN',
                        'ttl' => 4500,
                        'type' => 'NS',
                        'target' => 'another-external.nameserver.test',
                    ],
                ],
            ],
            'dnsHelperException' => false,
            'useInternalNameservers' => false,
            'useInternalNameserversFromDatabase' => false,
        ];

        yield 'Zone RRSIG DNSKEY already removed' => [
            'pdnsPayload' => [
                new Response(
                    200,
                    [],
                    self::getStaticMockedZoneResponseBodyWithoutRRSIGAndDNSKEYRecords(
                        domain: 'test-dns.test',
                        kind: 'Slave',
                        nameserver: 'ns1.testing.test',
                    ),
                ),
            ],
            'expectedADFPayload' => [
                MigrationValidationPipes::DNS_CONFIGURATION->value => [
                    [
                        'id' => MigrationValidation::DNS_CONFIGURATION_ZONE_DNSKEY_RRSIG_ALREADY_REMOVED->value,
                        'message' => 'Zone test-dns.test RRSIG and DNSKEY records are already removed',
                    ],
                    [
                        'id' => MigrationValidation::DNS_CONFIGURATION_PIPE_PASSED->value,
                        'message' => 'dns_configuration reference: unique_reference_for_adf',
                    ],
                ],
            ],
            'dnsGetRecordCalls' => [
                // SOA record
                [
                    [
                        'mname' => 'ns1.testing.test.',
                        'rname' => 'hostmaster.test-dns.test.',
                        'serial' => 2022050502,
                        'refresh' => 10800,
                        'retry' => 3600,
                        'expire' => 604800,
                        'minimum-ttl' => 3600,
                    ],
                ],
                // NS records
                [
                    [
                        'host' => 'test-dns.test',
                        'class' => 'IN',
                        'ttl' => 4500,
                        'type' => 'NS',
                        'target' => 'ns1.testing.test',
                    ],
                    [
                        'host' => 'test-dns.test',
                        'class' => 'IN',
                        'ttl' => 4500,
                        'type' => 'NS',
                        'target' => 'ns2.testing.test',
                    ],
                ],
            ],
            'dnsHelperException' => false,
            'useInternalNameservers' => false,
            'useInternalNameserversFromDatabase' => false,
        ];

        yield 'Zone SOA content does not match source SOA record content (Zone is not synced as expected)' => [
            'pdnsPayload' => [
                new Response(
                    200,
                    [],
                    self::getStaticMockedZoneResponseBody(
                        domain: 'test-dns.test',
                        kind: 'Slave',
                        nameserver: 'ns1.testing.test',
                    ),
                ),
            ],
            'expectedADFPayload' => [
                MigrationValidationPipes::DNS_CONFIGURATION->value => [
                    [
                        'id' => 'dns_configuration_soa_records_not_in_sync',
                        'message' => 'SOA record is different for domain test-dns.test',
                        'data' => [
                            'subscription_domain' => 'test-dns.test',
                            'domain_nameserver' => 'ns1.testing.test',
                            'domain_nameserver_soa_content' => 'ns1.testing.test. hostmaster.test-dns.test. 77777777 10800 3600 604800 3600',
                            'powerdns_soa_content' => 'ns1.testing.test. hostmaster.test-dns.test. 2022050502 10800 3600 604800 3600',
                        ],
                    ],
                    [
                        'id' => MigrationValidation::DNS_CONFIGURATION_PIPE_PASSED->value,
                        'message' => 'dns_configuration reference: unique_reference_for_adf',
                    ],
                ],
            ],
            'dnsGetRecordCalls' => [
                // SOA record
                [
                    [
                        'mname' => 'ns1.testing.test.',
                        'rname' => 'hostmaster.test-dns.test.',
                        'serial' => 77777777, // The serial is not up-to-date on the master zone
                        'refresh' => 10800,
                        'retry' => 3600,
                        'expire' => 604800,
                        'minimum-ttl' => 3600,
                    ],
                ],
                // NS records
                [
                    [
                        'host' => 'test-dns.test',
                        'class' => 'IN',
                        'ttl' => 4500,
                        'type' => 'NS',
                        'target' => 'ns1.testing.test',
                    ],
                    [
                        'host' => 'test-dns.test',
                        'class' => 'IN',
                        'ttl' => 4500,
                        'type' => 'NS',
                        'target' => 'ns2.testing.test',
                    ],
                ],
            ],
            'dnsHelperException' => false,
            'useInternalNameservers' => false,
            'useInternalNameserversFromDatabase' => false,
        ];

        yield 'DNS get does not properly resolve while attempting to verify' => [
            'pdnsPayload' => [
                new Response(
                    200,
                    [],
                    self::getStaticMockedZoneResponseBody('test-dns.test', [], 'Slave'),
                ),
            ],
            'expectedADFPayload' => [],
            'dnsGetRecordCalls' => [],
            'dnsHelperException' => true,
            'useInternalNameservers' => false,
            'useInternalNameserversFromDatabase' => false,
        ];

        yield 'DNS zone is empty' => [
            'pdnsPayload' => [
                new Response(
                    200,
                    [],
                    self::getMockedZoneResponseBodyWithRrsets('test-dns.test', [], 'Slave'),
                ),
            ],
            'expectedADFPayload' => [
                MigrationValidationPipes::DNS_CONFIGURATION->value => [
                    [
                        'id' => 'dns_configuration_zone_no_records',
                        'message' => 'PowerDNS zone test-dns.test has no records',
                    ],
                    [
                        'id' => 'dns_configuration_dnskey_rrsig_already_removed',
                        'message' => 'Zone test-dns.test RRSIG and DNSKEY records are already removed',
                    ],
                    [
                        'id' => 'dns_configuration_passed',
                        'message' => 'dns_configuration reference: unique_reference_for_adf',
                    ],
                ],
            ],
            'dnsGetRecordCalls' => [
                [
                    // SOA record
                    [
                        [
                            'mname' => 'ns1.testing.test.',
                            'rname' => 'hostmaster.test-dns.test.',
                            'serial' => 2022050502,
                            'refresh' => 10800,
                            'retry' => 3600,
                            'expire' => 604800,
                            'minimum-ttl' => 3600,
                        ],
                    ],
                    // NS records
                    [
                        [
                            'host' => 'test-dns.test',
                            'class' => 'IN',
                            'ttl' => 4500,
                            'type' => 'NS',
                            'target' => 'ns1.testing.test',
                        ],
                        [
                            'host' => 'test-dns.test',
                            'class' => 'IN',
                            'ttl' => 4500,
                            'type' => 'NS',
                            'target' => 'ns2.testing.test',
                        ],
                    ],
                ],
            ],
            'dnsHelperException' => false,
            'useInternalNameservers' => true,
            'useInternalNameserversFromDatabase' => false,
        ];
    }
}
