<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Pipes;

use GuzzleHttp\Psr7\Response;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RealtimeRegister\Exceptions\RealtimeRegisterClientException;
use Tests\Factories\DomainProviderBusinessUnitFactory;
use Tests\Infra\PowerDnsClient\PowerDnsMockHelper;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\Ferry\Dto\Validation\ValidationPayload;
use Waterfront\Domain\Ferry\Enums\MigrationValidation;
use Waterfront\Domain\Ferry\Enums\MigrationValidationPipes;
use Waterfront\Domain\Ferry\Pipes\DnsSecEnablePipe;
use Waterfront\Infra\RtrClient\Services\RtrService;

#[CoversClass(DnsSecEnablePipe::class)]
class DnsSecEnablePipeTest extends IntegrationTestCase
{
    use PowerDnsMockHelper;

    private const string REFERENCE = 'unique_reference_for_adf';

    #[Test]
    public function dnsZoneNotMaster(): void
    {
        $customer = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/dns_sec/subscription_zone_not_master.php');

        $validationPayload = new ValidationPayload(
            validationReference: self::REFERENCE,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $pdnsMock = $this->makePdnsWithMultipleResponses([
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('zone-not-master.nl', [], 'Slave')
            ),
        ]);

        $this->pdns($pdnsMock);

        $dnssecEnablePipe = self::resolve(DnsSecEnablePipe::class);
        $validationPayload = $dnssecEnablePipe->handle(
            $validationPayload,
            fn (ValidationPayload $validationPayload): ValidationPayload => $validationPayload
        );

        self::assertSame(self::REFERENCE, $validationPayload->validationReference);
        self::assertSame(
            [
                MigrationValidationPipes::DNSSEC_ENABLE->value => [
                    [
                        'id' => MigrationValidation::DNSSEC_ZONE_NOT_MASTER->value,
                        'message' => 'DNS zone zone-not-master.nl is type Slave',
                    ],
                    [
                        'id' => MigrationValidation::DNSSEC_PIPE_PASSED->value,
                        'message' => 'dnssec_enable reference: unique_reference_for_adf',
                    ],
                ],
            ],
            $validationPayload->validationResults
        );
    }

