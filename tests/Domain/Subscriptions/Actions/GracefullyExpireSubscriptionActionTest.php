<?php

declare(strict_types=1);

namespace Tests\Domain\Subscriptions\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Bus\Dispatcher;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\AuditLogs\Actions\StoreAuditLogAction;
use Waterfront\Domain\Domains\Jobs\DisableDomainAutoRenewal;
use Waterfront\Domain\Domains\Jobs\SuspendDomainJob;
use Waterfront\Domain\Hosting\Jobs\SuspendHostingJob;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Products\Repositories\ProductSpecRepository;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Actions\GracefullyExpireSubscriptionAction;
use Waterfront\Domain\Subscriptions\Actions\SaveSubscriptionAdministrativeStatusAction;
use Waterfront\Domain\Subscriptions\Actions\SaveSubscriptionTerminationDateAction;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Services\SubscriptionChangeService;

#[CoversClass(GracefullyExpireSubscriptionAction::class)]
class GracefullyExpireSubscriptionActionTest extends IntegrationTestCase
{
    private GracefullyExpireSubscriptionAction $gracefullyExpireSubscriptionAction;

    private Dispatcher&MockObject $jobDispatcher;

    private SaveSubscriptionTerminationDateAction&MockObject $saveSubscriptionTerminationDateAction;

    private SaveSubscriptionAdministrativeStatusAction&MockObject $saveSubscriptionAdministrativeStatusAction;

    private ProductSpecRepository&MockObject $productSpecRepository;

    private ProductGroup $domainProductGroup;

    private ProductGroup $dnsProductGroup;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow();

        $this->jobDispatcher = self::createMock(Dispatcher::class);
        $this->saveSubscriptionTerminationDateAction = self::createMock(SaveSubscriptionTerminationDateAction::class);
        $this->saveSubscriptionAdministrativeStatusAction = self::createMock(SaveSubscriptionAdministrativeStatusAction::class);
        $this->productSpecRepository = self::createMock(ProductSpecRepository::class);

        $this->gracefullyExpireSubscriptionAction = new GracefullyExpireSubscriptionAction(
            $this->saveSubscriptionAdministrativeStatusAction,
            $this->saveSubscriptionTerminationDateAction,
            $this->jobDispatcher,
            $this->productSpecRepository,
            self::createStub(LoggerInterface::class),
            self::createStub(StoreAuditLogAction::class),
            self::createStub(SubscriptionChangeService::class),
        );

