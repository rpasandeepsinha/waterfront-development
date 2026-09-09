<?php

declare(strict_types=1);

namespace Tests\Domain\Subscriptions\Services;

use Illuminate\Contracts\Bus\Dispatcher as JobDispatcher;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Testing\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Factories\CustomerFactory;
use Tests\Factories\OrderFactory;
use Tests\Factories\OrderLineItemFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\Events\CreateDns;
use Waterfront\Domain\Domains\Events\CreateDomain;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Hosting\Events\CreateHosting;
use Waterfront\Domain\MailManagement\Events\CreateMailOnlyHosting;
use Waterfront\Domain\ManualProvisioning\Events\DispatchCreateManualProvisioning;
use Waterfront\Domain\Microsoft365\Events\CreateMicrosoft365;
use Waterfront\Domain\Notes\Actions\StoreNoteAction;
use Waterfront\Domain\Notes\Models\Notes;
use Waterfront\Domain\Orders\Serializers\CartSerializerFactory;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Enums\ProductType;
use Waterfront\Domain\Products\Repositories\BackupProductSpecRepository;
use Waterfront\Domain\ResellerHosting\Jobs\CreateResellerHostingJob;
use Waterfront\Domain\Sitebuilder\Events\CreateSitebuilder;
use Waterfront\Domain\Ssl\Events\CreateSsl;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Services\ProvisionService;
use Waterfront\Domain\Subscriptions\SubscriptionProcessor\Builder\EventSubscriptionDataBuilder;
use Waterfront\Domain\VPS\Events\CreateVps;
use Waterfront\Domain\VPS\Interfaces\VirtualMachineDeploymentRepositoryInterface;

#[CoversClass(ProvisionService::class)]
#[AllowMockObjectsWithoutExpectations]
class ProvisionServiceTest extends IntegrationTestCase
{
    private ProvisionService $service;

    private EventDispatcher&MockObject $eventDispatcher;

    private JobDispatcher&MockObject $jobDispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventDispatcher = self::createMock(EventDispatcher::class);
        $this->jobDispatcher = self::createMock(JobDispatcher::class);

