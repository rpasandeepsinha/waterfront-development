<?php

declare(strict_types=1);

namespace Tests\Domain\Domains\Jobs;

use Exception;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\Domains\DTO\DomainDetailsDTO;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Domains\Factories\DomainServiceFactory;
use Waterfront\Domain\Domains\Interfaces\DomainDriverInterface;
use Waterfront\Domain\Domains\Jobs\UpdateDomainNameRegistrationJob;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Infra\RtrClient\Services\Enums\DomainStatus as RtrDomainStatus;
use Waterfront\Infra\RtrClient\Services\RtrService;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(UpdateDomainNameRegistrationJob::class)]
#[AllowMockObjectsWithoutExpectations]
class UpdateDomainNameRegistrationJobTest extends IntegrationTestCase
{
    public const string NS1 = 'ns1.testnameserver.nl';
    public const string NS2 = 'ns2.testnameserver.nl';

    #[Test]
    public function updateNameserverAndDnsSecForDomain(): void
    {
        $testNameservers = [
            new Nameserver(self::NS1),
            new Nameserver(self::NS2),
        ];
        $domainSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->nlDomain())
            ->has(new DomainDeploymentFactory()->for(new ProviderFactory()->domainOpenProvider()->createOne()))
            ->technicalStatusPending()
            ->createOne();

        self::assertNotNull($domainSubscription->domainDeployment);

        $job = new UpdateDomainNameRegistrationJob(
            domainDeployment: $domainSubscription->domainDeployment,
            nameservers: $testNameservers
        );

        $mockLogger = self::createMock(LoggerInterface::class);
        $mockDomainDriver = self::createMock(DomainDriverInterface::class);
        $mockDomainServiceFactory = self::createMock(DomainServiceFactory::class);

        $mockLogger->expects(self::once())
            ->method('info')
            ->with('UpdateDomainRegistrationJob started for domain {domain.name}', [
                LoggingContextKeys::DOMAIN_NAME => $domainSubscription->domain,
                LoggingContextKeys::SUBSCRIPTION_UUID => $domainSubscription->uuid,
                LoggingContextKeys::SUBSCRIPTION_ID => $domainSubscription->id,
                LoggingContextKeys::PROVISIONING_TYPE => 'domain',
            ]);

        $mockDomainServiceFactory->expects(self::once())
            ->method('driver')
            ->with($domainSubscription->domainDeployment->provider->slug)
            ->willReturn($mockDomainDriver);

        $expectedNameserverHostnames = [self::NS1, self::NS2];

        $mockDomainDriver->expects(self::once())
            ->method('modify')
            ->with($domainSubscription->domain, [
                'dnssecKeys' => null,
                'ns' => $expectedNameserverHostnames,
            ]);

        $job->handle($mockDomainServiceFactory, $mockLogger);

        $domainSubscription->refresh();
        self::assertNotNull($domainSubscription->domainDeployment);
        $domainSubscription->domainDeployment->refresh();