        $this->domainProductGroup = new ProductGroupFactory()->extension()->createOne();
        $this->dnsProductGroup = new ProductGroupFactory()->dns()->createOne();
    }

    #[Test]
    public function gracefullyExpireDomainSubscriptionType(): void
    {
        $terminationDate = CarbonImmutable::now()->addDays(30);

        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->administrativeStatusCancelled()
            ->for(new ProductFactory()->for($this->domainProductGroup))
            ->createOne();
        $provider = ProviderFactory::new()->createOne([
            'type' => ProviderType::DOMAIN,
            'enabled' => true,
            'default' => false,
            'slug' => ProviderSlug::PLACEHOLDER,
        ]);
        new DomainDeploymentFactory()
            ->for($provider)
            ->for($subscription)
            ->createOne();

        $this->saveSubscriptionAdministrativeStatusAction
            ->expects(self::once())
            ->method('execute')
            ->with($subscription, AdministrativeStatus::EXPIRED);

        $this->saveSubscriptionTerminationDateAction
            ->expects(self::once())
            ->method('execute')
            ->with($subscription, self::callback(function ($givenDate) use ($terminationDate) {
                self::assertInstanceOf(CarbonImmutable::class, $givenDate);
                self::assertSame($givenDate->format('Y-m-d'), $terminationDate->format('Y-m-d'));

                return true;
            }));

        $this->jobDispatcher
            ->expects(self::exactly(2))
            ->method('dispatch')
            ->willReturnCallback(
                fn ($job) => match ($job::class) {
                    DisableDomainAutoRenewal::class, SuspendDomainJob::class => true,
                    default => throw new LogicException($job::class),
                },
            );

        $this->productSpecRepository
            ->expects(self::once())
            ->method('findBySpecification')
            ->willReturn(new ProductSpecFactory()->make(['value' => '30']));

        $this->gracefullyExpireSubscriptionAction->execute($subscription);
    }

    #[Test]
    public function graceFullyExpireWithChildSetsSuspendedState(): void
    {
        $terminationDate = CarbonImmutable::now()->addDays(30);

        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->administrativeStatusCancelled()
            ->for(new ProductFactory()->for($this->domainProductGroup))
            ->createOne();
        $dnsChild = new SubscriptionFactory()
            ->withCustomer()
            ->administrativeStatusCancelled()
            ->technicalStatusOk()
            ->for(new ProductFactory()->for($this->dnsProductGroup))
            ->parentSubscription($subscription)
            ->createOne();

        $this->saveSubscriptionAdministrativeStatusAction
            ->expects(self::once())
            ->method('execute')
            ->with($dnsChild, AdministrativeStatus::EXPIRED);

        $this->saveSubscriptionTerminationDateAction
            ->expects(self::once())
            ->method('execute')
            ->with($dnsChild, self::callback(function ($givenDate) use ($terminationDate) {
                self::assertInstanceOf(CarbonImmutable::class, $givenDate);
                self::assertSame($givenDate->format('Y-m-d'), $terminationDate->format('Y-m-d'));

                return true;
            }));

        $this->jobDispatcher->expects(self::never())->method('dispatch');

        $this->productSpecRepository
            ->expects(self::once())
            ->method('findBySpecification')
            ->willReturn(new ProductSpecFactory()->make(['value' => '30']));

        $this->gracefullyExpireSubscriptionAction->execute($dnsChild);

        $dnsChild->refresh();

        self::assertSame(TechnicalStatus::SUSPENDED->value, $dnsChild->technical_status);
    }

    #[Test]
    public function expireDomainSubscriptionTypWithNoGracePeriod(): void
    {
        $terminationDate = CarbonImmutable::now();

        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->administrativeStatusCancelled()
            ->for(new ProductFactory()->for($this->domainProductGroup))
            ->createOne();
        $provider = ProviderFactory::new()->createOne([
            'type' => ProviderType::DOMAIN,
            'enabled' => true,
            'default' => false,
            'slug' => ProviderSlug::PLACEHOLDER,
        ]);
        new DomainDeploymentFactory()
            ->for($provider)
            ->for($subscription)
            ->createOne();

        $this->saveSubscriptionAdministrativeStatusAction
            ->expects(self::once())
            ->method('execute')
            ->with($subscription, AdministrativeStatus::EXPIRED);

        $this->saveSubscriptionTerminationDateAction
            ->expects(self::once())
            ->method('execute')
            ->with($subscription, self::callback(function ($givenDate) use ($terminationDate) {
                self::assertInstanceOf(CarbonImmutable::class, $givenDate);
                self::assertSame($givenDate->format('Y-m-d'), $terminationDate->format('Y-m-d'));

                return true;
            }));

        $this->jobDispatcher->expects(self::never())->method('dispatch');

        $this->productSpecRepository->expects(self::once())->method('findBySpecification')->willReturn(null);

        $this->gracefullyExpireSubscriptionAction->execute($subscription);
    }

    #[Test]
    public function gracefullyExpireHostingSubscriptionType(): void
    {
        $terminationDate = CarbonImmutable::now()->addDays(30);

        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->administrativeStatusCancelled()
            ->for(new ProductFactory()->for(new ProductGroupFactory()->hosting()))
            ->createOne();

        new ProviderFactory()->createOne([
            'type' => ProviderType::HOSTING,
            'slug' => ProviderSlug::DIRECTADMIN,
            'enabled' => true,
            'default' => true,
        ]);
        new HostingDeploymentFactory()->for($subscription)->createOne();

        $this->saveSubscriptionAdministrativeStatusAction
            ->expects(self::once())
            ->method('execute')
            ->with($subscription, AdministrativeStatus::EXPIRED);

        $this->saveSubscriptionTerminationDateAction
            ->expects(self::once())
            ->method('execute')
            ->with($subscription, self::callback(function ($givenDate) use ($terminationDate) {
                self::assertInstanceOf(CarbonImmutable::class, $givenDate);
                self::assertSame($givenDate->format('Y-m-d'), $terminationDate->format('Y-m-d'));

                return true;
            }));

        $this->jobDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(SuspendHostingJob::class));

        $this->productSpecRepository
            ->expects(self::once())
            ->method('findBySpecification')
            ->willReturn(new ProductSpecFactory()->make(['value' => '30']));

        $this->gracefullyExpireSubscriptionAction->execute($subscription);
    }

    #[Test]
    public function gracefullyExpireHostingSubscriptionTypeWithoutDeployment(): void
    {
        $terminationDate = CarbonImmutable::now()->addDays(30);

        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->administrativeStatusCancelled()
            ->for(new ProductFactory()->for(new ProductGroupFactory()->hosting()))
            ->createOne();

        $this->saveSubscriptionAdministrativeStatusAction
            ->expects(self::once())
            ->method('execute')
            ->with($subscription, AdministrativeStatus::EXPIRED);

        $this->saveSubscriptionTerminationDateAction
            ->expects(self::once())
            ->method('execute')
            ->with($subscription, self::callback(function ($givenDate) use ($terminationDate) {
                self::assertInstanceOf(CarbonImmutable::class, $givenDate);
                self::assertSame($givenDate->format('Y-m-d'), $terminationDate->format('Y-m-d'));

                return true;
            }));

        $this->jobDispatcher->expects(self::never())->method('dispatch');

        $this->productSpecRepository
            ->expects(self::once())
            ->method('findBySpecification')
            ->willReturn(new ProductSpecFactory()->make(['value' => '30']));

        $this->gracefullyExpireSubscriptionAction->execute($subscription);

        self::assertSame(TechnicalStatus::SUSPENDED->value, $subscription->technical_status);
    }
}
