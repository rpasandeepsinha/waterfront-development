<?php

declare(strict_types=1);

namespace Tests\Domain\Microsoft365\Integration\Repositories;

use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Factories\CustomerFactory;
use Tests\Factories\Microsoft365CustomerInfoFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Microsoft365\Repositories\Microsoft365CustomerInfoRepository;

#[CoversClass(Microsoft365CustomerInfoRepository::class)]
class Microsoft365CustomerInfoRepositoryTest extends IntegrationTestCase
{
    private Microsoft365CustomerInfoRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = self::resolve(Microsoft365CustomerInfoRepository::class);
    }

    public function testFindAllByCustomerReturnsEveryTenantForTheCustomerOrderedById(): void
    {
        $customer = new CustomerFactory()->createOne();
        $otherCustomer = new CustomerFactory()->createOne();

        $firstTenant = new Microsoft365CustomerInfoFactory()->for($customer)->createOne();
        $secondTenant = new Microsoft365CustomerInfoFactory()->for($customer)->createOne();
        new Microsoft365CustomerInfoFactory()->for($otherCustomer)->createOne();

        $tenants = $this->repository->findAllByCustomer($customer)->values()->all();

        self::assertCount(2, $tenants);
        self::assertSame($firstTenant->id, $tenants[0]->id);
        self::assertSame($secondTenant->id, $tenants[1]->id);
    }

    public function testFindAllByCustomerReturnsEmptyCollectionWhenCustomerHasNoTenants(): void
    {
        $customer = new CustomerFactory()->createOne();

        $tenants = $this->repository->findAllByCustomer($customer);

        self::assertCount(0, $tenants);
    }
}
