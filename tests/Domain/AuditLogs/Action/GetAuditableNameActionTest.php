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
use Tests\Factories\Microsoft365CustomerInfoFactory;
use Tests\Factories\Microsoft365DeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\AuditLogs\Actions\GetAuditableNameAction;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\History\Models\Audit;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\VPS\Models\ManagerDomainDeployment;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;

#[CoversClass(GetAuditableNameAction::class)]
class GetAuditableNameActionTest extends IntegrationTestCase
{
    #[Test]
    public function getAuditableName(): void
    {
        $customer = new CustomerFactory()->createOne();

        $getAuditableNameAction = self::resolve(GetAuditableNameAction::class);

        new AuditFactory()->create([
            'event' => 'updated',
            'auditable_type' => Customer::class,
            'auditable_id' => $customer->id,
            'old_values' => '[]',
            'new_values' => ['first_name' => $customer->first_name],
        ]);
        $audit = Audit::where('auditable_type', Customer::class)->firstOrFail();

        $result = $getAuditableNameAction->execute($audit);
        self::assertSame("$customer->first_name $customer->last_name", $result);

        unset($audit->customer);
        $audit->auditable_type = "App\Models\WeirdNamespace";
        $audit->save();

        $result = $getAuditableNameAction->execute($audit);
        self::assertNull($result);
    }

    #[Test]
    public function getCloudstackAuditableName(): void
    {
        $this->createCloudStackSubscriptions();

        Customer::query()->where('organization', 'cloudstack')->first();

        $virtualMachineDomain = VirtualMachineDeployment::firstOrFail();

        $audit = new AuditFactory()->createOne([
            'event' => 'updated',
            'auditable_type' => VirtualMachineDeployment::class,
            'auditable_id' => $virtualMachineDomain->id,
            'old_values' => ['domain_name' => 'name'],
            'new_values' => ['domain_name' => 'naaaame'],
        ]);

        $audit->auditable?->with('subscription.product.productGroup', 'managerDomainDeployment')->get()->toArray();

        $getAuditableNameAction = new GetAuditableNameAction();

        $result = $getAuditableNameAction->execute($audit);

        self::assertSame($virtualMachineDomain->managerDomainDeployment->domain_name, $result);
    }

    #[Test]
    public function getCloudstackManagerDomainAuditableName(): void
    {
        $this->createCloudStackSubscriptions();

        Customer::where('organization', 'cloudstack')->firstOrFail();

        $cloudStackManagerDomain = ManagerDomainDeployment::firstOrFail();

        $audit = new AuditFactory()->createOne([
            'event' => 'updated',
            'auditable_type' => ManagerDomainDeployment::class,
            'auditable_id' => $cloudStackManagerDomain->id,
            'old_values' => ['domain_name' => 'name'],
            'new_values' => ['domain_name' => 'naaaame'],
        ]);

        $getAuditableNameAction = new GetAuditableNameAction();

        $result = $getAuditableNameAction->execute($audit);

        self::assertSame($cloudStackManagerDomain->domain_name, $result);
    }

    #[Test]
    public function getMicrosoftAuditableName(): void
    {
        $customer = new CustomerFactory()->createOne();
        $group = new ProductGroupFactory()->createOne([
            'name' => 'Microsoft 365',
            'slug' => ProductGroupType::MICROSOFT_365,
        ]);

        $product = new ProductFactory()->for($group)->createOne([
            'slug' => 'microsoft-business-standard-parent',
        ]);

        $mainSubscription = new SubscriptionFactory()->for($product)->makeOne([
            'customer_id' => $customer->id,
            'domain' => null,
        ]);

        $product->subscriptions()->save($mainSubscription);

        $customerInfo = new Microsoft365CustomerInfoFactory()->createOne([
            'customer_id' => $customer->id,
            'tenant_name' => $customer->customer_number . '.onmicrosoft.com',
        ]);

        $microsoftSub = new Microsoft365DeploymentFactory()->createOne([
            'subscription_id' => $mainSubscription->id,
            'microsoft365_customer_info_id' => $customerInfo->id,
        ]);

        $audit = new AuditFactory()->createOne([
            'event' => 'updated',
            'auditable_type' => Microsoft365Deployment::class,
            'auditable_id' => $microsoftSub->id,
            'old_values' => ['technical_status' => TechnicalStatus::PENDING->value],
            'new_values' => ['technical_status' => TechnicalStatus::OK->value],
        ]);

        $getAuditableNameAction = new GetAuditableNameAction();

        $result = $getAuditableNameAction->execute($audit);

        self::assertSame($customerInfo->tenant_name, $result);
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
        $volumeProductGroup = new ProductGroupFactory()->cloudstackVolume()->createOne();

        $virtualMachineProduct = new ProductFactory()->for($virtualMachineProductGroup)->createOne();
        $volumeProduct = new ProductFactory()->for($volumeProductGroup)->createOne();

        new CloudstackEnvironmentProductFactory()
            ->for($virtualMachineProduct)
            ->for($environment)
            ->create();
        new CloudstackEnvironmentProductFactory()
            ->for($volumeProduct)
            ->for($environment)
            ->create();

        $virtualMachineSubscription = new SubscriptionFactory()
            ->for($virtualMachineProduct)
            ->for($customer)
            ->createOne();
        $volumeSubscription = new SubscriptionFactory()
            ->for($volumeProduct)
            ->for($customer)
            ->parentSubscription($virtualMachineSubscription)
            ->createOne([
                'uuid' => '45ff89c4-eeee-eeee-eeee-eeeeeeeeeeee',
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

        new CloudstackVirtualMachineDeploymentFactory()->for($managerDomainDeployment)->create([
            'subscription_uuid' => $virtualMachineSubscription->uuid,
            'cloudstack_id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
        ]);
        new CloudstackVolumeDeploymentFactory()->for($managerDomainDeployment)->create([
            'subscription_uuid' => $volumeSubscription->uuid,
            'cloudstack_id' => 'eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee',
        ]);
        new CloudstackVolumeDeploymentFactory()->for($managerDomainDeployment)->create([
            'subscription_uuid' => $volumeSubscriptionCanceled->uuid,
            'cloudstack_id' => '99999999-9999-9999-9999-999999999999',
        ]);
    }
}
