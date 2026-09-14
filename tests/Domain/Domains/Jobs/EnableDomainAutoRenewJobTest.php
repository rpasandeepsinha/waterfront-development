<?php

declare(strict_types=1);

namespace Tests\Domain\Domains\Jobs;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\Exceptions\EnableAutorenewalFailedException;
use Waterfront\Domain\Domains\Jobs\EnableDomainAutoRenewJob;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Email\Actions\SendEnableAutoRenewFailedMailAction;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(EnableDomainAutoRenewJob::class)]
#[AllowMockObjectsWithoutExpectations]
class EnableDomainAutoRenewJobTest extends IntegrationTestCase
{
    private Subscription $subscription;

    private DomainDeployment $domainDeployment;

    private SendEnableAutoRenewFailedMailAction&MockObject $domainEnableAutoRenewFailedMailer;

    private DomainService&MockObject $domainService;

    private LoggerInterface&MockObject $logger;

    private ProductGroup $extensionProductGroup;

    public function setUp(): void
    {
        parent::setUp();

        $this->domainEnableAutoRenewFailedMailer = $this->createMock(SendEnableAutoRenewFailedMailAction::class);
        $this->domainService = $this->createMock(DomainService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->extensionProductGroup = new ProductGroupFactory()->extension()->createOne();

        $this->subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->for($this->extensionProductGroup))
            ->createOne([
                'domain' => 'revertcancel.nl',
                'administrative_status' => AdministrativeStatus::CANCELED->value,
                'technical_status' => TechnicalStatus::OK->value,
            ]);

        $this->domainDeployment = new DomainDeploymentFactory()
            ->withRtrProvider()
            ->for($this->subscription, 'subscription')
            ->createOne();
    }

    #[Test]
    public function enableDomainAutoRenewJobSuccess(): void
    {
        $this->domainService
            ->expects(self::once())
            ->method('enableAutoRenewal')
            ->with($this->subscription->domain, $this->domainDeployment->provider->slug);

        $this->domainEnableAutoRenewFailedMailer->expects(self::never())->method('execute');

        $job = new EnableDomainAutoRenewJob($this->subscription);
        $job->handle($this->domainService, $this->domainEnableAutoRenewFailedMailer, $this->logger);

        $this->subscription->refresh();

        self::assertSame(TechnicalStatus::OK->value, $this->subscription->technical_status);
    }

    #[Test]
    public function enableDomainAutoRenewJobFailed(): void
    {
        $this->domainService
            ->expects(self::once())
            ->method('enableAutoRenewal')
            ->with($this->subscription->domain, $this->domainDeployment->provider->slug)
            ->willThrowException(new EnableAutorenewalFailedException());

        $this->domainEnableAutoRenewFailedMailer->expects(self::once())->method('execute')->with($this->subscription);

        $job = new EnableDomainAutoRenewJob($this->subscription);
        $job->handle($this->domainService, $this->domainEnableAutoRenewFailedMailer, $this->logger);

        $this->subscription->refresh();

        self::assertSame(TechnicalStatus::ERROR->value, $this->subscription->technical_status);
    }

    #[Test]
    public function enableDomainAutoRenewJobNotDomainSubscriptionDoesNotRun(): void
    {
        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->for($this->extensionProductGroup))
            ->createOne([
                'technical_status' => TechnicalStatus::OK->value,
            ]);

        $this->domainService->expects(self::never())->method('enableAutoRenewal')->with($subscription->domain, '');

        $this->domainEnableAutoRenewFailedMailer->expects(self::never())->method('execute')->with($subscription);

        $this->logger->expects(self::once())->method('error');

        $job = new EnableDomainAutoRenewJob($subscription);
        $job->handle($this->domainService, $this->domainEnableAutoRenewFailedMailer, $this->logger);

        $subscription->refresh();

        self::assertSame(TechnicalStatus::OK->value, $subscription->technical_status);
    }

    #[Test]
    public function enableDomainAutoRenewJobNoDomainDoesNotRun(): void
    {
        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->for($this->extensionProductGroup))
            ->createOne([
                'domain' => null,
                'technical_status' => TechnicalStatus::OK->value,
            ]);

        $domainDeployment = new DomainDeploymentFactory()
            ->withRtrProvider()
            ->for($subscription, 'subscription')
            ->createOne();

        $this->domainService
            ->expects(self::never())
            ->method('enableAutoRenewal')
            ->with($subscription->domain, $domainDeployment);

        $this->domainEnableAutoRenewFailedMailer->expects(self::never())->method('execute')->with($subscription);

        $this->logger->expects(self::once())->method('error');

        $job = new EnableDomainAutoRenewJob($subscription);
        $job->handle($this->domainService, $this->domainEnableAutoRenewFailedMailer, $this->logger);

        $subscription->refresh();

        self::assertSame(TechnicalStatus::OK->value, $subscription->technical_status);
    }
}
