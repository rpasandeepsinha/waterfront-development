<?php

declare(strict_types=1);

namespace Tests\Domain\Microsoft365\Jobs;

use Exception;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\Microsoft365CustomerInfoFactory;
use Tests\Factories\Microsoft365DeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Mailer\MailTemplateInterface;
use Waterfront\Domain\Microsoft365\Enums\PrimaryDomainStatus;
use Waterfront\Domain\Microsoft365\Jobs\SetPrimaryDomainJob;
use Waterfront\Domain\Microsoft365\Mailer\Microsoft365PrimaryDomainUpdated;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;
use Waterfront\Domain\Microsoft365\Services\Microsoft365Service;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;

#[CoversClass(SetPrimaryDomainJob::class)]
#[AllowMockObjectsWithoutExpectations]
class SetPrimaryDomainJobTest extends IntegrationTestCase
{
    public const string DOMAIN = 'microsoft-primary-domain.nl';

    private Microsoft365CustomerInfo $microsoft365CustomerInfo;

    private LoggerInterface&MockObject $mockLogger;

    private Microsoft365Service&MockObject $mockMicrosoft365Service;

    private MailerInterface&MockObject $mockMailer;

    protected function setUp(): void
    {
        parent::setUp();

        $customer = CustomerFactory::new()->createOne();

        $this->microsoft365CustomerInfo = new Microsoft365CustomerInfoFactory()->for($customer)->createOne();

        $product = new ProductFactory()->for(new ProductGroupFactory()->hosting())->createOne();

        new Microsoft365DeploymentFactory()
            ->for(new SubscriptionFactory()->for($customer)->for($product)->createOne())
            ->for($this->microsoft365CustomerInfo)
            ->createOne();

        $this->mockLogger = self::createMock(LoggerInterface::class);
        $this->mockMicrosoft365Service = self::createMock(Microsoft365Service::class);
        $this->mockMailer = self::createMock(MailerInterface::class);
    }

    #[Test]
    public function setPrimaryDomainJobDispatch(): void
    {
        Queue::fake();
        Queue::assertNothingPushed();

        self::resolve(Dispatcher::class)
            ->dispatch(
                new SetPrimaryDomainJob(
                    self::DOMAIN,
                    $this->microsoft365CustomerInfo
                )
            );

        Queue::assertPushedOn(QueueName::MICROSOFT365->value, SetPrimaryDomainJob::class);
    }

    #[Test]
    public function setPrimaryDomainJobDispatchAsync(): void
    {
        Bus::fake();

        self::resolve(Dispatcher::class)
            ->dispatch(
                new SetPrimaryDomainJob(
                    self::DOMAIN,
                    $this->microsoft365CustomerInfo
                )
            );

        Bus::assertNotDispatchedSync(SetPrimaryDomainJob::class);
    }

    #[Test]
    public function failedJobSetsPrimaryDomainStatusVerificationFailed(): void
    {
        $testExceptionMessage = 'A test exception that has occurred';
        $testThrowable = new Exception($testExceptionMessage);

        $this->mockLogger->expects(self::once())
            ->method('error')
            ->with(
                'Error SetPrimaryDomainJob for domain {domain.name} job definitely failed after {queue.attempt} attempts',
                [
                    LoggingContextKeys::DOMAIN_NAME => self::DOMAIN,
                    LoggingContextKeys::QUEUE_ATTEMPT => 1,
                    LoggingContextKeys::EXCEPTION => $testThrowable,
                    LoggingContextKeys::META => [
                        'Microsoft365CustomerInfoId' => $this->microsoft365CustomerInfo->id,
                    ],
                ]
            );

        $this->app->bind(LoggerInterface::class, fn () => $this->mockLogger);

        $job = new SetPrimaryDomainJob(self::DOMAIN, $this->microsoft365CustomerInfo);

        $job->failed($testThrowable);

        self::assertSame(PrimaryDomainStatus::VERIFICATION_FAILED, $this->microsoft365CustomerInfo->refresh()->primary_domain_status);
    }

