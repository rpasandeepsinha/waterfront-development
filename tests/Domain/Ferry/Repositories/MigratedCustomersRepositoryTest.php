<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Repositories;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\MigratedCustomersFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Ferry\Repositories\MigratedCustomersRepository;

#[CoversClass(MigratedCustomersRepository::class)]
class MigratedCustomersRepositoryTest extends IntegrationTestCase
{
    private MigratedCustomersRepository $migratedCustomersRepository;

    public function setUp(): void
    {
        parent::setUp();

        $this->migratedCustomersRepository = self::resolve(MigratedCustomersRepository::class);

        $customerFromWrongGroup = new CustomerFactory()->createOne();
        new MigratedCustomersFactory()->createOne([
            'group_type' => 'wrongGroup',
        ])->customers()->attach($customerFromWrongGroup);
    }

    #[Test]
    public function testGetMigratedCustomersByGroupType(): void
    {
        $groupType = 'GetMigratedCustomers';

        $customer = new CustomerFactory()->createOne();
        $migratedCustomer1 = new MigratedCustomersFactory()->createOne([
            'group_type' => $groupType,
        ]);
        $migratedCustomer1->customers()->attach($customer);
        $customer2 = new CustomerFactory()->createOne();
        $migratedCustomer2 = new MigratedCustomersFactory()->createOne([
            'group_type' => $groupType,
        ]);
        $migratedCustomer2->customers()->attach($customer2);

        $migratedCustomers = $this->migratedCustomersRepository->getMigratedCustomersByGroupType($groupType);
        self::assertCount(2, $migratedCustomers);
        self::assertInstanceOf(MigratedCustomer::class, $migratedCustomers[0]);
        self::assertInstanceOf(MigratedCustomer::class, $migratedCustomers[1]);
        self::assertEqualsCanonicalizing([$migratedCustomer1->id, $migratedCustomer2->id], [$migratedCustomers[0]->id, $migratedCustomers[1]->id]);

        $emptyGroupTypeResult = $this->migratedCustomersRepository->getMigratedCustomersByGroupType('');
        self::assertCount(0, $emptyGroupTypeResult);
    }

    #[Test]
    public function mailBlockedMigratedCustomer(): void
    {
        $email = 'will.be.blocked@example.com';
        $customer = new CustomerFactory()->createOne([
            'email' => $email,
        ]);

        $migratedCustomer = new MigratedCustomersFactory()->createOne([
            'successful' => false,
        ]);
        $migratedCustomer->customers()->attach($customer);

        $result = $this->migratedCustomersRepository->isMigratedCustomerEligibleForMailing($email);
        self::assertFalse($result);
    }

    #[Test]
    public function notMailBlockedMigratedCustomer(): void
    {
        $email = 'will.not.be.blocked@example.com';
        $customer = new CustomerFactory()->createOne([
            'email' => $email,
        ]);

        $migratedCustomer = new MigratedCustomersFactory()->createOne([
            'successful' => true,
        ]);
        $migratedCustomer->customers()->attach($customer);

        $result = $this->migratedCustomersRepository->isMigratedCustomerEligibleForMailing($email);
        self::assertTrue($result);
    }

    #[Test]
    public function notBlockedNoMigratedCustomerFound(): void
    {
        $email = 'will.not.be.blocked@example.com';
        new CustomerFactory()->createOne([
            'email' => $email,
        ]);

        $result = $this->migratedCustomersRepository->isMigratedCustomerEligibleForMailing($email);
        self::assertTrue($result);
    }
}
