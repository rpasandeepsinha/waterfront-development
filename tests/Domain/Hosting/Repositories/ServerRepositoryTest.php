<?php

declare(strict_types=1);

namespace Tests\Domain\Hosting\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Hosting\Repositories\ServerRepository;
use Waterfront\Domain\Servers\Enums\ServerType;

#[CoversClass(ServerRepository::class)]
class ServerRepositoryTest extends IntegrationTestCase
{
    #[Test]
    public function findAvailableServer(): void
    {
        new ServerFactory()->plesk()->createOne();

        $serverRepository = self::resolve(ServerRepository::class);
        $server = $serverRepository->findAvailableServer(ServerType::PLESK);

        self::assertSame(ServerType::PLESK, $server->type);
    }

    #[Test]
    public function doNotFindSoftDeletedServer(): void
    {
        new ServerFactory()->createOne([
            'deleted_at' => CarbonImmutable::now(),
        ]);

        self::expectException(ModelNotFoundException::class);
        $serverRepository = self::resolve(ServerRepository::class);
        $serverRepository->findAvailableServer(ServerType::PLESK);
    }

    #[Test]
    public function findServerWithLeastActiveSubscriptions(): void
    {
        $customer = new CustomerFactory()->createOne();
        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();
        $server1 = new ServerFactory()->createOne();
        $server2 = new ServerFactory()->createOne();

        $subscription = new SubscriptionFactory()
            ->for($customer)
            ->for($product)
            ->createOne();
        new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'server_id' => $server1->id,
        ]);

        $serverRepository = self::resolve(ServerRepository::class);
        $server = $serverRepository->findAvailableServer(ServerType::PLESK);
        self::assertSame($server2->id, $server->id);
    }
}