    #[Test]
    public function domainVerificationRecordsCannotBeRetrieved(): void
    {
        $this->mockLogger->expects(self::exactly(2))
            ->method('debug')
            ->with(
                ...self::withConsecutive(
                    [
                    'Starting SetPrimaryDomainJob for domain [{domain.name}], attempt {queue.attempt}/7',
                    [
                        LoggingContextKeys::DOMAIN_NAME => self::DOMAIN,
                        LoggingContextKeys::QUEUE_ATTEMPT => 1,
                        LoggingContextKeys::META => [
                            'Microsoft365CustomerInfoId' => $this->microsoft365CustomerInfo->id,
                        ],
                    ],
                ],
                    [
                    'Verification records could not be retrieved for domain [{domain.name}]',
                    [
                        LoggingContextKeys::DOMAIN_NAME => self::DOMAIN,
                        LoggingContextKeys::QUEUE_ATTEMPT => 1,
                        LoggingContextKeys::META => [
                            'Microsoft365CustomerInfoId' => $this->microsoft365CustomerInfo->id,
                        ],
                    ],
                ],
                )
            );

        $this->mockMicrosoft365Service
            ->expects(self::once())
            ->method('checkIfDomainExistsInMicrosoftAccount')
            ->with(self::DOMAIN)
            ->willReturn(true);

        $this->mockMicrosoft365Service
            ->expects(self::once())
            ->method('setVerificationDnsRecordsForPrimaryDomain')
            ->with(self::DOMAIN)
            ->willReturn(false);

        $job = new SetPrimaryDomainJob(self::DOMAIN, $this->microsoft365CustomerInfo);

        $job->handle(
            $this->mockMicrosoft365Service,
            $this->mockLogger,
            $this->mockMailer,
        );

        self::assertSame(PrimaryDomainStatus::VERIFICATION_PENDING, $this->microsoft365CustomerInfo->refresh()->primary_domain_status);
    }

    #[Test]
    public function createDomainButVerifyDomainFails(): void
    {
        $this->mockLogger->expects(self::exactly(4))
            ->method('debug')
            ->with(
                ...self::withConsecutive(
                    [
                    'Starting SetPrimaryDomainJob for domain [{domain.name}], attempt {queue.attempt}/7',
                    [
                        LoggingContextKeys::DOMAIN_NAME => self::DOMAIN,
                        LoggingContextKeys::QUEUE_ATTEMPT => 1,
                        LoggingContextKeys::META => [
                            'Microsoft365CustomerInfoId' => $this->microsoft365CustomerInfo->id,
                        ],
                    ],
                ],
                    [
                    'Creating domain [{domain.name}] in Microsoft',
                    [
                        LoggingContextKeys::DOMAIN_NAME => self::DOMAIN,
                        LoggingContextKeys::QUEUE_ATTEMPT => 1,
                        LoggingContextKeys::META => [
                            'Microsoft365CustomerInfoId' => $this->microsoft365CustomerInfo->id,
                        ],
                    ],
                ],
                    [
                    '[{domain.name}] has successfully been created in Microsoft',
                    [
                        LoggingContextKeys::DOMAIN_NAME => self::DOMAIN,
                        LoggingContextKeys::QUEUE_ATTEMPT => 1,
                        LoggingContextKeys::META => [
                            'Microsoft365CustomerInfoId' => $this->microsoft365CustomerInfo->id,
                        ],
                    ],
                ],
                    [
                    'Verification did not change the verify status or domain does not exist [{domain.name}]',
                    [
                        LoggingContextKeys::DOMAIN_NAME => self::DOMAIN,
                        LoggingContextKeys::QUEUE_ATTEMPT => 1,
                        LoggingContextKeys::META => [
                            'Microsoft365CustomerInfoId' => $this->microsoft365CustomerInfo->id,
                        ],
                    ],
                ]
                )
            );

        $this->mockMicrosoft365Service
            ->expects(self::once())
            ->method('checkIfDomainExistsInMicrosoftAccount')
            ->with(self::DOMAIN)
            ->willReturn(false);

        $this->mockMicrosoft365Service
            ->expects(self::once())
            ->method('createDomainInMicrosoftAccount')
            ->with(self::DOMAIN)
            ->willReturn(true);

        $this->mockMicrosoft365Service
            ->expects(self::once())
            ->method('setVerificationDnsRecordsForPrimaryDomain')
            ->with(self::DOMAIN)
            ->willReturn(true);

        $this->mockMicrosoft365Service
            ->expects(self::once())
            ->method('verifyDomainInMicrosoftAccount')
            ->with(self::DOMAIN)
            ->willReturn(false);

        $job = new SetPrimaryDomainJob(self::DOMAIN, $this->microsoft365CustomerInfo);

        $job->handle(
            $this->mockMicrosoft365Service,
            $this->mockLogger,
            $this->mockMailer,
        );

        self::assertSame(PrimaryDomainStatus::VERIFICATION_PENDING, $this->microsoft365CustomerInfo->refresh()->primary_domain_status);
    }

