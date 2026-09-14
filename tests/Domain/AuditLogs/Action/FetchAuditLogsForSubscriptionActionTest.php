<?php

declare(strict_types=1);

namespace Tests\Domain\AuditLogs\Action;

use Illuminate\Database\Eloquent\Factories\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\AuditFactory;
use Tests\Factories\CloudstackEnvironmentFactory;
use Tests\Factories\CloudstackManagerDomainDeploymentFactory;
use Tests\Factories\CloudstackVirtualMachineDeploymentFactory;
use Tests\Factories\CloudstackVolumeDeploymentFactory;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\Microsoft365CustomerInfoFactory;
use Tests\Factories\Microsoft365DeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ResellerHostingDeploymentFactory;
use Tests\Factories\SslDeploymentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\AuditLogs\Actions\FetchAuditLogsForSubscriptionAction;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(FetchAuditLogsForSubscriptionAction::class)]
class FetchAuditLogsForSubscriptionActionTest extends IntegrationTestCase
{
    private Customer $customer;

    private FetchAuditLogsForSubscriptionAction $fetchAuditLogsForSubscriptionAction;

    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fetchAuditLogsForSubscriptionAction = new FetchAuditLogsForSubscriptionAction();

        $this->customer = new CustomerFactory()->createOne();
        $product = new ProductFactory()->for(new ProductGroupFactory()->extension())->createOne();
        $this->subscription = new SubscriptionFactory()
            ->for($product)
            ->for($this->customer)
            ->createOne();
    }

    #[Test]
    public function findSubscriptionLinked(): void
    {
        $productHosting = new ProductFactory()->for(new ProductGroupFactory()->hosting())->createOne();
        $subscription2 = new SubscriptionFactory()
            ->for($productHosting)
            ->for($this->customer)
            ->createOne();

        new AuditFactory()->create([
            'event' => 'event1',
            'auditable_type' => Subscription::class,
            'auditable_id' => $this->subscription->id,
            'old_values' => ['foo' => 'bar'],
            'new_values' => ['bar' => 'foo'],
        ]);
        new AuditFactory()->create([
            'event' => 'event2',
            'auditable_type' => Subscription::class,
            'auditable_id' => $this->subscription->id,
            'old_values' => ['foo' => 'bar'],
            'new_values' => ['bar' => 'foo'],
        ]);
        new AuditFactory()->create([
            'event' => 'event3',
            'auditable_type' => Subscription::class,
            'auditable_id' => $subscription2->id,
            'old_values' => ['foo' => 'bar'],
            'new_values' => ['bar' => 'foo'],
        ]);

        $auditLogPaginator = $this->fetchAuditLogsForSubscriptionAction->execute($this->subscription, 2);

        self::assertSame(2, $auditLogPaginator->perPage());
        self::assertSame(2, $auditLogPaginator->total());

        // first item, order is DESC it will be event2
        $auditItem1 = $auditLogPaginator->items()[0];
        $auditableSubscription = $auditItem1->auditable;
        self::assertInstanceOf(Subscription::class, $auditableSubscription);

        self::assertSame($this->subscription->id, $auditItem1->auditable_id);
        self::assertSame('event2', $auditItem1->event);

        // second item
        $auditItem2 = $auditLogPaginator->items()[1];
        $auditableSubscription = $auditItem2->auditable;
        self::assertInstanceOf(Subscription::class, $auditableSubscription);

        self::assertSame($this->subscription->id, $auditItem1->auditable_id);
        self::assertSame('event1', $auditItem2->event);
    }

    #[Test]
    public function findDomainDeploymentLinked(): void
    {
        $this->assertAuditFetch(
            new DomainDeploymentFactory()->withPlaceholderProvider(),
        );
    }

    #[Test]
    public function findHostingDeploymentLinked(): void
    {
        $this->assertAuditFetch(
            new HostingDeploymentFactory()->withPleskProvider(),
        );
    }

    #[Test]
    public function findResellerHostingDeploymentLinked(): void
    {
        $this->assertAuditFetch(
            new ResellerHostingDeploymentFactory()->for(new ProviderFactory()->createOne([
                'type' => ProviderType::HOSTING,
                'slug' => ProviderSlug::DIRECTADMIN,
                'enabled' => true,
                'default' => true,
            ]), 'provider'),
        );
    }

    #[Test]
    public function findSslDeploymentLinked(): void
    {
        $this->assertAuditFetch(
            new SslDeploymentFactory()->rtrProvider(),
        );
    }

    #[Test]
    public function find365DeploymentLinked(): void
    {
        $customer = new Microsoft365CustomerInfoFactory()->for(new CustomerFactory())->createOne();
        $this->assertAuditFetch(
            new Microsoft365DeploymentFactory()->for($customer),
        );
    }

    #[Test]
    public function findVirtualMachingDeploymentLinked(): void
    {
        $environment = new CloudstackEnvironmentFactory()->createOne([
            'name' => 'Test Environment',
            'ui_url' => 'https://ui',
            'domain_name' => 'test/vps',
        ]);

        $managerDomainDeployment = new CloudstackManagerDomainDeploymentFactory()
            ->for($environment)
            ->for($this->customer)
            ->createOne([]);

        $this->assertAuditFetch(
            new CloudstackVirtualMachineDeploymentFactory()->for($managerDomainDeployment),
        );
    }

    #[Test]
    public function findVolumeDeploymentLinked(): void
    {
        $environment = new CloudstackEnvironmentFactory()->createOne([
            'name' => 'Test Environment',
            'ui_url' => 'https://ui',
            'domain_name' => 'test/vps',
        ]);

        $managerDomainDeployment = new CloudstackManagerDomainDeploymentFactory()
            ->for($environment)
            ->for($this->customer)
            ->createOne([]);

        $this->assertAuditFetch(
            new CloudstackVolumeDeploymentFactory()->for($managerDomainDeployment)->state([
                'subscription_uuid' => $this->subscription->uuid,
                'cloudstack_id' => 'eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee',
            ]),
        );
    }

    /**
     * @param DomainDeploymentFactory|HostingDeploymentFactory|ResellerHostingDeploymentFactory|SslDeploymentFactory|Microsoft365DeploymentFactory|CloudstackVirtualMachineDeploymentFactory|CloudstackVolumeDeploymentFactory $factory
     */
    private function assertAuditFetch(Factory $factory): void
    {
        $deployment = $factory->for($this->subscription)->createOne();

        new AuditFactory()->create([
            'auditable_type' => $deployment::class,
            'auditable_id' => $deployment->id,
        ]);

        $auditLogPaginator = $this->fetchAuditLogsForSubscriptionAction->execute($this->subscription, 5);

        self::assertSame(5, $auditLogPaginator->perPage());
        self::assertSame(1, $auditLogPaginator->total());

        $auditItem = $auditLogPaginator->items()[0];

        self::assertSame($deployment->id, $auditItem->auditable_id);
        self::assertInstanceOf($deployment::class, $auditItem->auditable);
    }
}