        $this->service = new ProvisionService(
            builder: self::resolve(EventSubscriptionDataBuilder::class),
            vmSubscriptionRepository: self::resolve(VirtualMachineDeploymentRepositoryInterface::class),
            cartSerializerFactory: self::resolve(CartSerializerFactory::class),
            eventDispatcher: $this->eventDispatcher,
            jobDispatcher: $this->jobDispatcher,
            backupProductSpecRepository: self::resolve(BackupProductSpecRepository::class),
            storeNoteAction: self::resolve(StoreNoteAction::class),
        );
    }

    #[Test]
    public function provisionDomain(): void
    {
        new ProviderFactory()->domainRtr()->createOne();
        $subscription = new SubscriptionFactory()
            ->for(new CustomerFactory()->withAddress())
            ->for(new ProductFactory()->for(new ProductGroupFactory()->extension()))
            ->createOne();

        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(CreateDomain::class));

        $this->service->provision([$subscription]);
    }

    #[Test]
    public function provisionDomainWithTransferService(): void
    {
        new ProviderFactory()->domainRtr()->createOne();
        $customer = new CustomerFactory()->withAddress()->createOne();

        $hostingProduct = new ProductFactory()->hostingBrons()->createOne();
        $transferProduct = new ProductFactory()->for(new ProductGroupFactory()->oneTimeService()->createOne())->createOne([
            'slug' => 'transfer_service',
        ]);

        $subscription = new SubscriptionFactory()
            ->for($customer)
            ->for(new ProductFactory()->for(new ProductGroupFactory()->extension()))
            ->createOne();

        $order = new OrderFactory()->for($customer)->createOne();
        new OrderLineItemFactory()->for($order)->createOne([
            'domain' => $subscription->domain,
            'product_uuid' => $subscription->product_uuid,
            'subscription_uuid' => $subscription->uuid,
            'transfer_secret' => 'abcdefghijklmnop',
        ]);
        $hostingLine = new OrderLineItemFactory()->for($order)->createOne([
            'product_uuid' => $hostingProduct->uuid,
            'domain' => $subscription->domain,
        ]);
        new OrderLineItemFactory()->for($order)->createOne([
            'product_uuid' => $transferProduct->uuid,
            'domain' => $subscription->domain,
            'parent_id' => $hostingLine->id,
        ]);

        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(CreateDomain::class));

        $this->service->provision([$subscription]);

        $domainDeployment = DomainDeployment::where('subscription_uuid', $subscription->uuid)->firstOrFail();
        Assert::assertSame('deferred_transfer', $domainDeployment->transfer_secret);
        $note = Notes::where('subscription_id', $subscription->id)->firstOrFail()->firstOrFail();
        Assert::assertStringContainsString('deferred_transfer', $note->note);
    }

    #[Test]
    public function deferredTransfer(): void
    {
        $customer = new CustomerFactory()->withAddress()->createOne();
        new ProviderFactory()->domainRtr()->createOne();
        $subscription = new SubscriptionFactory()
            ->for($customer)
            ->for(new ProductFactory()->for(new ProductGroupFactory()->extension()))
            ->createOne();

        $order = new OrderFactory()->for($customer)->createOne();
        new OrderLineItemFactory()->for($order)->createOne([
            'domain' => $subscription->domain,
            'product_uuid' => $subscription->product_uuid,
            'subscription_uuid' => $subscription->uuid,
            'transfer_secret' => 'deferred_transfer',
        ]);

        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(CreateDomain::class));

        $this->service->provision([$subscription]);

        $domainDeployment = DomainDeployment::where('subscription_uuid', $subscription->uuid)->firstOrFail();
        Assert::assertSame('deferred_transfer', $domainDeployment->transfer_secret);
    }

    #[Test]
    public function provisionRedirect(): void
    {
        $subscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for(new ProductGroupFactory()->redirect()))
            ->createOne([
                'technical_status' => 'blabla',
            ]);

        $this->service->provision([$subscription]);

        self::assertSame(TechnicalStatus::OK->value, $subscription->technical_status);
    }

    #[Test]
    public function provisionOther(): void
    {
        $subscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for(new ProductGroupFactory()->other()))
            ->createOne([
                'technical_status' => 'blabla',
            ]);

        $this->service->provision([$subscription]);

        self::assertSame(TechnicalStatus::OK->value, $subscription->technical_status);
    }

    #[Test]
    public function provisionSsl(): void
    {
        new ProviderFactory()->sslRtr()->createOne(['default' => true]);
        $subscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for(new ProductGroupFactory()->ssl()))
            ->createOne();

        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(CreateSsl::class));

        $this->service->provision([$subscription]);
    }

    #[Test]
    public function provisionDns(): void
    {
        $subscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for(new ProductGroupFactory()->dns()))
            ->createOne();

        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(CreateDns::class));

        $this->service->provision([$subscription]);
    }

    #[Test]
    public function resellerHosting(): void
    {
        $subscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for(new ProductGroupFactory()->resellerHosting()))
            ->createOne();

        $this->jobDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(CreateResellerHostingJob::class));

        $this->service->provision([$subscription]);
    }

    #[Test]
    public function vps(): void
    {
        $subscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for(new ProductGroupFactory()->vps()))
            ->createOne();

        $subscription->children()->save(
            new SubscriptionFactory()
                ->for(new CustomerFactory())
                ->for(new ProductFactory()->for(new ProductGroupFactory()->cloudstackOs()))
                ->createOne()
        );

        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(CreateVps::class));

        $this->service->provision([$subscription]);
    }

    #[Test]
    public function manualSubscription(): void
    {
        $subscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for(new ProductGroupFactory()->manualSubscription()))
            ->createOne();

        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(DispatchCreateManualProvisioning::class));

        $this->service->provision([$subscription]);
    }

    #[Test]
    public function hosting(): void
    {
        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $mailOnlyProduct = new ProductFactory()->for($productGroup)->createOne();
        new ProductSpecFactory()->for($mailOnlyProduct)->createOne([
            'name' => ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER->value,
            'value' => 1,
        ]);
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

        $this->eventDispatcher
            ->expects(self::exactly(3))
            ->method('dispatch')
            ->with(
                ...self::withConsecutive(
                    [self::isInstanceOf(CreateMailOnlyHosting::class)],
                    [self::isInstanceOf(CreateSitebuilder::class)],
                    [self::isInstanceOf(CreateHosting::class)],
                )
            );

        $this->service->provision([$mailOnlySubscription, $sitebuilderSubscription, $hostingSubscription]);
    }

    #[Test]
    public function cloudStackVirtualMachine(): void
    {
        $subscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for(new ProductGroupFactory()->cloudstackVirtualMachine()))
            ->createOne();

        $this->eventDispatcher
            ->expects(self::never())
            ->method(self::anything());

        $this->service->provision([$subscription]);
    }

    #[Test]
    public function cloudStackVolume(): void
    {
        $subscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for(new ProductGroupFactory()->cloudstackVolume()))
            ->createOne();

        $this->eventDispatcher
            ->expects(self::never())
            ->method(self::anything());

        $this->service->provision([$subscription]);
    }

    #[Test]
    public function cloudStackOs(): void
    {
        $subscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for(new ProductGroupFactory()->cloudstackOs()))
            ->createOne();

        $this->eventDispatcher
            ->expects(self::never())
            ->method(self::anything());

        $this->service->provision([$subscription]);
    }

    #[Test]
    public function microsoft365(): void
    {
        $subscriptions = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for(new ProductGroupFactory()->microsoft365()))
            ->createMany(2)
            ->all();

        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(CreateMicrosoft365::class));

        $this->service->provision($subscriptions);
    }

    #[Test]
    public function oneTimeService(): void
    {
        $subscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for(new ProductGroupFactory()->oneTimeService()))
            ->createOne();

        $this->eventDispatcher
            ->expects(self::never())
            ->method(self::anything());

        $this->service->provision([$subscription]);
    }

    #[Test]
    public function volumeDiscount(): void
    {
        $subscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for(new ProductGroupFactory()->volumeDiscount()))
            ->createOne();

        $this->eventDispatcher
            ->expects(self::never())
            ->method(self::anything());

        $this->service->provision([$subscription]);
    }

    #[Test]
    public function addon(): void
    {
        $subscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for(new ProductGroupFactory()->addon()))
            ->createOne();

        $this->eventDispatcher
            ->expects(self::never())
            ->method(self::anything());

        $this->service->provision([$subscription]);
    }
}