    #[Test]
    public function promotionOfVerifiedDomainFails(): void
    {
        $this->mockLogger->expects(self::exactly(2))
            ->method('debug')
            ->with(
                ...self::withConsecutive(
                    [
                    'Starting SetPrimaryDomainJob for domain [{domain.name}], attempt {queue.attempt}/7',
                    [
                        LoggingContextKeys::DOMAIN_NAME => self::DOMAIN,
                        LoggingContextKeys::QUEUE_ATTEMPT => 1,
                        LoggingContextKeys::META => [
                            'Microsoft365CustomerInfoId' => $this->microsoft365CustomerInfo->id,
                        ],
                    ],
                ],
                    [
                    'Promotion did not change the domain to a default root domain [{domain.name}]',
                    [
                        LoggingContextKeys::DOMAIN_NAME => self::DOMAIN,
                        LoggingContextKeys::QUEUE_ATTEMPT => 1,
                        LoggingContextKeys::META => [
                            'Microsoft365CustomerInfoId' => $this->microsoft365CustomerInfo->id,
                        ],
                    ],
                ],
                )
            );

        $this->mockMicrosoft365Service
            ->expects(self::once())
            ->method('checkIfDomainExistsInMicrosoftAccount')
            ->with(self::DOMAIN)
            ->willReturn(true);

        $this->mockMicrosoft365Service
            ->expects(self::once())
            ->method('setVerificationDnsRecordsForPrimaryDomain')
            ->with(self::DOMAIN)
            ->willReturn(true);

        $this->mockMicrosoft365Service
            ->expects(self::once())
            ->method('verifyDomainInMicrosoftAccount')
            ->with(self::DOMAIN)
            ->willReturn(true);

        $this->mockMicrosoft365Service
            ->expects(self::once())
            ->method('promoteDomainInMicrosoftAccount')
            ->with(self::DOMAIN)
            ->willReturn(false);

        $job = new SetPrimaryDomainJob(self::DOMAIN, $this->microsoft365CustomerInfo);

        $job->handle(
            $this->mockMicrosoft365Service,
            $this->mockLogger,
            $this->mockMailer,
        );

        self::assertSame(PrimaryDomainStatus::VERIFIED, $this->microsoft365CustomerInfo->refresh()->primary_domain_status);
    }

