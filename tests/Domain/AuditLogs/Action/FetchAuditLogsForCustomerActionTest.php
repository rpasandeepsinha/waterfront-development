<?php

declare(strict_types=1);

namespace Tests\Domain\AuditLogs\Action;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\AuditFactory;
use Tests\Factories\CloudstackEnvironmentFactory;
use Tests\Factories\CloudstackEnvironmentProductFactory;
use Tests\Factories\CloudstackManagerDomainDeploymentFactory;
use Tests\Factories\CloudstackVirtualMachineDeploymentFactory;
use Tests\Factories\CloudstackVolumeDeploymentFactory;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\Microsoft365CustomerInfoFactory;
use Tests\Factories\Microsoft365DeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\AuditLogs\Actions\FetchAuditLogsForCustomerAction;
use Waterfront\Domain\AuditLogs\Enums\AuditLogEvent;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;

#[CoversClass(FetchAuditLogsForCustomerAction::class)]
class FetchAuditLogsForCustomerActionTest extends IntegrationTestCase
{
    private Customer $customer;

    private FetchAuditLogsForCustomerAction $fetchAuditLogsForCustomerAction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fetchAuditLogsForCustomerAction = new FetchAuditLogsForCustomerAction();

        $this->customer = new CustomerFactory()->createOne();
    }

    #[Test]
    public function execute(): void
    {
        $prodGroup = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::EXTENSION,
        ]);
        $product = new ProductFactory()->for($prodGroup)->createOne([]);

        $subscription = new SubscriptionFactory()
            ->for($product)
            ->for($this->customer)
            ->createOne();

        new AuditFactory()->create([
            'event' => 'updated',
            'auditable_type' => Subscription::class,
            'auditable_id' => $subscription->id,
            'old_values' => ['technical_status' => TechnicalStatus::FAILED->value],
            'new_values' => ['technical_status' => TechnicalStatus::PENDING->value],
        ]);
        new AuditFactory()->create([
            'event' => 'updated',
            'auditable_type' => Subscription::class,
            'auditable_id' => $subscription->id,
            'old_values' => ['technical_status' => TechnicalStatus::PENDING->value],
            'new_values' => ['technical_status' => TechnicalStatus::OK->value],
        ]);

        $auditlogPaginator = $this->fetchAuditLogsForCustomerAction->execute($this->customer, 1);

        self::assertSame(1, $auditlogPaginator->perPage());
        self::assertSame(2, $auditlogPaginator->total());

        $firstAudit = $auditlogPaginator->items()[0];

        $auditableSubscription = $firstAudit->auditable;
        self::assertInstanceOf(Subscription::class, $auditableSubscription);

        self::assertNotNull($auditableSubscription->domain);
        self::assertSame(AuditLogEvent::UPDATED->value, $firstAudit->event);
        self::assertSame(['technical_status' => TechnicalStatus::PENDING->value], $firstAudit->old_values);
        self::assertSame(['technical_status' => TechnicalStatus::OK->value], $firstAudit->new_values);
        self::assertSame(Subscription::class, $firstAudit->auditable_type);
        self::assertSame($subscription->id, $firstAudit->auditable_id);
        self::assertSame($firstAudit->auditable_id, $auditableSubscription->id);
        self::assertSame($subscription->domain, $auditableSubscription->domain);
        self::assertSame(ProductGroupType::EXTENSION, $auditableSubscription->product->productGroup->slug);
        self::assertNull($firstAudit->tags);
        self::assertNotNull($firstAudit->url);
        self::assertNotNull($firstAudit->ip_address);
    }

    #[Test]
    public function execute365(): void
    {
        $group = new ProductGroupFactory()->createOne([
            'name' => 'Microsoft 365',
            'slug' => ProductGroupType::MICROSOFT_365,
        ]);

        $product = new ProductFactory()->for($group)->createOne([
            'slug' => 'microsoft-business-standard-parent',
        ]);

        $mainSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($product)
            ->makeOne([
                'domain' => null,
            ]);

        $product->subscriptions()->save($mainSubscription);

        $customerInfo = new Microsoft365CustomerInfoFactory()->createOne([
            'customer_id' => $this->customer->id,
            'tenant_name' => $this->customer->customer_number . '.onmicrosoft.com',
        ]);

        $microsoftSub = new Microsoft365DeploymentFactory()->createOne([
            'subscription_id' => $mainSubscription->id,
            'microsoft365_customer_info_id' => $customerInfo->id,
        ]);

        new AuditFactory()->create([
            'event' => 'updated',
            'auditable_type' => Microsoft365Deployment::class,
            'auditable_id' => $microsoftSub->id,
            'old_values' => ['technical_status' => TechnicalStatus::PENDING->value],
            'new_values' => ['technical_status' => TechnicalStatus::OK->value],
        ]);

        $auditlogPaginator = $this->fetchAuditLogsForCustomerAction->execute($this->customer, 1);

        $lastAudit = $auditlogPaginator->items()[0];

        $auditableDeployment = $lastAudit->auditable;
        self::assertInstanceOf(Microsoft365Deployment::class, $auditableDeployment);
        self::assertSame(
            ProductGroupType::MICROSOFT_365,
            $auditableDeployment->subscription->product->productGroup->slug,
        );
        self::assertSame($customerInfo->tenant_name, $auditableDeployment->microsoft365CustomerInfo->tenant_name);
    }

    #[Test]
    public function VirtualMachineDeployment(): void
    {
        $this->createCloudStackSubscriptions();

        $customer = Customer::where('organization', 'cloudstack')->firstOrFail();

        $virtualMachineDomain = VirtualMachineDeployment::firstOrFail();

        new AuditFactory()->create([
            'event' => 'updated',
            'auditable_type' => VirtualMachineDeployment::class,
            'auditable_id' => $virtualMachineDomain->id,
            'old_values' => ['domain_name' => 'name'],
            'new_values' => ['domain_name' => 'naaaame'],
            'identity_uuid' => $this->customer->uuid,
        ]);

        $auditlogPaginator = $this->fetchAuditLogsForCustomerAction->execute($customer, 1);

        $lastAudit = $auditlogPaginator->items()[0];

        $auditableDeployment = $lastAudit->auditable;
        self::assertInstanceOf(VirtualMachineDeployment::class, $auditableDeployment);
        self::assertSame(
            ProductGroupType::CLOUDSTACK_VIRTUAL_MACHINE,
            $auditableDeployment->subscription->product->productGroup->slug,
        );
        self::assertSame(
            $virtualMachineDomain->managerDomainDeployment->domain_name,
            $auditableDeployment->managerDomainDeployment->domain_name,
        );
    }

    #[Test]
    public function testEagerLoadingWithOldNamespace(): void
    {
        $oldDomainSubscriptionNamespace = 'Modules\DomainService\Models\Subscription';
        $prodGroup = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::EXTENSION,
        ]);
        $product = new ProductFactory()->for($prodGroup)->createOne([]);

        $subscription = new SubscriptionFactory()
            ->for($product)
            ->for($this->customer)
            ->createOne();
        $domainDeployment = new DomainDeploymentFactory()
            ->for($subscription)
            ->withPlaceholderProvider()
            ->createOne();

        new AuditFactory()->create([
            'event' => 'created',
            'auditable_type' => $oldDomainSubscriptionNamespace,
            'auditable_id' => $domainDeployment->id,
            'old_values' => ['technical_status' => TechnicalStatus::PENDING->value],
            'new_values' => ['technical_status' => TechnicalStatus::OK->value],
            'identity_uuid' => $this->customer->uuid,
        ]);

        $auditlogPaginator = $this->fetchAuditLogsForCustomerAction->execute($this->customer, 1);

        self::assertSame(1, $auditlogPaginator->perPage());

        $firstAudit = $auditlogPaginator->items()[0];

        $auditableDeployment = $firstAudit->auditable;
        self::assertInstanceOf(DomainDeployment::class, $auditableDeployment);

        self::assertTrue($auditableDeployment->relationLoaded('subscription'));

        self::assertNotNull($auditableDeployment->subscription->domain);
        self::assertSame($oldDomainSubscriptionNamespace, $firstAudit->auditable_type);
        self::assertSame($firstAudit->auditable_id, $domainDeployment->id);
        self::assertSame($subscription->domain, $auditableDeployment->subscription->domain);
        self::assertSame(ProductGroupType::EXTENSION, $auditableDeployment->subscription->product->productGroup->slug);
    }

    private function createCloudStackSubscriptions(): void
    {
        $customer = new CustomerFactory()->createOne([
            'organization' => 'cloudstack',
        ]);

        $environment = new CloudstackEnvironmentFactory()->createOne([
            'name' => 'Test Environment',
            'ui_url' => 'https://ui',
            'domain_name' => 'test/vps',
        ]);

        $managerDomainDeployment = new CloudstackManagerDomainDeploymentFactory()->for($environment)->createOne([
            'customer_id' => $customer->id,
            'domain_name' => 'cs84583951',
            'account' => 'cs84583951',
            'username' => 'cs84583951',
        ]);

        $virtualMachineProductGroup = new ProductGroupFactory()->cloudstackVirtualMachine()->createOne();
        $virtualMachineProduct = new ProductFactory()->for($virtualMachineProductGroup)->createOne();
        new CloudstackEnvironmentProductFactory()
            ->for($virtualMachineProduct)
            ->for($environment)
            ->create();

        $volumeProductGroup = new ProductGroupFactory()->cloudstackVolume()->createOne();
        $volumeProduct = new ProductFactory()->for($volumeProductGroup)->createOne();
        new CloudstackEnvironmentProductFactory()
            ->for($volumeProduct)
            ->for($environment)
            ->create();

        $virtualMachineSubscription = new SubscriptionFactory()
            ->for($virtualMachineProduct)
            ->for($customer)
            ->createOne();

        new CloudstackVirtualMachineDeploymentFactory()->for($managerDomainDeployment)->create([
            'subscription_uuid' => $virtualMachineSubscription->uuid,
            'cloudstack_id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
        ]);

        $volumeSubscription = new SubscriptionFactory()
            ->for($volumeProduct)
            ->for($customer)
            ->parentSubscription($virtualMachineSubscription)
            ->createOne([
                'uuid' => '45ff89c4-eeee-eeee-eeee-eeeeeeeeeeee',
            ]);

        new CloudstackVolumeDeploymentFactory()->for($managerDomainDeployment)->create([
            'subscription_uuid' => $volumeSubscription->uuid,
            'cloudstack_id' => 'eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee',
        ]);

        $volumeSubscriptionCanceled = new SubscriptionFactory()
            ->for($volumeProduct)
            ->for($customer)
            ->parentSubscription($virtualMachineSubscription)
            ->createOne([
                'domain' => 'Volume 9',
                'start_date' => new CarbonImmutable()->subWeek(),
                'end_date' => new CarbonImmutable()->addMonth()->subWeek(),
                'cancel_date' => new CarbonImmutable()->subDay(),
                'administrative_status' => AdministrativeStatus::CANCELED->value,
            ]);

        new CloudstackVolumeDeploymentFactory()->for($managerDomainDeployment)->create([
            'subscription_uuid' => $volumeSubscriptionCanceled->uuid,
            'cloudstack_id' => '99999999-9999-9999-9999-999999999999',
        ]);
    }
}
