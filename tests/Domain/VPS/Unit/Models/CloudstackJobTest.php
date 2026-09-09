<?php

declare(strict_types=1);

namespace Tests\Domain\VPS\Unit\Models;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CloudstackEnvironmentFactory;
use Tests\Factories\CloudstackJobFactory;
use Tests\Factories\CloudstackManagerDomainDeploymentFactory;
use Tests\Factories\CloudstackVirtualMachineDeploymentFactory;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\VPS\Models\CloudstackJob;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;

#[CoversClass(CloudstackJob::class)]
class CloudstackJobTest extends IntegrationTestCase
{
    #[Test]
    public function virtualMachineSubscriptionsRelation(): void
    {
        $customer = new CustomerFactory()->createOne();

        $vmGroup = new ProductGroupFactory()->vps()->createOne([
            'name' => 'vps',
        ]);

        $vmProduct = new ProductFactory()->createOne([
            'product_group_id' => $vmGroup->id,
            'name' => 'VPS large',
            'slug' => 'vps_large',
        ]);

        $environment = new CloudstackEnvironmentFactory()->createOne();

        $managerSubscription = new CloudstackManagerDomainDeploymentFactory()
            ->for($customer)
            ->for($environment)
            ->createOne();

        $subscription = new SubscriptionFactory()->withCustomer()->createOne([
            'product_uuid' => $vmProduct->uuid,
            'customer_id' => $customer->id,
        ]);

        $vmSubscription = new CloudstackVirtualMachineDeploymentFactory()
            ->for($subscription, 'subscription')
            ->for($managerSubscription, 'managerDomainDeployment')
            ->createOne();

        $cloudstackJob = new CloudstackJobFactory()->createOne([
            'vm_deployment_id' => $vmSubscription->id,
        ]);

        $vmSubscription->refresh();

        self::assertInstanceOf(CloudstackJob::class, $vmSubscription->cloudstackJobs->first());
        self::assertSame($cloudstackJob->id, $vmSubscription->cloudstackJobs->first()->id);

        self::assertInstanceOf(VirtualMachineDeployment::class, $cloudstackJob->virtualMachineDeployment);
        self::assertSame($vmSubscription->id, $cloudstackJob->virtualMachineDeployment->id);
    }
}