    #[Test]
    public function setDomainAsDefaultDomainFails(): void
    {
        $this->mockLogger->expects(self::exactly(2))
            ->method('debug')
            ->with(
                ...self::withConsecutive(
                    [
                    'Starting SetPrimaryDomainJob for domain [{domain.name}], attempt {queue.attempt}/7',
                    [
                        LoggingContextKeys::DOMAIN_NAME => self::DOMAIN,
                        LoggingContextKeys::QUEUE_ATTEMPT => 1,
                        LoggingContextKeys::META => [
                            'Microsoft365CustomerInfoId' => $this->microsoft365CustomerInfo->id,
                        ],
                    ],
                ],
                    [
                    'Failed to set domain [{domain.name}] as default domain',
                    [
                        LoggingContextKeys::DOMAIN_NAME => self::DOMAIN,
                        LoggingContextKeys::QUEUE_ATTEMPT => 1,
                        LoggingContextKeys::META => [
                            'Microsoft365CustomerInfoId' => $this->microsoft365CustomerInfo->id,
                        ],
                    ],
                ],
                )
            );

        $this->mockMicrosoft365Service
            ->expects(self::once())
            ->method('checkIfDomainExistsInMicrosoftAccount')
            ->with(self::DOMAIN)
            ->willReturn(true);

        $this->mockMicrosoft365Service
            ->expects(self::once())
            ->method('setVerificationDnsRecordsForPrimaryDomain')
            ->with(self::DOMAIN)
            ->willReturn(true);

        $this->mockMicrosoft365Service
            ->expects(self::once())
            ->method('verifyDomainInMicrosoftAccount')
            ->with(self::DOMAIN)
            ->willReturn(true);

        $this->mockMicrosoft365Service
            ->expects(self::once())
            ->method('promoteDomainInMicrosoftAccount')
            ->with(self::DOMAIN)
            ->willReturn(true);

        $this->mockMicrosoft365Service
            ->expects(self::once())
            ->method('setDomainAsDefaultDomainInMicrosoftAccount')
            ->with(self::DOMAIN)
            ->willReturn(false);

        $job = new SetPrimaryDomainJob(self::DOMAIN, $this->microsoft365CustomerInfo);

        $job->handle(
            $this->mockMicrosoft365Service,
            $this->mockLogger,
            $this->mockMailer,
        );

        self::assertSame(PrimaryDomainStatus::VERIFIED, $this->microsoft365CustomerInfo->refresh()->primary_domain_status);
    }

    #[Test]
    public function settingMxRecordsForPromotedDomainFails(): void
    {
        $this->mockLogger->expects(self::exactly(2))
            ->method('debug')
            ->with(
                ...self::withConsecutive(
                    [
                    'Starting SetPrimaryDomainJob for domain [{domain.name}], attempt {queue.attempt}/7',
                    [
                        LoggingContextKeys::DOMAIN_NAME => self::DOMAIN,
                        LoggingContextKeys::QUEUE_ATTEMPT => 1,
                        LoggingContextKeys::META => [
                            'Microsoft365CustomerInfoId' => $this->microsoft365CustomerInfo->id,
                        ],
                    ],
                ],
                    [
                    'Verification records could not be retrieved for domain [{domain.name}]',
                    [
                        LoggingContextKeys::DOMAIN_NAME => self::DOMAIN,
                        LoggingContextKeys::QUEUE_ATTEMPT => 1,
                        LoggingContextKeys::META => [
                            'Microsoft365CustomerInfoId' => $this->microsoft365CustomerInfo->id,
                        ],
                    ],
                ],
                )
            );

        $this->mockMicrosoft365Service
            ->expects(self::once())
            ->method('checkIfDomainExistsInMicrosoftAccount')
            ->with(self::DOMAIN)
            ->willReturn(true);

        $this->mockMicrosoft365Service
            ->expects(self::once())
            ->method('setVerificationDnsRecordsForPrimaryDomain')
            ->with(self::DOMAIN)
            ->willReturn(true);

        $this->mockMicrosoft365Service
            ->expects(self::once())
            ->method('verifyDomainInMicrosoftAccount')
            ->with(self::DOMAIN)
            ->willReturn(true);

        $this->mockMicrosoft365Service
            ->expects(self::once())
            ->method('promoteDomainInMicrosoftAccount')
            ->with(self::DOMAIN)
            ->willReturn(true);

        $this->mockMicrosoft365Service
            ->expects(self::once())
            ->method('setDomainAsDefaultDomainInMicrosoftAccount')
            ->with(self::DOMAIN)
            ->willReturn(true);

        $this->mockMicrosoft365Service
            ->expects(self::once())
            ->method('setServiceConfigurationRecordsForPrimaryDomain')
            ->with(self::DOMAIN)
            ->willReturn(false);

        $job = new SetPrimaryDomainJob(self::DOMAIN, $this->microsoft365CustomerInfo);

        $job->handle(
            $this->mockMicrosoft365Service,
            $this->mockLogger,
            $this->mockMailer,
        );

        self::assertSame(PrimaryDomainStatus::VERIFIED, $this->microsoft365CustomerInfo->refresh()->primary_domain_status);
    }

