<?php

declare(strict_types=1);

namespace Tests\Domain\Customers\Repositories;

use DateTime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\CustomerWalletFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Repositories\CustomerWalletRepository;

#[CoversClass(CustomerWalletRepository::class)]
class CustomerWalletRepositoryTest extends IntegrationTestCase
{
    private CustomerWalletRepository $customerWalletRepository;

    public function setUp(): void
    {
        parent::setUp();

        $customer1 = new CustomerFactory()->createOne();
        $customer2 = new CustomerFactory()->createOne();
        $customer3 = new CustomerFactory()->createOne();

        new CustomerWalletFactory()->create([
            'customer_id' => $customer1->id,
            'amount' => 100,
            'refund_requested_at' => new DateTime('2023-01-5'),
            'csv_downloaded_at' => new DateTime('2023-01-15'),
        ]);
        new CustomerWalletFactory()->create([
            'customer_id' => $customer2->id,
            'amount' => 200,
            'refund_requested_at' => new DateTime('2023-01-10'),
            'csv_downloaded_at' => null,
        ]);
        new CustomerWalletFactory()->create([
            'customer_id' => $customer3->id,
            'amount' => 300,
            'refund_requested_at' => null,
            'csv_downloaded_at' => null,
        ]);

        $this->customerWalletRepository = self::resolve(CustomerWalletRepository::class);
    }

    #[Test]
    public function findAll(): void
    {
        $result = $this->customerWalletRepository->findAll();
        self::assertCount(3, $result);
    }
}
