<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Services\ManualMigration;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\MigratedCustomersFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Requests\ManualMigrationMigrateRequest;
use Waterfront\Domain\Ferry\Services\ManualMigration\MigratedCustomerService;

#[CoversClass(MigratedCustomerService::class)]
class MigratedCustomerServiceTest extends IntegrationTestCase
{
    #[Test]
    public function validateShouldFailIfMigratedCustomerExists(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $customer = new CustomerFactory()->createOne();
        $migrationCustomer = new MigratedCustomersFactory()->createOne(['group_type' => 'non_manual_migration']);
        $migrationCustomer->customers()->attach($customer);
        $customer->refresh();

        $service = new MigratedCustomerService();
        $service->validateAndCreateMigratedCustomer(new ManualMigrationMigrateRequest(), $customer);
    }

    #[Test]
    public function validateShouldPassIfMigratedCustomerExistsForManualMigration(): void
    {
        $customer = new CustomerFactory()->createOne();
        $migrationCustomer = new MigratedCustomersFactory()->createOne(['group_type' => 'manual_migration']);
        $migrationCustomer->customers()->attach($customer);
        $customer->refresh();

        $service = new MigratedCustomerService();
        $httpRequest = ManualMigrationMigrateRequest::create('', parameters: [
            'reference_customer_number' => '123',
            'source_business_unit' => 'versio',
        ]);
        $service->validateAndCreateMigratedCustomer($httpRequest, $customer);

        // No changes should have been made
        self::assertCount(1, $customer->migratedCustomers);
    }

    #[Test]
    public function validateShouldPassAndCreateMigratedCustomersIfNoneExist(): void
    {
        $customer = new CustomerFactory()->createOne();
        $httpRequest = ManualMigrationMigrateRequest::create('', parameters: [
            'reference_customer_number' => '123',
            'source_business_unit' => 'versio',
        ]);
        $service = new MigratedCustomerService();
        $service->validateAndCreateMigratedCustomer($httpRequest, $customer);

        // No changes should have been made
        self::assertCount(1, $customer->migratedCustomers);
    }
}