    #[Test]
    public function dnsZoneInvalidFQDN(): void
    {
        $customer = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/dns_sec/subscription_zone_not_master.php');

        $validationPayload = new ValidationPayload(
            validationReference: self::REFERENCE,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $dnsServiceMock = self::createMock(DnsService::class);
        $dnsServiceMock->expects(self::once())->method('getDnsZone')
            ->willThrowException(self::createStub(ValidationException::class));

        $this->app->instance(DnsService::class, $dnsServiceMock);

        $dnssecEnablePipe = self::resolve(DnsSecEnablePipe::class);
        $validationPayload = $dnssecEnablePipe->handle(
            $validationPayload,
            fn (ValidationPayload $validationPayload): ValidationPayload => $validationPayload
        );

        self::assertSame(self::REFERENCE, $validationPayload->validationReference);
        self::assertSame(
            [
                MigrationValidationPipes::DNSSEC_ENABLE->value => [
                    [
                        'id' => MigrationValidation::DNSSEC_ZONE_UNEXPECTED_EXCEPTION->value,
                        'message' => 'Fetching PowerDNS zone zone-not-master.nl gave unexpected exception: ',
                    ],
                    [
                        'id' => MigrationValidation::DNSSEC_PIPE_PASSED->value,
                        'message' => 'dnssec_enable reference: unique_reference_for_adf',
                    ],
                ],
            ],
            $validationPayload->validationResults
        );
    }

    #[Test]
    public function dnssecNoBusinessDomain(): void
    {
        $customer = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/dns_sec/subscription_bu_argeweb_rtr.php');

        $validationPayload = new ValidationPayload(
            validationReference: self::REFERENCE,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $pdnsMock = $this->makePdnsWithMultipleResponses([
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('bu-argeweb.nl')
            ),
        ]);

        $this->pdns($pdnsMock);

        $rtrService = $this->createMock(RtrService::class);

        $rtrService->expects(self::never())
            ->method('isDnssecSupported');

        $rtrService->expects(self::never())
            ->method('setHandle');

        $rtrService->expects(self::never())
            ->method('setClient');

        $this->app->bind(RtrService::class, fn () => $rtrService);

        $dnssecEnablePipe = self::resolve(DnsSecEnablePipe::class);
        $validationPayload = $dnssecEnablePipe->handle(
            $validationPayload,
            fn (ValidationPayload $validationPayload): ValidationPayload => $validationPayload
        );

        self::assertSame(self::REFERENCE, $validationPayload->validationReference);
        self::assertSame(
            [
                MigrationValidationPipes::DNSSEC_ENABLE->value => [
                    [
                        'id' => MigrationValidation::DOMAIN_MIGRATION_BUSINESS_UNIT_FAILED->value,
                        'message' => "Couldn't find domain provider business unit by slug [argeweb] from backend realtime_register, exception: The given business unit slug [argeweb] could not be found. Please ensure that the business unit exists and is correctly configured.",
                    ],
                    [
                        'id' => MigrationValidation::DNSSEC_PIPE_PASSED->value,
                        'message' => 'dnssec_enable reference: unique_reference_for_adf',
                    ],
                ],
            ],
            $validationPayload->validationResults
        );
    }

    #[Test]
    public function exceptionFromDnsSec(): void
    {
        $customer = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/dns_sec/subscription_throw_dns_sec_exception.php');

        $validationPayload = new ValidationPayload(
            validationReference: self::REFERENCE,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $pdnsMock = $this->makePdnsWithMultipleResponses([
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('throws-general-exception.nl')
            ),
        ]);

        $this->pdns($pdnsMock);

        $rtrService = $this->createMock(RtrService::class);

        $rtrService->expects(self::once())
            ->method('isDnssecSupported')
            ->with(self::equalTo('throws-general-exception.nl'))
            ->willThrowException(new RealtimeRegisterClientException('Something went wrong'));

        $rtrService
            ->method('setHandle')
            ->willReturnSelf();

        $rtrService
            ->method('setClient')
            ->willReturnSelf();

        $this->app->bind(RtrService::class, fn () => $rtrService);

        $dnssecEnablePipe = self::resolve(DnsSecEnablePipe::class);
        $validationPayload = $dnssecEnablePipe->handle(
            $validationPayload,
            fn (ValidationPayload $validationPayload): ValidationPayload => $validationPayload
        );

        self::assertSame(self::REFERENCE, $validationPayload->validationReference);
        self::assertSame(
            [
                MigrationValidationPipes::DNSSEC_ENABLE->value => [
                    [
                        'id' => MigrationValidation::DNSSEC_PIPE_FAILED->value,
                        'message' => "Couldn't check if DNSSEC was supported for throws-general-exception.nl from backend realtime_register, exception: Something went wrong",
                    ],
                    [
                        'id' => MigrationValidation::DNSSEC_PIPE_PASSED->value,
                        'message' => 'dnssec_enable reference: unique_reference_for_adf',
                    ],
                ],
            ],
            $validationPayload->validationResults
        );
    }

    #[Test]
    public function dnssecNoCredentialsForBusinessDomain(): void
    {
        $customer = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/dns_sec/subscription_bu_argeweb_rtr.php');

        DomainProviderBusinessUnitFactory::new()->argeweb()->createOne();

        $validationPayload = new ValidationPayload(
            validationReference: self::REFERENCE,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $pdnsMock = $this->makePdnsWithMultipleResponses([
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('bu-argeweb.nl')
            ),
        ]);

        $this->pdns($pdnsMock);

        $rtrService = $this->createMock(RtrService::class);

        $rtrService->expects(self::never())
            ->method('isDnssecSupported');

        $rtrService->expects(self::never())
            ->method('setHandle');

        $rtrService->expects(self::never())
            ->method('setClient');

        $this->app->bind(RtrService::class, fn () => $rtrService);

        $dnssecEnablePipe = self::resolve(DnsSecEnablePipe::class);
        $validationPayload = $dnssecEnablePipe->handle(
            $validationPayload,
            fn (ValidationPayload $validationPayload): ValidationPayload => $validationPayload
        );

        self::assertSame(self::REFERENCE, $validationPayload->validationReference);
        self::assertSame(
            [
                MigrationValidationPipes::DNSSEC_ENABLE->value => [
                    [
                        'id' => MigrationValidation::DOMAIN_MIGRATION_DRIVER_CREDENTIALS_FAILED->value,
                        'message' => 'No credentials set for [realtime_register] with business unit [argeweb], exception: The given business unit slug [argeweb] does not have credentials for the given provider [realtime_register]. Please ensure that the business unit & credentials exists and is correctly configured.',
                    ],
                    [
                        'id' => MigrationValidation::DNSSEC_PIPE_PASSED->value,
                        'message' => 'dnssec_enable reference: unique_reference_for_adf',
                    ],
                ],
            ],
            $validationPayload->validationResults
        );
    }

    #[Test]
    public function dnssecUnknownProvider(): void
    {
        $customer = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/dns_sec/subscription_bu_argeweb_rtr.php');
        $subscriptions['domain_extensions'][0]['driver'] = 'open_srs';

        DomainProviderBusinessUnitFactory::new()->argeweb()->createOne();

        $validationPayload = new ValidationPayload(
            validationReference: self::REFERENCE,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $pdnsMock = $this->makePdnsWithMultipleResponses([
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('bu-argeweb.nl')
            ),
        ]);

        $this->pdns($pdnsMock);

        $rtrService = $this->createMock(RtrService::class);

        $rtrService->expects(self::never())
            ->method('isDnssecSupported');

        $rtrService->expects(self::never())
            ->method('setHandle');

        $rtrService->expects(self::never())
            ->method('setClient');

        $this->app->bind(RtrService::class, fn () => $rtrService);

        $dnssecEnablePipe = self::resolve(DnsSecEnablePipe::class);
        $validationPayload = $dnssecEnablePipe->handle(
            $validationPayload,
            fn (ValidationPayload $validationPayload): ValidationPayload => $validationPayload
        );

        self::assertSame(self::REFERENCE, $validationPayload->validationReference);
        self::assertSame(
            [
                MigrationValidationPipes::DNSSEC_ENABLE->value => [
                   [
                        'id' => MigrationValidation::DNSSEC_PIPE_FAILED->value,
                        'message' => "Couldn't check if DNSSEC was supported for bu-argeweb.nl from backend open_srs, exception: \"open_srs\" is not a valid backing value for enum Waterfront\Domain\Providers\Enums\ProviderSlug",
                    ],
                    [
                        'id' => MigrationValidation::DNSSEC_PIPE_PASSED->value,
                        'message' => 'dnssec_enable reference: unique_reference_for_adf',
                    ],
                ],
            ],
            $validationPayload->validationResults
        );
    }

    #[Test]
    public function tldDoesntSupportDnsSec(): void
    {
        $customer = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/dns_sec/subscription_no_dns_sec_domain.php');

        $validationPayload = new ValidationPayload(
            validationReference: self::REFERENCE,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $pdnsMock = $this->makePdnsWithMultipleResponses([
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('dns-sec-not-supported.mg')
            ),
        ]);

        $this->pdns($pdnsMock);

        $rtrService = $this->createMock(RtrService::class);

        $rtrService->expects(self::once())
            ->method('isDnssecSupported')
            ->with(self::equalTo('dns-sec-not-supported.mg'))
            ->willReturn(false);

        $rtrService
            ->method('setHandle')
            ->willReturnSelf();

        $rtrService
            ->method('setClient')
            ->willReturnSelf();

        $this->app->bind(RtrService::class, fn () => $rtrService);

        $dnssecEnablePipe = self::resolve(DnsSecEnablePipe::class);
        $validationPayload = $dnssecEnablePipe->handle(
            $validationPayload,
            fn (ValidationPayload $validationPayload): ValidationPayload => $validationPayload
        );

        self::assertSame(self::REFERENCE, $validationPayload->validationReference);
        self::assertSame(
            [
                MigrationValidationPipes::DNSSEC_ENABLE->value => [
                    [
                        'id' => MigrationValidation::DNSSEC_TLD_NOT_SUPPORTED->value,
                        'message' => 'DNSSEC not supported for dns-sec-not-supported.mg',
                    ],
                    [
                        'id' => MigrationValidation::DNSSEC_PIPE_PASSED->value,
                        'message' => 'dnssec_enable reference: unique_reference_for_adf',
                    ],
                ],
            ],
            $validationPayload->validationResults
        );
    }
}