    #[Test]
    public function handleSuccessWithPrimaryDomainSet(): void
    {
        $this->mockLogger->expects(self::once())
            ->method('debug')
            ->with(
                'Starting SetPrimaryDomainJob for domain [{domain.name}], attempt {queue.attempt}/7',
                [
                    LoggingContextKeys::DOMAIN_NAME => self::DOMAIN,
                    LoggingContextKeys::QUEUE_ATTEMPT => 1,
                    LoggingContextKeys::META => [
                        'Microsoft365CustomerInfoId' => $this->microsoft365CustomerInfo->id,
                    ],
                ]
            );

        $this->mockMicrosoft365Service
            ->expects(self::once())
            ->method('checkIfDomainExistsInMicrosoftAccount')
            ->with(self::DOMAIN)
            ->willReturn(true);

        $this->mockMicrosoft365Service
            ->expects(self::once())
            ->method('setVerificationDnsRecordsForPrimaryDomain')
            ->with(self::DOMAIN)
            ->willReturn(true);

        $this->mockMicrosoft365Service
            ->expects(self::once())
            ->method('verifyDomainInMicrosoftAccount')
            ->with(self::DOMAIN)
            ->willReturn(true);

        $this->mockMicrosoft365Service
            ->expects(self::once())
            ->method('promoteDomainInMicrosoftAccount')
            ->with(self::DOMAIN)
            ->willReturn(true);

        $this->mockMicrosoft365Service
            ->expects(self::once())
            ->method('setDomainAsDefaultDomainInMicrosoftAccount')
            ->with(self::DOMAIN)
            ->willReturn(true);

        $this->mockMicrosoft365Service
            ->expects(self::once())
            ->method('setServiceConfigurationRecordsForPrimaryDomain')
            ->with(self::DOMAIN)
            ->willReturn(true);

        $this->mockMailer->expects(self::once())
            ->method('send')
            ->with(
                self::callback(
                    fn (array $recipients): bool => count($recipients) === 1
                        && $recipients[0]->getEmail() === $this->microsoft365CustomerInfo->customer->email
                ),
                self::callback(
                    fn (MailTemplateInterface $template): bool => $template::class === Microsoft365PrimaryDomainUpdated::class
                )
            );

        $job = new SetPrimaryDomainJob(self::DOMAIN, $this->microsoft365CustomerInfo);

        $job->handle(
            $this->mockMicrosoft365Service,
            $this->mockLogger,
            $this->mockMailer,
        );

        self::assertSame(PrimaryDomainStatus::ACTIVE, $this->microsoft365CustomerInfo->refresh()->primary_domain_status);
        self::assertSame(self::DOMAIN, $this->microsoft365CustomerInfo->refresh()->primary_domain);
    }

