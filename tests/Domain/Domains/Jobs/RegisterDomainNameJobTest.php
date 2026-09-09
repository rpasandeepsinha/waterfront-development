<?php

declare(strict_types=1);

namespace Tests\Domain\Domains\Jobs;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Exception;
use Psr\Log\LoggerInterface;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\DTO\RegistrationResult;
use Waterfront\Domain\Domains\DTO\TransferResult;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Domains\Jobs\RegisterDomainNameJob;
use Waterfront\Domain\Domains\Mailers\MailDomainCreationFailed;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Provision\DomainNames\Exceptions\RegisterDomainNameException;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(RegisterDomainNameJob::class)]
class RegisterDomainNameJobTest extends IntegrationTestCase
{
    public const string DOMAIN = 'register-domain-job-test.nl';

    /**
     * @throws Exception
     */
    #[Test]
    public function registerWithoutDomainHandlesFailedJob(): void
    {
        $domainSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->nlDomain())
            ->has(new DomainDeploymentFactory()->for(new ProviderFactory()->domainOpenProvider()->createOne())->state([
                'last_result' => null,
                'last_result_received' => null,
                'transfer_secret' => null,
                'dnssec_enabled' => false,
                'private_whois_enabled' => false,
            ]), 'domainDeployment')
            ->createOne([
                'domain' => null,
            ]);

        $exception = new RegisterDomainNameException(
            sprintf(
                'Error registering domain: Domain is missing from Subscription (%s)',
                $domainSubscription->uuid
            )
        );

        $expectedLastResult = json_encode([
            'message'   => 'Domain registration failed',
            'exception' => $exception->getMessage(),
            'trace'     => $exception->getTraceAsString(),
        ]);

        $mockLogger = $this->createMock(LoggerInterface::class);
        $this->app->bind(LoggerInterface::class, fn () => $mockLogger);

        $mockLogger->expects(self::once())
        ->method('error')
        ->with(
            'Error RegisterDomainJob ({transfer_or_registration}) for domain {domain.name} job definitely failed after {job.attempt} attempts',
            [
                LoggingContextKeys::DOMAIN_NAME => null,
                LoggingContextKeys::SUBSCRIPTION_UUID => $domainSubscription->uuid,
                LoggingContextKeys::SUBSCRIPTION_ID => $domainSubscription->id,
                LoggingContextKeys::EXCEPTION => $exception,
                LoggingContextKeys::PROVISIONING_TYPE => 'domain.registration',
                LoggingContextKeys::QUEUE_ATTEMPT => 1,
            ]
        );

        $mockMailer = $this->createMock(MailerInterface::class);
        $this->app->bind(MailerInterface::class, fn () => $mockMailer);

        $mockMailer->expects(self::once())
            ->method('send')
            ->with(
                [$domainSubscription->customer],
                new MailDomainCreationFailed('-', 'rtr-error.general')
            );

        self::assertNotNull($domainSubscription->domainDeployment);

        $job = new RegisterDomainNameJob($domainSubscription->domainDeployment);
        $job->failed($exception);

        $domainSubscription->refresh();

