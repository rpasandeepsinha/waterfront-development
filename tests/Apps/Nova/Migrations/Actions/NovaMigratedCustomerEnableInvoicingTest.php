<?php

declare(strict_types=1);

namespace Tests\Apps\Nova\Migrations\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\MigratedCustomersFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Nova\Migrations\Actions\NovaMigratedCustomerEnableInvoicingAction;

#[CoversClass(NovaMigratedCustomerEnableInvoicingAction::class)]
class NovaMigratedCustomerEnableInvoicingTest extends IntegrationTestCase
{
    #[Test]
    public function action(): void
    {
        $customer = CustomerFactory::new()->createOne();
        $migratedCustomer = MigratedCustomersFactory::new()->createOne([
            'administrative_successful' => false,
            'enable_invoicing' => false,
            'successful' => false,
        ]);
        $migratedCustomer->customers()->attach($customer->id);

        Event::fake();

        $fields = new ActionFields(new Collection(), new Collection());
        $payload = new Collection([$migratedCustomer]);

        $action = self::resolve(NovaMigratedCustomerEnableInvoicingAction::class);
        $result = $action->handle($fields, $payload);

        self::assertInstanceOf(ActionResponse::class, $result);

        $migratedCustomer->refresh();

        self::assertTrue($migratedCustomer->administrative_successful);
        self::assertTrue($migratedCustomer->enable_invoicing);
        self::assertTrue($migratedCustomer->successful);
    }
}
