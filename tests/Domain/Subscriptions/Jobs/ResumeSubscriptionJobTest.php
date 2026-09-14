<?php

declare(strict_types=1);

namespace Tests\Domain\Subscriptions\Jobs;

use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\OneTimeServiceFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use UnexpectedValueException;
use Waterfront\Domain\Domains\Jobs\RestoreDomainJob;
use Waterfront\Domain\Hosting\Jobs\UnsuspendHostingJob;
use Waterfront\Domain\Invoices\DTO\OneTimeServiceContext;
use Waterfront\Domain\OneTimeServices\Enums\OneTimeServiceStatus;
use Waterfront\Domain\OneTimeServices\Services\OneTimeServiceCreator;
use Waterfront\Domain\OneTimeServices\Services\OneTimeServiceInvoiceService;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Actions\ResumeSubscriptionInGracePeriodAction;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Jobs\ResumeSubscriptionJob;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(ResumeSubscriptionJob::class)]
#[AllowMockObjectsWithoutExpectations]
class ResumeSubscriptionJobTest extends IntegrationTestCase
{
    private Subscription $subscription;

    private ResumeSubscriptionInGracePeriodAction&MockObject $resumeSubscriptionInGracePeriodAction;

    private ProductRepository&MockObject $productRepository;

    private OneTimeServiceInvoiceService&MockObject $oneTimeServiceInvoiceService;

    private Dispatcher $dispatcher;

    private OneTimeServiceCreator&MockObject $oneTimeServiceCreator;

    private Product $quarantaineProduct;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow();
        Queue::fake();

        Model::preventLazyLoading(false);

        $date = CarbonImmutable::now();

        $product = new ProductFactory()->for(
            new ProductGroupFactory()->extension(),
        )->createOne();

        $this->quarantaineProduct = new ProductFactory()->for(
            new ProductGroupFactory()->oneTimeService(),
        )->createOne([
            'slug' => 'quarantainekosten',
        ]);

        $this->subscription = new SubscriptionFactory()
            ->withCustomer()
            ->administrativeStatusExpired()
            ->for($product)
            ->createOne([
                'cancel_date' => $date->subWeek(),
                'termination_date' => $date->addWeek(),
            ]);

