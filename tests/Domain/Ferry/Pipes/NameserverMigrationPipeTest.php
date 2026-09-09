<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Pipes;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Bus\Dispatcher;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RealtimeRegister\Domain\Enum\DomainStatusEnum;
use Tests\Factories\DomainProviderBusinessUnitFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Domains\DTO\DomainDetailsDTO;
use Waterfront\Domain\Domains\Serializers\DomainSerializerFactory;
use Waterfront\Domain\Ferry\Dto\Validation\ValidationPayload;
use Waterfront\Domain\Ferry\Enums\MigrationValidation;
use Waterfront\Domain\Ferry\Enums\MigrationValidationPipes;
use Waterfront\Domain\Ferry\Pipes\NameserverMigrationPipe;
use Waterfront\Domain\Ferry\Services\DnsMigrationService;
use Waterfront\Infra\OpenproviderClient\Interfaces\OpenProviderConnectionInterface;
use Waterfront\Infra\OpenproviderClient\OpenproviderClient;
use Waterfront\Infra\RtrClient\Services\RtrService;

#[CoversClass(NameserverMigrationPipe::class)]
class NameserverMigrationPipeTest extends IntegrationTestCase
{
    #[Test]
    public function nameserverNoBusinessUnit(): void
    {
        $customer      = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/subscriptions_correct_one_domain_argeweb_bu.php');

        $reference = 'unique_reference_for_adf';

        $rtrService = $this->createMock(RtrService::class);
        $rtrService->expects(self::never())
            ->method('fetchDomain');

        $rtrService->expects(self::never())
            ->method('setClient');

        $this->app->bind(RtrService::class, fn () => $rtrService);

        $validationPayload = new ValidationPayload(
            validationReference: $reference,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $dnsNameserverMigrationPipe = self::resolve(NameserverMigrationPipe::class);

        $validationPayload = $dnsNameserverMigrationPipe->handle(
            $validationPayload,
            fn (ValidationPayload $validationPayload): ValidationPayload => $validationPayload
        );

        self::assertSame($reference, $validationPayload->validationReference);
        self::assertSame(
            [
                MigrationValidationPipes::NAMESERVER_MIGRATION->value => [
                    [
                        'id' => MigrationValidation::DOMAIN_MIGRATION_BUSINESS_UNIT_FAILED->value,
                        'message' => "Couldn't find domain provider business unit by slug [argeweb] from backend openprovider, exception: The given business unit slug [argeweb] could not be found. Please ensure that the business unit exists and is correctly configured.",
                    ],
                    [
                        'id' => MigrationValidation::NAMESERVER_PIPE_PASSED->value,
                        'message' => 'dns_nameserver_migration reference: unique_reference_for_adf',
                    ],
                ],
            ],
            $validationPayload->validationResults
        );
    }

    #[Test]
    public function nameserverNoCredentialsBusinessUnit(): void
    {
        $customer      = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/subscriptions_correct_one_domain_argeweb_bu.php');

        $reference = 'unique_reference_for_adf';

        DomainProviderBusinessUnitFactory::new()->argeweb()->createOne();

        $rtrService = $this->createMock(RtrService::class);
        $rtrService->expects(self::never())
            ->method('fetchDomain');

        $rtrService->expects(self::never())
            ->method('setClient');

        $this->app->bind(RtrService::class, fn () => $rtrService);

        $validationPayload = new ValidationPayload(
            validationReference: $reference,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $dnsNameserverMigrationPipe = self::resolve(NameserverMigrationPipe::class);

        $validationPayload = $dnsNameserverMigrationPipe->handle(
            $validationPayload,
            fn (ValidationPayload $validationPayload): ValidationPayload => $validationPayload
        );

        self::assertSame($reference, $validationPayload->validationReference);
        self::assertSame(
            [
                MigrationValidationPipes::NAMESERVER_MIGRATION->value => [
                    [
                        'id' => MigrationValidation::DOMAIN_MIGRATION_DRIVER_CREDENTIALS_FAILED->value,
                        'message' => 'No credentials set for [openprovider] with business unit [argeweb], exception: The given business unit slug [argeweb] does not have credentials for the given provider [openprovider]. Please ensure that the business unit & credentials exists and is correctly configured.',
                    ],
                    [
                        'id' => MigrationValidation::NAMESERVER_PIPE_PASSED->value,
                        'message' => 'dns_nameserver_migration reference: unique_reference_for_adf',
                    ],
                ],
            ],
            $validationPayload->validationResults
        );
    }

    #[Test]
    public function nameserverNotMigratable(): void
    {
        $customer      = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/subscriptions_correct.php');
        $serializer = DomainSerializerFactory::getSerializer();

        $reference = 'unique_reference_for_adf';
        $details1 = $serializer->denormalize([
            'domainName' => 'bla.com',
            'registry' => '',
            'customer' => '',
            'registrant' => '',
            'privacyProtect' => true,
            'status' => [DomainStatusEnum::STATUS_OK],
            'authcode' => '',
            'languageCode' => '',
            'autoRenew' => true,
            'autoRenewPeriod' => 0,
            'ns' => [
                'nameserver01.testing.test',
                'nameserver02.testing.test',
            ],
            'childHosts' => [],
            'createdDate' => '2020-09-09T09:18:57Z',
            'expiryDate' => '2020-09-09T09:18:57Z',
            'premium' => false,
        ], DomainDetailsDTO::class);

        $details2 = $serializer->denormalize([
            'domainName' => 'bla.com',
            'registry' => '',
            'customer' => '',
            'registrant' => '',
            'privacyProtect' => true,
            'status' => [DomainStatusEnum::STATUS_OK],
            'authcode' => '',
            'languageCode' => '',
            'autoRenew' => true,
            'autoRenewPeriod' => 0,
            'ns' => [
                'ns1.sandwave-test.com',
                'is-whitelabel-nameserver.test',
            ],
            'childHosts' => [],
            'createdDate' => '2020-09-09T09:18:57Z',
            'expiryDate' => '2020-09-09T09:18:57Z',
            'premium' => false,
        ], DomainDetailsDTO::class);

        $rtrService = $this->createMock(RtrService::class);
        $rtrService->expects(self::exactly(2))
            ->method('fetchDomain')
            ->willReturnCallback(
                fn (string $domain) => match ($domain) {
                    'test-dns-intern-10.nl' => $details1,
                    'test-dns-intern-11.nl' => $details2,
                    default => throw new LogicException()
                }
            );

        $rtrService
            ->method('setHandle')
            ->willReturnSelf();

        $rtrService
            ->method('setClient')
            ->willReturnSelf();

        $this->app->bind(RtrService::class, fn () => $rtrService);

        $dnsMigrationService = $this->createPartialMock(
            DnsMigrationService::class,
            [
                'getNameserversViaReverseDNS',
                'isMigratableNameserver',
            ]
        );

        $dnsMigrationService->expects(self::exactly(3))
            ->method('getNameserversViaReverseDNS')
            ->willReturn([
                'nameserver01.testing.test',
            ]);

        $dnsMigrationService->expects(self::exactly(7))
            ->method('isMigratableNameserver')
            ->willReturn(
                true, // test dns 10 n1
                false, // test dns 10 n2
                false, // test dns 10 n2 -> not whitelabel
                false, // test dns 11 n1
                false, // test dns 11 n1-> not whitelabel
                false, // test dns 11 n2
                true // // test dns 11 n2 -> is whitelabel
            );

        $this->app->bind(DnsMigrationService::class, fn () => $dnsMigrationService);

        $validationPayload = new ValidationPayload(
            validationReference: $reference,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $dnsNameserverMigrationPipe = self::resolve(NameserverMigrationPipe::class);

        $validationPayload = $dnsNameserverMigrationPipe->handle(
            $validationPayload,
            fn (ValidationPayload $validationPayload): ValidationPayload => $validationPayload
        );

        self::assertSame($reference, $validationPayload->validationReference);
        self::assertSame(
            [
                MigrationValidationPipes::NAMESERVER_MIGRATION->value => [
                    [
                        'id' => MigrationValidation::NAMESERVER_HAS_NON_MIGRATEABLE_NAMESERVER->value,
                        'message' => 'Domain test-dns-intern-10.nl has non-migratable nameserver nameserver02.testing.test',
                    ],
                    [
                        'id' => MigrationValidation::NAMESERVER_HAS_NON_MIGRATEABLE_NAMESERVER->value,
                        'message' => 'Domain test-dns-intern-11.nl has non-migratable nameserver ns1.sandwave-test.com',
                    ],
                    [
                        'id' => MigrationValidation::NAMESERVER_HAS_WHITELABEL_NAMESERVER->value,
                        'message' => 'Domain test-dns-intern-11.nl has an whitelabel nameserver (rdns) nameserver01.testing.test',
                    ],
                    [
                        'id' => MigrationValidation::NAMESERVER_PIPE_PASSED->value,
                        'message' => 'dns_nameserver_migration reference: unique_reference_for_adf',
                    ],
                ],
            ],
            $validationPayload->validationResults
        );
    }

    /**
     * @see https://yh-jira.atlassian.net/browse/SWD-9590
     */
    #[Test]
    public function openproviderNameserverEmptyNameserver(): void
    {
        DomainProviderBusinessUnitFactory::new()->argeweb()->createOne();

        $mockHandler = new MockHandler([
            new Response(
                status: 200,
                body: (string) file_get_contents(__DIR__ . '/data/nameserver_migration/openprovider_retrieve_nameserver_empty.xml')
            ),
        ]);

        $openproviderClient = new OpenproviderClient(
            httpClient: new Client(['handler' => HandlerStack::create($mockHandler)]),
            connection: self::createStub(OpenProviderConnectionInterface::class),
            jobDispatcher: self::createStub(Dispatcher::class),
        );
        $this->app->bind(OpenproviderClient::class, fn () => $openproviderClient);

        $customer      = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/subscriptions_correct_one_domain_openprovider.php');

        $reference = 'unique_reference_for_adf';

        $validationPayload = new ValidationPayload(
            validationReference: $reference,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $dnsNameserverMigrationPipe = self::resolve(NameserverMigrationPipe::class);
        $validationPayload = $dnsNameserverMigrationPipe->handle(
            $validationPayload,
            fn (ValidationPayload $validationPayload): ValidationPayload => $validationPayload
        );

        self::assertSame($reference, $validationPayload->validationReference);
        self::assertSame(
            [
                MigrationValidationPipes::NAMESERVER_MIGRATION->value => [
                    [
                        'id' => MigrationValidation::NAMESERVER_HOSTNAME_NOT_STRING->value,
                        'message' => 'Domain test-dns.test with status PENDING_VALIDATION has nameserver hostname which is not a string',
                        'data' => [
                            'hostname' => [],
                        ],
                    ],
                    [
                        'id' => MigrationValidation::NAMESERVER_PIPE_PASSED->value,
                        'message' => 'dns_nameserver_migration reference: unique_reference_for_adf',
                    ],
                ],
            ],
            $validationPayload->validationResults
        );
    }
}
