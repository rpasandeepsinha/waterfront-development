<?php

declare(strict_types=1);

namespace Tests\Domain\Customers\Action;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\MigratedCustomersFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Actions\MarkMigratedCustomersAdministrativeSuccessfulAction;

#[CoversClass(MarkMigratedCustomersAdministrativeSuccessfulAction::class)]
class MarkMigratedCustomersAdministrativeSuccessfulActionTest extends IntegrationTestCase
{
    #[Test]
    public function executeMarksEveryMigrationOfTheCustomerAsAdministrativeSuccessful(): void
    {
        $customer = CustomerFactory::new()->createOne();

        $unsuccessful = MigratedCustomersFactory::new()->createOne(['administrative_successful' => false]);
        $unsuccessful->customers()->attach($customer->id);

        $alreadySuccessful = MigratedCustomersFactory::new()->createOne(['administrative_successful' => true]);
        $alreadySuccessful->customers()->attach($customer->id);

        self::resolve(MarkMigratedCustomersAdministrativeSuccessfulAction::class)->execute($customer);

        self::assertTrue($unsuccessful->refresh()->administrative_successful);
        self::assertTrue($alreadySuccessful->refresh()->administrative_successful);
    }

    #[Test]
    public function executeLeavesMigrationsOfOtherCustomersUntouched(): void
    {
        $customer = CustomerFactory::new()->createOne();
        $otherCustomer = CustomerFactory::new()->createOne();

        $migratedCustomer = MigratedCustomersFactory::new()->createOne(['administrative_successful' => false]);
        $migratedCustomer->customers()->attach($customer->id);

        $otherMigratedCustomer = MigratedCustomersFactory::new()->createOne(['administrative_successful' => false]);
        $otherMigratedCustomer->customers()->attach($otherCustomer->id);

        self::resolve(MarkMigratedCustomersAdministrativeSuccessfulAction::class)->execute($customer);

        self::assertTrue($migratedCustomer->refresh()->administrative_successful);
        self::assertFalse($otherMigratedCustomer->refresh()->administrative_successful);
    }
}
