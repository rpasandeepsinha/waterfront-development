<?php

declare(strict_types=1);

namespace Tests\Domain\Ssl\Job;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\SslDeploymentFactory;
use Tests\TestCase;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Ssl\Jobs\SendDcvReminderEmail;
use Waterfront\Domain\Ssl\Mailers\SslRenewalFailedMissingCname;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Ssl\Repositories\DeploymentRepository;
use Waterfront\Domain\Ssl\Services\CustomerSharedSslService;
use Waterfront\Domain\Ssl\Services\DcvCnameValidatorService;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\RtrClient\DTO\DcvDetails;

#[CoversClass(SendDcvReminderEmail::class)]
#[AllowMockObjectsWithoutExpectations]
class SendDcvReminderEmailJobTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function sendsReminderWhenIssuePersists(): void
    {
        CarbonImmutable::setTestNow('2025-10-14 10:00:00');

        $seed = SslDeploymentFactory::new()->makeOne();

        $sslDeployment = self::getMockBuilder(SslDeployment::class)->onlyMethods(['update'])->getMock();
        $sslDeployment->subscription_uuid = $seed->subscription_uuid;

        $subscription = new Subscription();
        $subscription->domain = 'example.com';
        $subscription->end_date = CarbonImmutable::parse('2025-11-20');
        $subscription->setRelation('customer', (object) []);
        $sslDeployment->setRelation('subscription', $subscription);

        $sslDeploymentRepository = self::createMock(DeploymentRepository::class);
        $sslDeploymentRepository
            ->expects(self::once())
            ->method('findForReminderById')
            ->with(777)
            ->willReturn($sslDeployment);

        $dcv = new DcvDetails('suspended', 'ok', '_abc.example.com', 'CNAME', 'aaaa.bbbb.sectigo.com.');

        $customerSharedSslService = self::createMock(CustomerSharedSslService::class);
        $customerSharedSslService
            ->expects(self::once())
            ->method('getDcvDetails')
            ->with($sslDeployment)
            ->willReturn($dcv);

        $dcvCnameValidatorService = self::createMock(DcvCnameValidatorService::class);
        $dcvCnameValidatorService
            ->expects(self::once())
            ->method('isCnameMissingOrIncorrect')
            ->with($dcv)
            ->willReturn(true);

        $mailer = self::createMock(MailerInterface::class);
        $mailer
            ->expects(self::once())
            ->method('send')
            ->with(
                self::callback(static fn ($recipients) => is_array($recipients)),
                self::callback(function ($mailable) {
                    self::assertInstanceOf(SslRenewalFailedMissingCname::class, $mailable);
                    /** @var SslRenewalFailedMissingCname $mailable */
                    self::assertSame('example.com', $mailable->domain);
                    self::assertSame('_abc.example.com', $mailable->cnameName);
                    self::assertStringEndsWith('.sectigo.com.', $mailable->cnameValue);

                    return true;
                }),
            );

        $sslDeployment->expects(self::never())->method('update');

        $logger = self::createStub(LoggerInterface::class);

        $sendDcvReminderEmail = new SendDcvReminderEmail(777);
        $sendDcvReminderEmail->handle(
            $sslDeploymentRepository,
            $customerSharedSslService,
            $dcvCnameValidatorService,
            $mailer,
            $logger,
        );
    }

    #[Test]
    public function skipsWhenCnameIsValid(): void
    {
        $seed = SslDeploymentFactory::new()->makeOne();

        $sslDeployment = new SslDeployment();
        $sslDeployment->subscription_uuid = $seed->subscription_uuid;

        $subscription = new Subscription();
        $subscription->domain = 'ok.example';
        $subscription->end_date = CarbonImmutable::parse('2025-12-31');
        $subscription->setRelation('customer', (object) []);
        $sslDeployment->setRelation('subscription', $subscription);

        $sslDeploymentRepository = self::createMock(DeploymentRepository::class);
        $sslDeploymentRepository
            ->expects(self::once())
            ->method('findForReminderById')
            ->with(42)
            ->willReturn($sslDeployment);

        $dcv = new DcvDetails('active', 'ok', '_dcv.ok.example', 'CNAME', 'valid.target.example.');

        $customerSharedSslService = self::createMock(CustomerSharedSslService::class);
        $customerSharedSslService
            ->expects(self::once())
            ->method('getDcvDetails')
            ->with($sslDeployment)
            ->willReturn($dcv);

        $dcvCnameValidatorService = self::createMock(DcvCnameValidatorService::class);
        $dcvCnameValidatorService
            ->expects(self::once())
            ->method('isCnameMissingOrIncorrect')
            ->with($dcv)
            ->willReturn(false);

        $mailer = self::createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $logger = self::createStub(LoggerInterface::class);

        $sendDcvReminderEmail = new SendDcvReminderEmail(42);
        $sendDcvReminderEmail->handle(
            $sslDeploymentRepository,
            $customerSharedSslService,
            $dcvCnameValidatorService,
            $mailer,
            $logger,
        );
    }

    #[Test]
    public function skipsWhenDcvDetailsUnavailable(): void
    {
        $seed = SslDeploymentFactory::new()->makeOne();

        $sslDeployment = new SslDeployment();
        $sslDeployment->subscription_uuid = $seed->subscription_uuid;

        $subscription = new Subscription();
        $subscription->domain = 'nodcv.example';
        $subscription->end_date = CarbonImmutable::parse('2025-11-01');
        $subscription->setRelation('customer', (object) []);
        $sslDeployment->setRelation('subscription', $subscription);

        $sslDeploymentRepository = self::createMock(DeploymentRepository::class);
        $sslDeploymentRepository
            ->expects(self::once())
            ->method('findForReminderById')
            ->with(9)
            ->willReturn($sslDeployment);

        $customerSharedSslService = self::createMock(CustomerSharedSslService::class);
        $customerSharedSslService
            ->expects(self::once())
            ->method('getDcvDetails')
            ->with($sslDeployment)
            ->willReturn(null);

        $dcvCnameValidatorService = self::createMock(DcvCnameValidatorService::class);

        $mailer = self::createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $logger = self::createStub(LoggerInterface::class);

        $sendDcvReminderEmail = new SendDcvReminderEmail(9);
        $sendDcvReminderEmail->handle(
            $sslDeploymentRepository,
            $customerSharedSslService,
            $dcvCnameValidatorService,
            $mailer,
            $logger,
        );
    }
}
