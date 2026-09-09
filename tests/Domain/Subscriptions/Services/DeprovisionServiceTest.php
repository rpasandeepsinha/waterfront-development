<?php

declare(strict_types=1);

namespace Tests\Domain\Subscriptions\Services;

use Illuminate\Contracts\Bus\Dispatcher as JobDispatcher;
use Illuminate\Contracts\Events\Dispatcher;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Factories\CloudstackEnvironmentFactory;
use Tests\Factories\CloudstackManagerDomainDeploymentFactory;
use Tests\Factories\CloudstackVirtualMachineDeploymentFactory;
use Tests\Factories\CloudstackVolumeDeploymentFactory;
use Tests\Factories\CustomerFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ResellerHostingDeploymentFactory;
use Tests\Factories\SslDeploymentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Backup\Events\BackupTerminateEvent;
use Waterfront\Domain\DNS\Events\TerminateDnsZoneEvent;
use Waterfront\Domain\Domains\Events\DomainTerminated;
use Waterfront\Domain\Hosting\Events\TerminateHosting;
use Waterfront\Domain\Hosting\Jobs\TerminateRedirectsJob;
use Waterfront\Domain\MailManagement\Events\TerminateMailOnlyHosting;
use Waterfront\Domain\ManualProvisioning\Events\DispatchTerminateManualProvisioning;
use Waterfront\Domain\Microsoft365\Events\TerminateMicrosoft365;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Enums\ProductType;
use Waterfront\Domain\Products\Jobs\DetachVolumeDiscount;
use Waterfront\Domain\ResellerHosting\Jobs\TerminateResellerHostingJob;
use Waterfront\Domain\Sitebuilder\Events\TerminateSitebuilderHosting;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Services\DeprovisionService;
use Waterfront\Domain\VPS\Events\VpsTerminateEvent;

#[CoversClass(DeprovisionService::class)]
#[AllowMockObjectsWithoutExpectations]
class DeprovisionServiceTest extends IntegrationTestCase
{
    private DeprovisionService $service;

    private Dispatcher&MockObject $eventDispatcher;

    private JobDispatcher&MockObject $jobDispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventDispatcher = self::createMock(Dispatcher::class);
        $this->jobDispatcher = self::createMock(JobDispatcher::class);

