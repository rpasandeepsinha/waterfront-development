<?php

declare(strict_types=1);

namespace Tests\Domain\Domains\Jobs;

use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\Actions\AssignNameserversToDomainAction;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Factories\NameserverAssignerFactory;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\DNS\Repository\DnsProductSpecRepository;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\Exceptions\DisableAutorenewalFailedException;
use Waterfront\Domain\Domains\Factories\DomainServiceFactory;
use Waterfront\Domain\Domains\Interfaces\DomainDriverInterface;
use Waterfront\Domain\Domains\Jobs\CancelDomainSubscriptionJob;
use Waterfront\Domain\Domains\Repositories\DomainDeploymentRepository;
use Waterfront\Domain\Domains\Repositories\DomainProviderBusinessUnitRepository;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\CancellationService;

#[CoversClass(CancelDomainSubscriptionJob::class)]
class CancelDomainSubscriptionJobTest extends IntegrationTestCase
{
    public const string DOMAIN_NAME = 'dummy.com';

    private Subscription $subscription;

    private CancellationService&MockInterface $cancellationService;

    private DomainService&MockInterface $domainService;

    public function setUp(): void
    {
        parent::setUp();

        $this->cancellationService = $this->mock(CancellationService::class);
        $this->domainService = $this->mock(DomainService::class);

        $this->subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->for(new ProductGroupFactory()->createOne(['slug' => ProductGroupType::EXTENSION])))
            ->createOne([
                'domain' => self::DOMAIN_NAME,
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
                'technical_status' => TechnicalStatus::OK->value,
            ]);

        new DomainDeploymentFactory()
            ->withRtrProvider()
            ->for($this->subscription, 'subscription')
            ->createOne();
    }

    #[Test]
    public function transferredOutDomain(): void
    {
        $this->cancellationService->shouldReceive('cancel')
            ->once()
            ->withArgs(
                fn (Subscription $subscription, SubscriptionCancelType $cancelType, SubscriptionCancelReason $cancelReason, bool $sendMail) => $subscription->uuid === $this->subscription->uuid
                    && $cancelType === SubscriptionCancelType::CANCEL_END_DATE
                    && $sendMail === false
            );

        $this->domainService->shouldReceive('disableAutoRenewal')
            ->once()
            ->andThrow(new DisableAutorenewalFailedException());

        $cancelJob = new CancelDomainSubscriptionJob(self::DOMAIN_NAME);
        $cancelJob->handle($this->cancellationService, $this->domainService);

        $this->subscription->refresh();
        self::assertSame(TechnicalStatus::DELETED->value, $this->subscription->technical_status);
    }

    #[Test]
    public function notTransferredOutDomain(): void
    {
        $this->cancellationService->shouldReceive('cancel')
            ->once()
            ->withArgs(
                fn (Subscription $subscription, SubscriptionCancelType $cancelType, SubscriptionCancelReason $cancelReason, bool $sendMail) => $subscription->uuid === $this->subscription->uuid
                    && $cancelType === SubscriptionCancelType::CANCEL_END_DATE
                    && $sendMail === false
            );

        $driver = $this->mock(DomainDriverInterface::class);
        $driver->shouldReceive('modify')
            ->once()
            ->andReturnTrue();

        $domainServiceFactory = $this->mock(DomainServiceFactory::class);
        $domainServiceFactory->shouldReceive('driver')
            ->once()
            ->andReturn($driver);

        $domainService = new DomainService(
            $this->createStub(NameserverAssignerFactory::class),
            $this->createStub(AssignNameserversToDomainAction::class),
            $domainServiceFactory,
            self::createStub(LoggerInterface::class),
            self::createStub(DnsDeploymentRepository::class),
            self::createStub(DnsProductSpecRepository::class),
            self::createStub(DnsService::class),
            self::resolve(DomainDeploymentRepository::class),
            self::resolve(DomainProviderBusinessUnitRepository::class),
        );

        $cancelJob = new CancelDomainSubscriptionJob(self::DOMAIN_NAME);
        $cancelJob->handle($this->cancellationService, $domainService);

        $this->subscription->refresh();
        self::assertSame(TechnicalStatus::CANCELED->value, $this->subscription->technical_status);
    }
}