    #[Test]
    public function resolvesAndPersistsTenantIdWhenMissing(): void
    {
        $resolvedTenantId = 'd0d00399-2989-4ded-b811-e58ec764d0e8';
        $tenantName = 'contoso.onmicrosoft.com';

        $this->microsoft365CustomerInfo->tenant_id = null;
        $this->microsoft365CustomerInfo->tenant_name = $tenantName;
        $this->microsoft365CustomerInfo->save();

        $this->mockMicrosoft365Service
            ->expects(self::once())
            ->method('getTenantIdByName')
            ->with($tenantName)
            ->willReturn($resolvedTenantId);

        $this->mockMicrosoft365Service
            ->expects(self::once())
            ->method('checkIfDomainExistsInMicrosoftAccount')
            ->with(self::DOMAIN)
            ->willReturn(true);

        $this->mockMicrosoft365Service
            ->expects(self::once())
            ->method('setVerificationDnsRecordsForPrimaryDomain')
            ->with(self::DOMAIN)
            ->willReturn(true);

        $this->mockMicrosoft365Service
            ->expects(self::once())
            ->method('verifyDomainInMicrosoftAccount')
            ->with(self::DOMAIN)
            ->willReturn(true);

        $this->mockMicrosoft365Service
            ->expects(self::once())
            ->method('promoteDomainInMicrosoftAccount')
            ->with(self::DOMAIN)
            ->willReturn(true);

        $this->mockMicrosoft365Service
            ->expects(self::once())
            ->method('setDomainAsDefaultDomainInMicrosoftAccount')
            ->with(self::DOMAIN)
            ->willReturn(true);

        $this->mockMicrosoft365Service
            ->expects(self::once())
            ->method('setServiceConfigurationRecordsForPrimaryDomain')
            ->with(self::DOMAIN)
            ->willReturn(true);

        $job = new SetPrimaryDomainJob(self::DOMAIN, $this->microsoft365CustomerInfo);

        $job->handle(
            $this->mockMicrosoft365Service,
            $this->mockLogger,
            $this->mockMailer,
        );

        self::assertSame($resolvedTenantId, $this->microsoft365CustomerInfo->refresh()->tenant_id);
        self::assertSame(PrimaryDomainStatus::ACTIVE, $this->microsoft365CustomerInfo->refresh()->primary_domain_status);
    }

    #[Test]
    public function releasesForRetryWhenTenantIdCannotBeResolved(): void
    {
        $tenantName = 'contoso.onmicrosoft.com';

        $this->microsoft365CustomerInfo->tenant_id = null;
        $this->microsoft365CustomerInfo->tenant_name = $tenantName;
        $this->microsoft365CustomerInfo->save();

        $this->mockLogger->expects(self::once())
            ->method('warning')
            ->with(
                'Could not resolve tenant id for domain [{domain.name}], retrying',
                [
                    LoggingContextKeys::DOMAIN_NAME => self::DOMAIN,
                    LoggingContextKeys::QUEUE_ATTEMPT => 1,
                    LoggingContextKeys::META => [
                        'Microsoft365CustomerInfoId' => $this->microsoft365CustomerInfo->id,
                    ],
                ]
            );

        $this->mockMicrosoft365Service
            ->expects(self::once())
            ->method('getTenantIdByName')
            ->with($tenantName)
            ->willReturn(null);

        $this->mockMicrosoft365Service
            ->expects(self::never())
            ->method('checkIfDomainExistsInMicrosoftAccount');

        $job = new SetPrimaryDomainJob(self::DOMAIN, $this->microsoft365CustomerInfo);

        $job->handle(
            $this->mockMicrosoft365Service,
            $this->mockLogger,
            $this->mockMailer,
        );

        self::assertNull($this->microsoft365CustomerInfo->refresh()->tenant_id);
    }

    #[Test]
    public function setPrimaryDomainFailsToAddDomain(): void
    {
        $this->mockMicrosoft365Service->expects(self::once())
            ->method('checkIfDomainExistsInMicrosoftAccount')
            ->willReturn(false);

        $this->mockMicrosoft365Service->expects(self::once())
            ->method('createDomainInMicrosoftAccount')
            ->willReturn(false);

        $this->mockMicrosoft365Service->expects(self::never())
            ->method('setVerificationDnsRecordsForPrimaryDomain');

        $job = new SetPrimaryDomainJob(self::DOMAIN, $this->microsoft365CustomerInfo);

        $job->handle(
            $this->mockMicrosoft365Service,
            $this->mockLogger,
            $this->mockMailer,
        );
    }
}
