<?php

declare(strict_types=1);

namespace Tests\Domain\VPS\Unit\Repositories;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\VPS\Exceptions\VirtualMachineNotFoundException;
use Waterfront\Domain\VPS\Repositories\VirtualMachineDeploymentRepository;

#[CoversClass(VirtualMachineDeploymentRepository::class)]
class VirtualMachineSubscriptionRepositoryTest extends IntegrationTestCase
{
    #[Test]
    public function getOsSubscriptionChildSuccess(): void
    {
        $vpsSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->administrativeStatusActive()
            ->for(new ProductFactory()->for(new ProductGroupFactory()->vps()))
            ->createOne();

        $osSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->administrativeStatusActive()
            ->for(new ProductFactory()->for(new ProductGroupFactory()->cloudstackOs()))
            ->createOne();

        $vpsSubscription->children()->save($osSubscription);

        $repository = new VirtualMachineDeploymentRepository();
        $child = $repository->getOsSubscriptionChildFromSubscriptionUuid($vpsSubscription->uuid);

        self::assertSame($osSubscription->uuid, $child->uuid);
    }

    #[Test]
    public function getOsSubscriptionChildNonExisting(): void
    {
        $vpsSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->administrativeStatusActive()
            ->for(new ProductFactory()->for(new ProductGroupFactory()->vps()))
            ->createOne();

        $repository = new VirtualMachineDeploymentRepository();

        $expectedMessage = sprintf(
            'Could not find a subscription for VM %s with a Cloudstack OS as child subscription.',
            $vpsSubscription->uuid,
        );

        $this->expectException(VirtualMachineNotFoundException::class);
        $this->expectExceptionMessageIs($expectedMessage);

        $repository->getOsSubscriptionChildFromSubscriptionUuid($vpsSubscription->uuid);
    }
}