        self::assertSame(TechnicalStatus::FAILED->value, $domainSubscription->technical_status);
        self::assertNotNull($domainSubscription->domainDeployment?->last_result_received);
        self::assertSame(
            $expectedLastResult,
            $domainSubscription->domainDeployment->last_result
        );
    }

    #[Test]
    public function domainHandlesFailedTransferJob(): void
    {
        $domainSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->nlDomain())
            ->has(new DomainDeploymentFactory()->for(new ProviderFactory()->domainOpenProvider()->createOne())->state([
                'last_result' => null,
                'last_result_received' => null,
                'transfer_secret' => null,
                'dnssec_enabled' => false,
                'private_whois_enabled' => false,
            ]), 'domainDeployment')
            ->createOne([
                'domain' => null,
                'technical_status' => TechnicalStatus::TRANSFER_FAILED->value,
            ]);

        $mockLogger = $this->createStub(LoggerInterface::class);
        $this->app->bind(LoggerInterface::class, fn () => $mockLogger);

        $mockMailer = $this->createMock(MailerInterface::class);
        $this->app->bind(MailerInterface::class, fn () => $mockMailer);

        $mockMailer->expects(self::never())
            ->method('send');

        self::assertNotNull($domainSubscription->domainDeployment);

        $job = new RegisterDomainNameJob($domainSubscription->domainDeployment);
        $job->handle(self::resolve(DomainService::class), self::resolve(SubscriptionRepository::class), self::resolve(LoggerInterface::class));

        $domainSubscription->refresh();

        self::assertSame(TechnicalStatus::TRANSFER_FAILED->value, $domainSubscription->technical_status);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function registerWithPrivateWhoisWithoutDnsSec(): void
    {
        $domainSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->nlDomain())
            ->forDomain(self::DOMAIN)
            ->technicalStatus(TechnicalStatus::REGISTRATION->value)
            ->has(new DomainDeploymentFactory()->for(new ProviderFactory()->domainOpenProvider()->createOne())->state([
                'last_result' => null,
                'last_result_received' => null,
                'transfer_secret' => null,
                'dnssec_enabled' => false,
                'private_whois_enabled' => true,
            ]), 'domainDeployment')
            ->createOne();

        self::assertNotNull($domainSubscription->domainDeployment);

        $job = new RegisterDomainNameJob($domainSubscription->domainDeployment);

        $mockDomainService = $this->createMock(DomainService::class);
        $mockSubRepo = $this->createMock(SubscriptionRepository::class);
        $mockLogger = $this->mock(LoggerInterface::class);
        $mockRegistrationResult = self::createMock(RegistrationResult::class);

        $mockLogger->shouldReceive('info')
            ->once()
            ->with('RegisterDomainJob started for domain register-domain-job-test.nl. Registration or transfer: domain.registration', [
                LoggingContextKeys::DOMAIN_NAME => $domainSubscription->domain,
                LoggingContextKeys::SUBSCRIPTION_UUID => $domainSubscription->uuid,
                LoggingContextKeys::SUBSCRIPTION_ID => $domainSubscription->id,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
            ]);

        $mockSubRepo->expects(self::once())
            ->method('setTechnicalStatus')
            ->with(
                self::callback(fn (Subscription $receivedSubscription) => $receivedSubscription->id === $domainSubscription->id),
                TechnicalStatus::PENDING->value
            )
            ->willReturn(true);

        $mockDomainService->expects(self::once())
            ->method('register')
            ->with(
                $domainSubscription->domainDeployment,
                $domainSubscription->domain,
                $domainSubscription->contract_period,
                $domainSubscription->customer,
                $domainSubscription->domainDeployment->private_whois_enabled,
                $domainSubscription->domainDeployment->dnssec_enabled,
            )
            ->willReturn($mockRegistrationResult);

        $mockRegistrationResult->expects(self::exactly(3))
            ->method('getStatus')
            ->willReturn(DomainStatus::ACTIVE);

        $mockRegistrationResult->expects(self::exactly(3))
            ->method('getReason')
            ->willReturn('Registration successful');

        $mockLogger->shouldReceive('info')
            ->once()
            ->with(
                'RegisterDomainJob status after registration: {domain_registration.status}. Reason: {domain_registration.reason}',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domainSubscription->domain,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $domainSubscription->uuid,
                    LoggingContextKeys::SUBSCRIPTION_ID => $domainSubscription->id,
                    LoggingContextKeys::PROVISIONING_TYPE => 'domain.registration',
                    LoggingContextKeys::META => [
                        'domain_registration.status' => DomainStatus::ACTIVE->value,
                        'domain_registration.reason' => 'Registration successful',
                    ],
                ]
            );

        $job->handle($mockDomainService, $mockSubRepo, $mockLogger);
        $domainSubscription->refresh();
        self::assertSame('ACT', $domainSubscription->technical_status);
        self::assertNotNull($domainSubscription->domainDeployment?->last_result_received);
        self::assertSame('Registration successful', $domainSubscription->domainDeployment->last_result);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function registerWithoutPrivateWhoisWithoutDnsSec(): void
    {
        $domainSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->nlDomain())
            ->forDomain(self::DOMAIN)
            ->technicalStatus(TechnicalStatus::REGISTRATION->value)
            ->has(new DomainDeploymentFactory()->for(new ProviderFactory()->domainOpenProvider()->createOne())->state([
                'last_result' => null,
                'last_result_received' => null,
                'transfer_secret' => null,
                'dnssec_enabled' => false,
                'private_whois_enabled' => false,
            ]), 'domainDeployment')
            ->createOne();

        self::assertNotNull($domainSubscription->domainDeployment);

        $job = new RegisterDomainNameJob($domainSubscription->domainDeployment);

        $mockDomainService = $this->createMock(DomainService::class);
        $mockSubRepo = $this->createMock(SubscriptionRepository::class);
        $mockLogger = $this->mock(LoggerInterface::class);
        $mockRegistrationResult = self::createMock(RegistrationResult::class);

        $mockLogger->shouldReceive('info')
            ->once()
            ->with(
                'RegisterDomainJob status after registration: {domain_registration.status}. Reason: {domain_registration.reason}',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domainSubscription->domain,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $domainSubscription->uuid,
                    LoggingContextKeys::SUBSCRIPTION_ID => $domainSubscription->id,
                    LoggingContextKeys::PROVISIONING_TYPE => 'domain.registration',
                    LoggingContextKeys::META => [
                        'domain_registration.status' => 'ACT',
                        'domain_registration.reason' => 'Registration successful',
                    ],
                ]
            );

        $mockSubRepo->expects(self::once())
            ->method('setTechnicalStatus')
            ->with(
                self::callback(fn (Subscription $receivedSubscription) => $receivedSubscription->id === $domainSubscription->id),
                TechnicalStatus::PENDING->value
            )
            ->willReturn(true);

        $mockDomainService->expects(self::once())
            ->method('register')
            ->with(
                $domainSubscription->domainDeployment,
                $domainSubscription->domain,
                $domainSubscription->contract_period,
                $domainSubscription->customer,
                $domainSubscription->domainDeployment->private_whois_enabled,
                $domainSubscription->domainDeployment->dnssec_enabled,
            )
            ->willReturn($mockRegistrationResult);

        $mockRegistrationResult->expects(self::exactly(3))
            ->method('getStatus')
            ->willReturn(DomainStatus::ACTIVE);

        $mockRegistrationResult->expects(self::exactly(3))
            ->method('getReason')
            ->willReturn('Registration successful');

        $mockLogger->shouldReceive('info')
            ->once()
            ->with('RegisterDomainJob started for domain register-domain-job-test.nl. Registration or transfer: domain.registration', [
                LoggingContextKeys::DOMAIN_NAME => $domainSubscription->domain,
                LoggingContextKeys::SUBSCRIPTION_UUID => $domainSubscription->uuid,
                LoggingContextKeys::SUBSCRIPTION_ID => $domainSubscription->id,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
            ]);

        $job->handle($mockDomainService, $mockSubRepo, $mockLogger);
        $domainSubscription->refresh();
        self::assertSame('ACT', $domainSubscription->technical_status);
        self::assertNotNull($domainSubscription->domainDeployment?->last_result_received);
        self::assertSame('Registration successful', $domainSubscription->domainDeployment->last_result);
    }

    #[Test]
    public function registerPersistsPendingRegistrationResult(): void
    {
        $domainSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->nlDomain())
            ->forDomain(self::DOMAIN)
            ->technicalStatus(TechnicalStatus::REGISTRATION->value)
            ->has(new DomainDeploymentFactory()->for(new ProviderFactory()->domainOpenProvider()->createOne())->state([
                'last_result' => null,
                'last_result_received' => null,
                'transfer_secret' => null,
                'dnssec_enabled' => false,
                'private_whois_enabled' => false,
            ]), 'domainDeployment')
            ->createOne();

        self::assertNotNull($domainSubscription->domainDeployment);

        $registrationResult = new RegistrationResult(DomainStatus::PENDING);
        $registrationResult->setReason('{"domainName":"register-domain-job-test.nl"}');

        $mockDomainService = $this->createMock(DomainService::class);
        $mockDomainService->expects(self::once())
            ->method('register')
            ->willReturn($registrationResult);

        $mockSubRepo = $this->createMock(SubscriptionRepository::class);
        $mockSubRepo->expects(self::once())
            ->method('setTechnicalStatus')
            ->willReturn(true);

        $job = new RegisterDomainNameJob($domainSubscription->domainDeployment);
        $job->handle($mockDomainService, $mockSubRepo, self::createStub(LoggerInterface::class));

        $domainSubscription->refresh();

        self::assertSame(TechnicalStatus::PENDING->value, $domainSubscription->technical_status);
        self::assertNotNull($domainSubscription->domainDeployment?->last_result_received);
        self::assertSame('{"domainName":"register-domain-job-test.nl"}', $domainSubscription->domainDeployment->last_result);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function transferWithoutPrivateWhoisWithoutDnsSec(): void
    {
        $transferSecret = 'transfer-secret';
        $domainSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->nlDomain())
            ->forDomain(self::DOMAIN)
            ->technicalStatus(TechnicalStatus::REGISTRATION->value)
            ->has(new DomainDeploymentFactory()->for(new ProviderFactory()->domainOpenProvider()->createOne())->state([
                'last_result' => null,
                'last_result_received' => null,
                'transfer_secret' => $transferSecret,
                'dnssec_enabled' => false,
                'private_whois_enabled' => false,
            ]), 'domainDeployment')
            ->createOne();

        self::assertNotNull($domainSubscription->domainDeployment);

        $job = new RegisterDomainNameJob($domainSubscription->domainDeployment);

        $mockDomainService = $this->createMock(DomainService::class);
        $mockSubRepo = $this->createMock(SubscriptionRepository::class);
        $mockLogger = $this->mock(LoggerInterface::class);
        $mockTransferResult = self::createMock(TransferResult::class);

        $mockLogger->shouldReceive('info')
            ->once()
            ->with('RegisterDomainJob started for domain register-domain-job-test.nl. Registration or transfer: domain.transfer', [
                LoggingContextKeys::DOMAIN_NAME => $domainSubscription->domain,
                LoggingContextKeys::SUBSCRIPTION_UUID => $domainSubscription->uuid,
                LoggingContextKeys::SUBSCRIPTION_ID => $domainSubscription->id,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
        ]);

        $mockSubRepo->expects(self::once())
            ->method('setTechnicalStatus')
            ->with(
                self::callback(fn (Subscription $receivedSubscription) => $receivedSubscription->id === $domainSubscription->id),
                TechnicalStatus::PENDING->value
            )
            ->willReturn(true);

        $mockDomainService->expects(self::once())
            ->method('transfer')
            ->with(
                $domainSubscription->domainDeployment,
                $domainSubscription->domain,
                $domainSubscription->contract_period,
                $domainSubscription->customer,
                $domainSubscription->domainDeployment->private_whois_enabled,
                $domainSubscription->domainDeployment->dnssec_enabled,
                $transferSecret
            )
            ->willReturn($mockTransferResult);

        $mockTransferResult->expects(self::exactly(3))
            ->method('getStatus')
            ->willReturn(TechnicalStatus::OK->value);

        $mockTransferResult->expects(self::exactly(3))
            ->method('getReason')
            ->willReturn('Transfer successful');

        $mockLogger->shouldReceive('info')
            ->once()
            ->with(
                'RegisterDomainJob status after transfer: {status}. Reason: {reason}',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domainSubscription->domain,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $domainSubscription->uuid,
                    LoggingContextKeys::SUBSCRIPTION_ID => $domainSubscription->id,
                    LoggingContextKeys::PROVISIONING_TYPE => 'domain.transfer',
                    LoggingContextKeys::META => [
                        'domain_transfer.status' => TechnicalStatus::OK->value,
                        'domain_transfer.reason' => 'Transfer successful',
                    ],
                ]
            );

        $job->handle($mockDomainService, $mockSubRepo, $mockLogger);
        $domainSubscription->refresh();
        self::assertSame(TechnicalStatus::OK->value, $domainSubscription->technical_status);
        self::assertNotNull($domainSubscription->domainDeployment?->last_result_received);
        self::assertSame('Transfer successful', $domainSubscription->domainDeployment->last_result);
    }
}