        $this->service = new DeprovisionService(
            $this->eventDispatcher,
            $this->jobDispatcher,
            self::resolve(LoggerInterface::class),
        );
    }

    #[Test]
    public function deprovisionDomain(): void
    {
        $subscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for(new ProductGroupFactory()->extension()))
            ->createOne();

        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(DomainTerminated::class));

        $this->service->deprovision($subscription);
    }

    #[Test]
    public function deprovisionRedirect(): void
    {
        $subscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for(new ProductGroupFactory()->redirect()))
            ->createOne();

        $this->jobDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(TerminateRedirectsJob::class));

        $this->service->deprovision($subscription);

        self::assertSame(
            TechnicalStatus::DELETING->value,
            $subscription->refresh()->technical_status
        );
    }

    #[Test]
    public function deprovisionHosting(): void
    {
        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $mailOnlyProduct = new ProductFactory()->for($productGroup)->createOne();
        new ProductSpecFactory()->for($mailOnlyProduct)->createOne([
            'name' => ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER->value,
            'value' => 1,
        ]);
        $randomSubscriptionWithoutDeployment = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for($mailOnlyProduct)
            ->createOne();
        $mailOnlySubscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for($mailOnlyProduct)
            ->createOne();
        $sitebuilderProduct = new ProductFactory()->for($productGroup)->createOne(['slug' => ProductType::SITEBUILDER->value]);
        $sitebuilderSubscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for($sitebuilderProduct)
            ->createOne();
        $hostingSubscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for($productGroup))
            ->createOne();
        new HostingDeploymentFactory()->for($mailOnlySubscription)->withMailOnlyProvider()->createOne();
        new HostingDeploymentFactory()->for($sitebuilderSubscription)->withPleskProvider()->createOne();
        new HostingDeploymentFactory()->for($hostingSubscription)->withDirectAdminProvider()->createOne();

        $this->eventDispatcher
            ->expects(self::exactly(3))
            ->method('dispatch')
            ->with(
                ...self::withConsecutive(
                    [self::isInstanceOf(TerminateMailOnlyHosting::class)],
                    [self::isInstanceOf(TerminateSitebuilderHosting::class)],
                    [self::isInstanceOf(TerminateHosting::class)],
                )
            );

        $this->service->deprovision($mailOnlySubscription);
        $this->service->deprovision($sitebuilderSubscription);
        $this->service->deprovision($hostingSubscription);
        $this->service->deprovision($randomSubscriptionWithoutDeployment);

        self::assertSame(TechnicalStatus::DELETED->value, $randomSubscriptionWithoutDeployment->technical_status);
    }

    #[Test]
    public function deprovisionDns(): void
    {
        $subscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for(new ProductGroupFactory()->dns()))
            ->createOne();

        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(TerminateDnsZoneEvent::class));

        $this->service->deprovision($subscription);
    }

    #[Test]
    public function deprovisionSsl(): void
    {
        $subscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for(new ProductGroupFactory()->ssl()))
            ->createOne();
        new SslDeploymentFactory()
            ->for($subscription)
            ->for(new ProviderFactory()->sslRtr())
            ->createOne();

        $this->service->deprovision($subscription);

        self::assertTrue($subscription->sslDeployment?->trashed());
    }

    #[Test]
    public function deprovisionResellerHosting(): void
    {
        $subscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for(new ProductGroupFactory()->resellerHosting()))
            ->createOne();
        new ResellerHostingDeploymentFactory()
            ->for($subscription)
            ->for(new ProviderFactory()->hostingDirectAdmin())
            ->createOne();

        $this->jobDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(TerminateResellerHostingJob::class));

        $this->service->deprovision($subscription);
    }

    #[Test]
    public function deprovisionCloudstackVirtualMachine(): void
    {
        $subscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for(new ProductGroupFactory()->cloudstackVirtualMachine()))
            ->createOne();
        new CloudstackVirtualMachineDeploymentFactory()
            ->for(new CloudstackManagerDomainDeploymentFactory()->for(new CloudstackEnvironmentFactory())->for(new CustomerFactory()))
            ->for($subscription)
            ->createOne();

        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(VpsTerminateEvent::class));

        $this->service->deprovision($subscription);

        self::assertSame(TechnicalStatus::DELETING->value, $subscription->refresh()->technical_status);
    }

    #[Test]
    public function deprovisionVps(): void
    {
        $subscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for(new ProductGroupFactory()->vps()))
            ->createOne();
        new CloudstackVirtualMachineDeploymentFactory()
            ->for(new CloudstackManagerDomainDeploymentFactory()->for(new CloudstackEnvironmentFactory())->for(new CustomerFactory()))
            ->for($subscription)
            ->createOne();

        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(VpsTerminateEvent::class));

        $this->service->deprovision($subscription);

        self::assertSame(TechnicalStatus::DELETING->value, $subscription->refresh()->technical_status);
    }

    #[Test]
    public function deprovisionCloudstackVolume(): void
    {
        $subscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for(new ProductGroupFactory()->cloudstackVolume()))
            ->createOne();
        new CloudstackVolumeDeploymentFactory()
            ->for(new CloudstackManagerDomainDeploymentFactory()->for(new CloudstackEnvironmentFactory())->for(new CustomerFactory()))
            ->for($subscription)
            ->createOne();

        $this->service->deprovision($subscription);

        self::assertTrue($subscription->cloudStackVolumeDeployment?->trashed());
        self::assertSame(TechnicalStatus::DELETED->value, $subscription->technical_status);
    }

    #[Test]
    public function deprovisionManualSubscription(): void
    {
        $subscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for(new ProductGroupFactory()->manualSubscription()))
            ->createOne();

        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(DispatchTerminateManualProvisioning::class));

        $this->service->deprovision($subscription);
    }

    #[Test]
    public function deprovisionVolumeDiscount(): void
    {
        $subscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for(new ProductGroupFactory()->volumeDiscount()))
            ->createOne();

        $this->jobDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(DetachVolumeDiscount::class));

        $this->service->deprovision($subscription);
    }

    #[Test]
    public function deprovisionMicrosoft365(): void
    {
        $subscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for(new ProductGroupFactory()->microsoft365()))
            ->createOne();

        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(TerminateMicrosoft365::class));

        $this->service->deprovision($subscription);
    }

    #[Test]
    public function deprovisionOther(): void
    {
        $subscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for(new ProductGroupFactory()->other()))
            ->createOne();

        $this->jobDispatcher
            ->expects(self::never())
            ->method(self::anything());

        $this->eventDispatcher
            ->expects(self::never())
            ->method(self::anything());

        $this->service->deprovision($subscription);
    }

    #[Test]
    public function deprovisionCloudStackOs(): void
    {
        $subscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for(new ProductGroupFactory()->cloudstackOs()))
            ->createOne();

        $this->jobDispatcher
            ->expects(self::never())
            ->method(self::anything());

        $this->eventDispatcher
            ->expects(self::never())
            ->method(self::anything());

        $this->service->deprovision($subscription);
    }

    #[Test]
    public function deprovisionOneTimeService(): void
    {
        $subscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for(new ProductGroupFactory()->oneTimeService()))
            ->createOne();

        $this->jobDispatcher
            ->expects(self::never())
            ->method(self::anything());

        $this->eventDispatcher
            ->expects(self::never())
            ->method(self::anything());

        $this->service->deprovision($subscription);
    }

    #[Test]
    public function deprovisionAddon(): void
    {
        $subscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for(new ProductGroupFactory()->addon()))
            ->createOne();

        $this->jobDispatcher
            ->expects(self::never())
            ->method(self::anything());

        $this->eventDispatcher
            ->expects(self::never())
            ->method(self::anything());

        $this->service->deprovision($subscription);
    }

    #[Test]
    public function deprovisionBackup(): void
    {
        $subscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for(new ProductGroupFactory()->backup()))
            ->createOne();

        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(BackupTerminateEvent::class));

        $this->service->deprovision($subscription);

        self::assertSame(
            TechnicalStatus::DELETING->value,
            $subscription->refresh()->technical_status
        );
    }
}