        self::assertSame(DomainStatus::ACTIVE->value, $domainSubscription->technical_status);
        self::assertNotNull($domainSubscription->domainDeployment->last_result_received);
        self::assertSame(
            [
                'message' => sprintf('Successfully updated domain name registration for domain %s', $domainSubscription->domain),
                'data' => [
                    'dnssec' => $domainSubscription->domainDeployment->dnssec_enabled,
                    'ns' => $expectedNameserverHostnames,
                ],
            ],
            json_decode($domainSubscription->domainDeployment->last_result ?? '', true)
        );
    }

    #[DataProvider('updateReadyTechnicalStatusDataProvider')]
    #[Test]
    public function updateActivatesUpdateReadyTechnicalStatus(string $technicalStatus): void
    {
        $testNameservers = [
            new Nameserver(self::NS1),
            new Nameserver(self::NS2),
        ];
        $domainSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->nlDomain())
            ->has(new DomainDeploymentFactory()->for(new ProviderFactory()->domainOpenProvider()->createOne()))
            ->technicalStatus($technicalStatus)
            ->createOne();

        self::assertNotNull($domainSubscription->domainDeployment);

        $job = new UpdateDomainNameRegistrationJob(
            domainDeployment: $domainSubscription->domainDeployment,
            nameservers: $testNameservers
        );

        $mockDomainDriver = self::createMock(DomainDriverInterface::class);
        $mockDomainDriver->expects(self::once())
            ->method('modify');

        $mockDomainServiceFactory = self::createStub(DomainServiceFactory::class);
        $mockDomainServiceFactory->method('driver')
            ->willReturn($mockDomainDriver);

        $job->handle($mockDomainServiceFactory, self::createStub(LoggerInterface::class));

        self::assertSame(DomainStatus::ACTIVE->value, $domainSubscription->refresh()->technical_status);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function updateReadyTechnicalStatusDataProvider(): array
    {
        return [
            'generic pending' => [TechnicalStatus::PENDING->value],
            'generic ok' => [TechnicalStatus::OK->value],
            'domain pending' => [DomainStatus::PENDING->value],
            'domain active' => [DomainStatus::ACTIVE->value],
        ];
    }

    #[Test]
    public function updateDoesNotActivateWhenRefreshedRtrStatusIsPendingValidation(): void
    {
        $testNameservers = [
            new Nameserver(self::NS1),
            new Nameserver(self::NS2),
        ];
        $domainSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->nlDomain())
            ->has(new DomainDeploymentFactory()
                ->withRtrProvider()
                ->withRtrDomainStatus(RtrDomainStatus::PENDING_VALIDATION))
            ->technicalStatus(TechnicalStatus::PENDING->value)
            ->createOne();

        self::assertNotNull($domainSubscription->domainDeployment);

        $job = new UpdateDomainNameRegistrationJob(
            domainDeployment: $domainSubscription->domainDeployment,
            nameservers: $testNameservers
        );

        $mockDomainDriver = self::createMock(RtrService::class);
        $mockDomainDriver->expects(self::once())
            ->method('modify');
        $mockDomainDriver->expects(self::once())
            ->method('fetchDomain')
            ->with($domainSubscription->domain)
            ->willReturn(
                new DomainDetailsDTO(
                    domainName: 'test-domain.nl',
                    registrant: 'test-handle',
                    status: [RtrDomainStatus::PENDING_VALIDATION->value],
                    autoRenew: true,
                    autoRenewPeriod: 12,
                    ns: [],
                    premium: false,
                )
            );
        $mockDomainDriver->expects(self::once())
            ->method('getPrimaryDomainStatusFromDomainStatusList')
            ->with([RtrDomainStatus::PENDING_VALIDATION->value])
            ->willReturn(RtrDomainStatus::PENDING_VALIDATION);

        $mockDomainServiceFactory = self::createStub(DomainServiceFactory::class);
        $mockDomainServiceFactory->method('driver')
            ->willReturn($mockDomainDriver);

        $job->handle($mockDomainServiceFactory, self::createStub(LoggerInterface::class));

        self::assertSame(TechnicalStatus::PENDING->value, $domainSubscription->refresh()->technical_status);
        self::assertSame(RtrDomainStatus::PENDING_VALIDATION, $domainSubscription->domainDeployment?->refresh()->domain_status);
    }

    #[DataProvider('activationReadyRtrDomainStatusDataProvider')]
    #[Test]
    public function updateActivatesPendingValidationDomainWhenRefreshedDomainStatusIsActivationReady(RtrDomainStatus $rtrDomainStatus): void
    {
        $testNameservers = [
            new Nameserver(self::NS1),
            new Nameserver(self::NS2),
        ];
        $domainSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->nlDomain())
            ->has(new DomainDeploymentFactory()
                ->withRtrProvider()
                ->withRtrDomainStatus(RtrDomainStatus::PENDING_VALIDATION))
            ->technicalStatus(TechnicalStatus::PENDING->value)
            ->createOne();

        self::assertNotNull($domainSubscription->domainDeployment);

        $job = new UpdateDomainNameRegistrationJob(
            domainDeployment: $domainSubscription->domainDeployment,
            nameservers: $testNameservers
        );

        $mockDomainDriver = self::createMock(RtrService::class);
        $mockDomainDriver->expects(self::once())
            ->method('modify');
        $mockDomainDriver->expects(self::once())
            ->method('fetchDomain')
            ->with($domainSubscription->domain)
            ->willReturn(
                new DomainDetailsDTO(
                    domainName: 'test-domain.nl',
                    registrant: 'test-handle',
                    status: [$rtrDomainStatus->value],
                    autoRenew: true,
                    autoRenewPeriod: 12,
                    ns: [],
                    premium: false,
                )
            );
        $mockDomainDriver->expects(self::once())
            ->method('getPrimaryDomainStatusFromDomainStatusList')
            ->with([$rtrDomainStatus->value])
            ->willReturn($rtrDomainStatus);

        $mockDomainServiceFactory = self::createStub(DomainServiceFactory::class);
        $mockDomainServiceFactory->method('driver')
            ->willReturn($mockDomainDriver);

        $job->handle($mockDomainServiceFactory, self::createStub(LoggerInterface::class));

        self::assertSame(DomainStatus::ACTIVE->value, $domainSubscription->refresh()->technical_status);
        self::assertSame($rtrDomainStatus, $domainSubscription->domainDeployment?->refresh()->domain_status);
    }

    /**
     * @return array<string, array{RtrDomainStatus}>
     */
    public static function activationReadyRtrDomainStatusDataProvider(): array
    {
        return [
            'ok' => [RtrDomainStatus::OK],
            'inactive' => [RtrDomainStatus::INACTIVE],
        ];
    }

    #[Test]
    public function updateRefreshesRtrDomainStatusWhenStatusIsNull(): void
    {
        $testNameservers = [
            new Nameserver(self::NS1),
            new Nameserver(self::NS2),
        ];
        $domainSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->nlDomain())
            ->has(new DomainDeploymentFactory()->withRtrProvider())
            ->technicalStatus(TechnicalStatus::PENDING->value)
            ->createOne();

        self::assertNotNull($domainSubscription->domainDeployment);
        self::assertNull($domainSubscription->domainDeployment->domain_status);

        $job = new UpdateDomainNameRegistrationJob(
            domainDeployment: $domainSubscription->domainDeployment,
            nameservers: $testNameservers,
        );

        $mockDomainDriver = self::createMock(RtrService::class);
        $mockDomainDriver->expects(self::once())
            ->method('modify');
        $mockDomainDriver->expects(self::once())
            ->method('fetchDomain')
            ->with($domainSubscription->domain)
            ->willReturn(
                new DomainDetailsDTO(
                    domainName: 'test-domain.nl',
                    registrant: 'test-handle',
                    status: [RtrDomainStatus::OK->value],
                    autoRenew: true,
                    autoRenewPeriod: 12,
                    ns: [],
                    premium: false,
                )
            );
        $mockDomainDriver->expects(self::once())
            ->method('getPrimaryDomainStatusFromDomainStatusList')
            ->with([RtrDomainStatus::OK->value])
            ->willReturn(RtrDomainStatus::OK);

        $mockDomainServiceFactory = self::createStub(DomainServiceFactory::class);
        $mockDomainServiceFactory->method('driver')
            ->willReturn($mockDomainDriver);

        $job->handle(
            $mockDomainServiceFactory,
            self::createStub(LoggerInterface::class),
        );

        self::assertSame(DomainStatus::ACTIVE->value, $domainSubscription->refresh()->technical_status);
        self::assertSame(RtrDomainStatus::OK, $domainSubscription->domainDeployment?->refresh()->domain_status);
    }

    #[Test]
    public function updateDoesNotActivatePendingValidationDomainWhenRefreshedDomainStatusIsProhibited(): void
    {
        $testNameservers = [
            new Nameserver(self::NS1),
            new Nameserver(self::NS2),
        ];
        $domainSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->nlDomain())
            ->has(new DomainDeploymentFactory()
                ->withRtrProvider()
                ->withRtrDomainStatus(RtrDomainStatus::PENDING_VALIDATION))
            ->technicalStatus(TechnicalStatus::PENDING->value)
            ->createOne();

        self::assertNotNull($domainSubscription->domainDeployment);

        $job = new UpdateDomainNameRegistrationJob(
            domainDeployment: $domainSubscription->domainDeployment,
            nameservers: $testNameservers
        );

        $mockDomainDriver = self::createMock(RtrService::class);
        $mockDomainDriver->expects(self::once())
            ->method('modify');
        $mockDomainDriver->expects(self::once())
            ->method('fetchDomain')
            ->with($domainSubscription->domain)
            ->willReturn(
                new DomainDetailsDTO(
                    domainName: 'test-domain.nl',
                    registrant: 'test-handle',
                    status: [RtrDomainStatus::SERVER_UPDATE_PROHIBITED->value],
                    autoRenew: true,
                    autoRenewPeriod: 12,
                    ns: [],
                    premium: false,
                )
            );
        $mockDomainDriver->expects(self::once())
            ->method('getPrimaryDomainStatusFromDomainStatusList')
            ->with([RtrDomainStatus::SERVER_UPDATE_PROHIBITED->value])
            ->willReturn(RtrDomainStatus::SERVER_UPDATE_PROHIBITED);

        $mockDomainServiceFactory = self::createStub(DomainServiceFactory::class);
        $mockDomainServiceFactory->method('driver')
            ->willReturn($mockDomainDriver);

        $job->handle($mockDomainServiceFactory, self::createStub(LoggerInterface::class));

        self::assertSame(TechnicalStatus::PENDING->value, $domainSubscription->refresh()->technical_status);
        self::assertSame(RtrDomainStatus::SERVER_UPDATE_PROHIBITED, $domainSubscription->domainDeployment?->refresh()->domain_status);
    }

    #[DataProvider('nonUpdateReadyTechnicalStatusDataProvider')]
    #[Test]
    public function updateDoesNotActivateNonUpdateReadyTechnicalStatus(string $technicalStatus): void
    {
        $testNameservers = [
            new Nameserver(self::NS1),
            new Nameserver(self::NS2),
        ];
        $domainSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->nlDomain())
            ->has(new DomainDeploymentFactory()->for(new ProviderFactory()->domainOpenProvider()->createOne()))
            ->technicalStatus($technicalStatus)
            ->createOne();

        self::assertNotNull($domainSubscription->domainDeployment);

        $job = new UpdateDomainNameRegistrationJob(
            domainDeployment: $domainSubscription->domainDeployment,
            nameservers: $testNameservers
        );

        $mockDomainDriver = self::createMock(DomainDriverInterface::class);
        $mockDomainDriver->expects(self::once())
            ->method('modify');

        $mockDomainServiceFactory = self::createStub(DomainServiceFactory::class);
        $mockDomainServiceFactory->method('driver')
            ->willReturn($mockDomainDriver);

        $job->handle($mockDomainServiceFactory, self::createStub(LoggerInterface::class));

        self::assertSame($technicalStatus, $domainSubscription->refresh()->technical_status);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonUpdateReadyTechnicalStatusDataProvider(): array
    {
        return [
            'requested' => [DomainStatus::REQUESTED->value],
            'domain failed' => [DomainStatus::FAILED->value],
            'generic failed' => [TechnicalStatus::FAILED->value],
            'transfer failed' => [TechnicalStatus::TRANSFER_FAILED->value],
        ];
    }

    #[Test]
    public function failedJobSetsTechnicalStatusToFailedAndLastResultOnDeployment(): void
    {
        $testNameservers = [
            new Nameserver(self::NS1),
            new Nameserver(self::NS2),
        ];
        $testExceptionMessage = 'A test exception that has occured';
        $testThrowable = new Exception($testExceptionMessage);

        $testDomain = 'test-update-domain-nameserver-job.nl';
        $domainSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->nlDomain())
            ->forDomain($testDomain)
            ->has(new DomainDeploymentFactory()
                ->for(new ProviderFactory()->domainOpenProvider()->createOne())
                ->state([
                    'last_result' => null,
                    'last_result_received' => null,
                ]), 'domainDeployment')
            ->createOne();

        $mockLogger = self::createMock(LoggerInterface::class);
        $this->app->bind(LoggerInterface::class, fn () => $mockLogger);

        $mockLogger->expects(self::once())
            ->method('error')
            ->with(
                'Error UpdateDomainRegistrationJob for domain {domain.name} job definitely failed after {job.attempt} attempts',
                [
                    LoggingContextKeys::DOMAIN_NAME => $testDomain,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $domainSubscription->uuid,
                    LoggingContextKeys::SUBSCRIPTION_ID => $domainSubscription->id,
                    LoggingContextKeys::EXCEPTION => $testThrowable,
                    LoggingContextKeys::PROVISIONING_TYPE => 'domain',
                    LoggingContextKeys::QUEUE_ATTEMPT => 1,
                ]
            );

        self::assertNotNull($domainSubscription->domainDeployment);

        $job = new UpdateDomainNameRegistrationJob(
            domainDeployment: $domainSubscription->domainDeployment,
            nameservers: $testNameservers,
        );

        $job->failed($testThrowable);

        self::assertSame(TechnicalStatus::FAILED->value, $domainSubscription->refresh()->technical_status);
        self::assertNotNull($domainSubscription->domainDeployment?->last_result_received);
        self::assertSame(
            [
                'message' => 'Domain is registered but failed to update nameservers, dnssec, and private whois',
                'exception' => $testExceptionMessage,
                'trace' => $testThrowable->getTraceAsString(),
            ],
            json_decode($domainSubscription->domainDeployment->last_result ?? '', true)
        );
    }
}