        $this->resumeSubscriptionInGracePeriodAction = self::createMock(ResumeSubscriptionInGracePeriodAction::class);
        $this->productRepository = self::createMock(ProductRepository::class);
        $this->oneTimeServiceInvoiceService = self::createMock(OneTimeServiceInvoiceService::class);
        $this->oneTimeServiceCreator = self::createMock(OneTimeServiceCreator::class);
        $this->dispatcher = self::resolve(Dispatcher::class);
    }

    #[Test]
    public function subscriptionWithDomainDeploymentIsSuccessfullyResumed(): void
    {
        new DomainDeploymentFactory()
            ->for($this->subscription)
            ->for(
                ProviderFactory::new()->createOne([
                    'type' => ProviderType::DOMAIN,
                    'slug' => ProviderSlug::OPEN_PROVIDER,
                    'enabled' => true,
                    'default' => true,
                ]),
                'provider',
            )
            ->createOne();

        $this->resumeSubscriptionInGracePeriodAction->expects(self::once())->method('execute');
        $this->productRepository
            ->expects(self::once())
            ->method('getQuarantaineProduct')
            ->willReturn($this->quarantaineProduct);

        $context = new OneTimeServiceContext(
            $this->subscription,
            $this->quarantaineProduct,
            1,
            0,
            OneTimeServiceStatus::DONE,
            CarbonImmutable::now(),
            'Resuming expired subscription, removing domain from quarantaine.',
            null,
        );

        $oneTimeService = new OneTimeServiceFactory()
            ->for($this->subscription->customer)
            ->for($this->quarantaineProduct)
            ->createOne(
                [
                    'subscription_id' => $context->subscription->id,
                ],
            );

        $this->oneTimeServiceCreator
            ->expects(self::once())
            ->method('createFromContextWithNote')
            ->willReturn($oneTimeService);
        $this->oneTimeServiceInvoiceService
            ->expects(self::once())
            ->method('createOneTimeServiceInvoices')
            ->with($oneTimeService);

        $resumeSubscriptionJob = new ResumeSubscriptionJob($this->subscription, true);
        $resumeSubscriptionJob->handle(
            $this->resumeSubscriptionInGracePeriodAction,
            $this->productRepository,
            $this->oneTimeServiceCreator,
            $this->oneTimeServiceInvoiceService,
            $this->dispatcher,
            self::createStub(LoggerInterface::class),
        );

        Queue::assertPushed(RestoreDomainJob::class);
    }

    #[Test]
    public function subscriptionWithHostingDeploymentIsSuccessfullyResumed(): void
    {
        $productGroup = ProductGroup::where('slug', ProductGroupType::EXTENSION)->first();
        self::assertNotNull($productGroup);

        $productGroup->slug = ProductGroupType::HOSTING;
        $productGroup->save();

        new HostingDeploymentFactory()
            ->for($this->subscription)
            ->for(
                new ProviderFactory()->createOne([
                    'type' => ProviderType::HOSTING,
                    'slug' => ProviderSlug::PLACEHOLDER,
                    'enabled' => true,
                    'default' => false,
                ]),
                'provider',
            )
            ->createOne();

        $this->resumeSubscriptionInGracePeriodAction->expects(self::once())->method('execute');
        $this->productRepository->expects(self::never())->method('getQuarantaineProduct');

        $this->oneTimeServiceCreator->expects(self::never())->method('createFromContextWithNote');

        $this->oneTimeServiceInvoiceService->expects(self::never())->method('createFromCollection');

        $resumeSubscriptionJob = new ResumeSubscriptionJob($this->subscription, true);
        $resumeSubscriptionJob->handle(
            $this->resumeSubscriptionInGracePeriodAction,
            $this->productRepository,
            $this->oneTimeServiceCreator,
            $this->oneTimeServiceInvoiceService,
            $this->dispatcher,
            self::createStub(LoggerInterface::class),
        );

        Queue::assertPushed(UnsuspendHostingJob::class);
    }

    #[Test]
    public function subscriptionWithDomainDeploymentCantFindQuarantaineProductWillStillRestoreDomain(): void
    {
        new DomainDeploymentFactory()
            ->for($this->subscription)
            ->for(
                ProviderFactory::new()->createOne([
                    'type' => ProviderType::DOMAIN,
                    'slug' => ProviderSlug::PLACEHOLDER,
                    'enabled' => true,
                    'default' => true,
                ]),
                'provider',
            )
            ->createOne();

        $this->resumeSubscriptionInGracePeriodAction->expects(self::once())->method('execute');
        $this->productRepository->expects(self::once())->method('getQuarantaineProduct')->willReturn(null);

        $this->oneTimeServiceCreator->expects(self::never())->method('createFromContextWithNote');
        $this->oneTimeServiceInvoiceService->expects(self::never())->method('createFromCollection');

        $resumeSubscriptionJob = new ResumeSubscriptionJob($this->subscription, true);
        $resumeSubscriptionJob->handle(
            $this->resumeSubscriptionInGracePeriodAction,
            $this->productRepository,
            $this->oneTimeServiceCreator,
            $this->oneTimeServiceInvoiceService,
            $this->dispatcher,
            self::createStub(LoggerInterface::class),
        );

        Queue::assertPushed(RestoreDomainJob::class);
    }

    #[Test]
    public function subscriptionIsNotEligible(): void
    {
        $this->subscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $this->subscription->refresh();

        $this->resumeSubscriptionInGracePeriodAction
            ->expects(self::once())
            ->method('execute')
            ->willThrowException(new UnexpectedValueException());

        $this->oneTimeServiceCreator->expects(self::never())->method('createFromContextWithNote');
        $this->oneTimeServiceInvoiceService->expects(self::never())->method('createFromCollection');

        $resumeSubscriptionJob = new ResumeSubscriptionJob($this->subscription, true);
        $resumeSubscriptionJob->handle(
            $this->resumeSubscriptionInGracePeriodAction,
            $this->productRepository,
            $this->oneTimeServiceCreator,
            $this->oneTimeServiceInvoiceService,
            $this->dispatcher,
            self::createStub(LoggerInterface::class),
        );

        $this->subscription->refresh();
        self::assertSame(AdministrativeStatus::EXPIRED->value, $this->subscription->administrative_status);

        Queue::assertNotPushed(RestoreDomainJob::class);
        Queue::assertNotPushed(UnsuspendHostingJob::class);
    }

    #[Test]
    public function subscriptionResumeSubscriptionInGraceThrowsException(): void
    {
        $this->resumeSubscriptionInGracePeriodAction
            ->expects(self::once())
            ->method('execute')
            ->willThrowException(new Exception());

        $this->oneTimeServiceCreator->expects(self::never())->method('createFromContextWithNote');
        $this->oneTimeServiceInvoiceService->expects(self::never())->method('createFromCollection');

        $this->expectException(Exception::class);
        $resumeSubscriptionJob = new ResumeSubscriptionJob($this->subscription, true);
        $resumeSubscriptionJob->handle(
            $this->resumeSubscriptionInGracePeriodAction,
            $this->productRepository,
            $this->oneTimeServiceCreator,
            $this->oneTimeServiceInvoiceService,
            $this->dispatcher,
            self::createStub(LoggerInterface::class),
        );
    }
}
